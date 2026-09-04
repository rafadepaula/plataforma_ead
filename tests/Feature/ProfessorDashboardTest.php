<?php

namespace Tests\Feature;

use App\Actions\SubmitQuizAttemptAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\ProfessorDashboardService;
use App\Services\UserHomeResolver;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * the `professor` dashboard (`professor.dashboard`,
 * `role:professor`, `ProfessorDashboardService`): every metric starts from
 * `$user->taughtCourses()` — never from the Organization — so the stat
 * cards, the FIFO "Correções mais antigas" queue (top 5, `completed_at
 * asc`), the 7-day forum window and the quick-access list may never leak
 * a Course the professor is not assigned to. Also covers the role gate
 * (Aluno/Gestor → 403) and `UserHomeResolver`'s professor branch.
 */
class ProfessorDashboardTest extends TestCase
{
    private function professorFor(Organization $org): User
    {
        /** @var User $professor */
        $professor = User::factory()->professor()->create(['org_id' => $org->id, 'name' => 'Professor do Dashboard']);

        return $professor;
    }

    private function courseWithEssayQuiz(Organization $org): array
    {
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);

        $choiceQuestion = QuizQuestion::factory()->for($quiz)->singleChoice()->create();
        $correct = QuizOption::factory()->for($choiceQuestion, 'question')->correct()->create();
        QuizOption::factory()->for($choiceQuestion, 'question')->incorrect()->create();

        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        return [$course, $lesson, $choiceQuestion, $correct, $essayQuestion];
    }

    private function enrolledAluno(Course $course, string $name): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null, 'name' => $name]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        return $aluno;
    }

    /**
     * @return QuizAttempt The attempt left in `awaiting_manual_grading`.
     */
    private function pendingEssay(Lesson $lesson, User $aluno, QuizQuestion $choiceQuestion, QuizOption $correct, QuizQuestion $essayQuestion, string $essay = 'Resposta dissertativa.'): QuizAttempt
    {
        return app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $choiceQuestion->id, 'selected_option_ids' => [$correct->id]],
            ['question_id' => $essayQuestion->id, 'essay_answer' => $essay],
        ]);
    }

    private function reportedTopic(Course $course, User $student, string $reason): ForumReport
    {
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        return ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /**
     * The rendered value of a `x-ui.stat-card` carrying the given `dusk`
     * handle — the card's number is the contract under test, and a bare
     * `assertSee('1')` would match almost anything on the page.
     */
    private function statCardValue(string $html, string $dusk): string
    {
        preg_match('/dusk="'.preg_quote($dusk, '/').'".*?stat-card-value[^>]*>\s*([^<]+?)\s*<\/div>/s', $html, $matches);

        return trim($matches[1] ?? '');
    }

    /**
     * Same idea for the bare-number forum-activity counters.
     */
    private function counterValue(string $html, string $dusk): string
    {
        preg_match('/dusk="'.preg_quote($dusk, '/').'">\s*([^<]+?)\s*</', $html, $matches);

        return trim($matches[1] ?? '');
    }

    public function test_stat_cards_reflect_the_assigned_course_metrics(): void
    {
        $org = Organization::factory()->create();
        [$course, $lesson, $choiceQuestion, $correct, $essayQuestion] = $this->courseWithEssayQuiz($org);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $aluno = $this->enrolledAluno($course, 'Aluno Pendente Marques');
        $this->pendingEssay($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion);
        $this->reportedTopic($course, $aluno, 'Denuncia pendente real.');

        $response = $this->actingAs($professor)->get(route('professor.dashboard'));

        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['taught_courses']);
        $this->assertSame(1, $stats['pending_essays']);
        $this->assertSame(1, $stats['pending_reports']);

        $html = $response->getContent();
        $this->assertSame('1', $this->statCardValue($html, 'stat-taught-courses'));
        $this->assertSame('1', $this->statCardValue($html, 'stat-pending-essays'));
        $this->assertSame('1', $this->statCardValue($html, 'stat-pending-reports'));

        $response->assertSee($course->title);
        $response->assertSee('Aluno Pendente Marques');
    }

    public function test_oldest_essays_queue_shows_only_the_five_oldest_attempts_fifo(): void
    {
        $org = Organization::factory()->create();
        [$course, $lesson, $choiceQuestion, $correct, $essayQuestion] = $this->courseWithEssayQuiz($org);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $names = ['Aluno Antigo Um', 'Aluno Antigo Dois', 'Aluno Antigo Tres', 'Aluno Antigo Quatro', 'Aluno Antigo Cinco', 'Aluno Novo Seis'];

        foreach ($names as $index => $name) {
            $aluno = $this->enrolledAluno($course, $name);
            $attempt = $this->pendingEssay($lesson, $aluno, $choiceQuestion, $correct, $essayQuestion, 'Resposta do '.$name.'.');
            // Distinct, unambiguous FIFO stamps: the first created is the
            // oldest, the sixth one falls outside the top-5 cut.
            $attempt->update(['completed_at' => Carbon::now()->subMinutes(60 - $index * 10)]);
        }

        $response = $this->actingAs($professor)->get(route('professor.dashboard'));

        $response->assertOk();

        foreach (['Aluno Antigo Um', 'Aluno Antigo Dois', 'Aluno Antigo Tres', 'Aluno Antigo Quatro', 'Aluno Antigo Cinco'] as $name) {
            $response->assertSee($name);
        }

        $response->assertDontSee('Aluno Novo Seis');

        $oldestEssays = $response->viewData('oldestEssays');
        $this->assertCount(5, $oldestEssays);
        $this->assertSame(
            collect($oldestEssays)->pluck('user.name')->all(),
            array_slice($names, 0, 5),
        );
    }

    public function test_forum_activity_counts_only_the_last_seven_days(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $student = $this->enrolledAluno($course, 'Aluno Forum Antunes');

        $recentTopic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        ForumReply::factory()->for($recentTopic, 'topic')->for($student)->create();

        // Both stale rows sit INSIDE the assigned course, so they prove the
        // 7-day window rather than a course-scope filter.
        ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'created_at' => Carbon::now()->subDays(8),
        ]);

        $staleTopic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'created_at' => Carbon::now()->subDays(9),
        ]);
        ForumReply::factory()->for($staleTopic, 'topic')->for($student)->create(['created_at' => Carbon::now()->subDays(9)]);

        $response = $this->actingAs($professor)->get(route('professor.dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame('1', $this->counterValue($html, 'forum-topics-count'));
        $this->assertSame('1', $this->counterValue($html, 'forum-replies-count'));

        $forumActivity = $response->viewData('forumActivity');
        $this->assertSame(['topics' => 1, 'replies' => 1], $forumActivity);
    }

    public function test_metrics_of_an_unassigned_course_never_leak_into_the_dashboard(): void
    {
        $org = Organization::factory()->create();
        [$courseAssigned, $lessonAssigned, $choiceAssigned, $correctAssigned, $essayAssigned] = $this->courseWithEssayQuiz($org);
        [$courseOther, $lessonOther, $choiceOther, $correctOther, $essayOther] = $this->courseWithEssayQuiz($org);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $courseAssigned->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $alunoAssigned = $this->enrolledAluno($courseAssigned, 'Aluno Visivel Ramos');
        $alunoOther = $this->enrolledAluno($courseOther, 'Aluno Invisivel Teixeira');

        // The other course's data is OLDER and MORE abundant on purpose:
        // if any query forgot the pivot, it would win the FIFO cut and
        // inflate every card.
        $attemptOther = $this->pendingEssay($lessonOther, $alunoOther, $choiceOther, $correctOther, $essayOther, 'Resposta alheia.');
        $attemptOther->update(['completed_at' => Carbon::now()->subDays(3)]);
        $this->reportedTopic($courseOther, $alunoOther, 'Denuncia fora do perimetro.');
        ForumTopic::factory()->for($courseOther)->for($alunoOther)->create(['org_id' => $courseOther->org_id]);

        $attemptAssigned = $this->pendingEssay($lessonAssigned, $alunoAssigned, $choiceAssigned, $correctAssigned, $essayAssigned, 'Resposta visivel.');
        $attemptAssigned->update(['completed_at' => Carbon::now()->subDay()]);
        $this->reportedTopic($courseAssigned, $alunoAssigned, 'Denuncia dentro do perimetro.');

        $response = $this->actingAs($professor)->get(route('professor.dashboard'));

        $response->assertOk();
        $response->assertSee('Aluno Visivel Ramos');
        $response->assertSee($courseAssigned->title);
        $response->assertDontSee('Aluno Invisivel Teixeira');
        $response->assertDontSee($courseOther->title);
        $response->assertDontSee('Denuncia fora do perimetro.');

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['taught_courses']);
        $this->assertSame(1, $stats['pending_essays']);
        $this->assertSame(1, $stats['pending_reports']);

        $this->assertSame([$attemptAssigned->id], $response->viewData('oldestEssays')->pluck('id')->all());
    }

    public function test_aluno_and_gestor_are_forbidden_from_the_professor_dashboard(): void
    {
        $org = Organization::factory()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->actingAs($aluno)->get(route('professor.dashboard'))->assertForbidden();
        $this->actingAs($gestor)->get(route('professor.dashboard'))->assertForbidden();
    }

    public function test_user_home_resolver_sends_a_professor_to_the_professor_dashboard(): void
    {
        $org = Organization::factory()->create();
        $professor = $this->professorFor($org);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $resolver = app(UserHomeResolver::class);

        $this->assertSame(route('professor.dashboard'), $resolver->resolve($professor));
        $this->assertSame(route('student.courses.index'), $resolver->resolve($aluno));
    }

    public function test_a_professor_without_any_course_sees_the_zero_state_dashboard(): void
    {
        $org = Organization::factory()->create();
        $professor = $this->professorFor($org);

        $response = $this->actingAs($professor)->get(route('professor.dashboard'));

        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(0, $stats['taught_courses']);
        $this->assertSame(0, $stats['pending_essays']);
        $this->assertSame(0, $stats['pending_reports']);

        $response->assertSee('Nenhuma correção pendente');
        $response->assertSee('Nenhum curso atribuído a você.');
        $this->assertCount(0, $response->viewData('oldestEssays'));
        $this->assertSame(['topics' => 0, 'replies' => 0], app(ProfessorDashboardService::class)->forumActivity($professor));
    }
}

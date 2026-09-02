<?php

namespace Tests\Feature\Seeders;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\ForumTopic;
use App\Models\InvitationLink;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHPUnit Feature test verifying that running the DatabaseSeeder in
 * development/testing environment builds the minimal demo scenario: a
 * single "Liga Certo" organization, one organizer and one student, one
 * "Curso de Eletricista" with three modules (text, PDF and video), a quiz
 * per module, the enrollment and the completion rules — with no leftover
 * fictitious mass data (invitations, certificates, forum, notifications).
 */
class DatabaseSeederDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_seeding_creates_the_minimal_scenario_and_suppresses_mail_and_events(): void
    {
        Mail::fake();
        Notification::fake();

        $this->seed(DatabaseSeeder::class);

        Mail::assertNothingSent();
        Notification::assertNothingSent();

        // 1. One organization, its organizer and its single student
        //    (plus the global Super Admin from the baseline seeders).
        $this->assertSame(1, Organization::query()->count());
        $this->assertSame('Liga Certo', Organization::query()->first()->name);
        $this->assertSame(3, User::query()->count());
        $this->assertSame(1, User::query()->where('email', 'gestor.ligacerto@plataforma.com')->count());
        $this->assertSame(1, User::query()->where('email', 'aluno.ligacerto@plataforma.com')->count());

        // 2. One course with exactly three modules.
        $course = Course::query()->withoutGlobalScopes()->sole();
        $this->assertSame('Curso de Eletricista', $course->title);
        $this->assertSame(3, Module::query()->where('course_id', $course->id)->count());

        // 3. Three quizzes, one per module.
        $this->assertSame(3, Quiz::query()->count());

        // 4. The first module's quiz carries the essay question.
        $essayCount = QuizQuestion::query()->where('type', 'essay')->count();
        $this->assertSame(1, $essayCount);

        // 5. No fictitious leftover data.
        $this->assertSame(0, InvitationLink::query()->count());
        $this->assertSame(0, Certificate::query()->count());
        $this->assertSame(0, ForumTopic::query()->count());
        $this->assertSame(0, DatabaseNotification::query()->count());
    }

    public function test_seeded_student_is_enrolled_and_completion_rules_match_the_course_goal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $aluno = User::query()->where('email', 'aluno.ligacerto@plataforma.com')->first();
        $course = Course::query()->withoutGlobalScopes()->sole();

        // The student holds a single active enrollment on the course.
        $enrollment = $course->students()->where('users.id', $aluno->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame('active', $enrollment->pivot->status);
        $this->assertSame(0, (int) $enrollment->pivot->progress_percentage);

        // Completion requires every lesson (including the video) done…
        $allLessonsRule = $course->completionRules()->where('rule_type', 'all_lessons')->first();
        $this->assertNotNull($allLessonsRule);
        $this->assertNull($allLessonsRule->target_id);
        $this->assertSame(100, $allLessonsRule->required_percentage);

        // …and a 70% minimum score on the LAST quiz (module 3's quiz).
        $lastModule = Module::query()
            ->where('course_id', $course->id)
            ->orderByDesc('order_index')
            ->first();
        $lastQuiz = Quiz::query()
            ->whereHas('lesson', fn ($query) => $query->where('module_id', $lastModule->id))
            ->first();

        $minQuizScoreRule = $course->completionRules()->where('rule_type', 'min_quiz_score')->sole();
        $this->assertSame($lastQuiz->id, $minQuizScoreRule->target_id);
        $this->assertSame(70, $minQuizScoreRule->required_percentage);
    }

    public function test_seeding_is_idempotent_when_executed_multiple_times(): void
    {
        Mail::fake();
        Notification::fake();

        $this->seed(DatabaseSeeder::class);

        $counts = [
            'organizations' => Organization::query()->count(),
            'users' => User::query()->count(),
            'courses' => Course::query()->withoutGlobalScopes()->count(),
            'modules' => Module::query()->count(),
            'quizzes' => Quiz::query()->count(),
            'questions' => QuizQuestion::query()->count(),
            'completion_rules' => CourseCompletionRule::query()->count(),
        ];

        // Re-run the seeder to assert 100% idempotency.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts['organizations'], Organization::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['courses'], Course::query()->withoutGlobalScopes()->count());
        $this->assertSame($counts['modules'], Module::query()->count());
        $this->assertSame($counts['quizzes'], Quiz::query()->count());
        $this->assertSame($counts['questions'], QuizQuestion::query()->count());
        $this->assertSame($counts['completion_rules'], CourseCompletionRule::query()->count());
    }
}

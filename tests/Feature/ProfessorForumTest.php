<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * the `professor` role's forum perimeter: read access to the
 * assigned Courses' forum, deliberate NO-WRITE on new topics/replies,
 * and moderation parity with the Gestor (pin toggle, edit/delete of
 * foreign posts — attributed to the professor in `forum_post_edits` —
 * plus the `forum-moderation.*` report queue, which revalidates each
 * `postable()` against the policies so a course the professor does not
 * teach simply never surfaces). `ForumTopic`'s `OrgScope` workaround
 * (`org_id` set explicitly at creation) follows `ForumTopicTest`.
 */
class ProfessorForumTest extends TestCase
{
    private function enrolledStudent(Course $course, string $name = 'Aluno Autor Borges'): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null, 'name' => $name]);
        $student->assignRole('aluno');
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    private function publishedCourse(Organization $org): Course
    {
        return Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
    }

    private function topicFor(Course $course, User $author, array $attributes = []): ForumTopic
    {
        return ForumTopic::factory()->for($course)->for($author)->create([
            'org_id' => $course->org_id,
            ...$attributes,
        ]);
    }

    private function professorFor(Organization $org): User
    {
        /** @var User $professor */
        $professor = User::factory()->professor()->create(['org_id' => $org->id, 'name' => 'Professora Helena Braga']);

        return $professor;
    }

    private function assignProfessor(User $professor, Course $course, User $actor): void
    {
        $course->professors()->attach($professor->id, ['assigned_by' => $actor->id]);
    }

    public function test_assigned_professor_reads_the_forum_and_thread_but_an_unassigned_colleague_gets_403(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['title' => 'Topico do curso atribuido']);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)
            ->get(route('forum.index', $course))
            ->assertOk()
            ->assertSee('Topico do curso atribuido');

        $this->actingAs($professor)
            ->get(route('forum.show', [$course, $topic]))
            ->assertOk()
            ->assertSee('Topico do curso atribuido');

        $outsider = $this->professorFor($org, 'Professora Fora do Curso');

        $this->actingAs($outsider)->get(route('forum.index', $course))->assertForbidden();
        $this->actingAs($outsider)->get(route('forum.show', [$course, $topic]))->assertForbidden();
    }

    public function test_professor_cannot_create_a_topic_even_when_assigned(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)
            ->get(route('forum.create', $course))
            ->assertForbidden();

        $response = $this->actingAs($professor)->post(route('forum.store', $course), [
            'title' => 'Topico escrito pelo professor',
            'content' => 'Conteudo que nunca deve ser persistido.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('forum_topics', [
            'course_id' => $course->id,
            'title' => 'Topico escrito pelo professor',
        ]);
        $this->assertSame(0, ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->count());
    }

    public function test_professor_cannot_post_a_reply_even_when_assigned(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)
            ->post(route('forum-replies.store', [$course, $topic]), ['content' => 'Resposta do professor.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('forum_replies', [
            'topic_id' => $topic->id,
            'content' => 'Resposta do professor.',
        ]);
        $this->assertSame(0, $topic->replies()->count());
    }

    public function test_assigned_professor_toggles_the_pin_of_a_topic(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['is_pinned' => false]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)->post(route('forum.pin', [$course, $topic]))->assertRedirect();
        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => true]);

        $this->actingAs($professor)->post(route('forum.pin', [$course, $topic]))->assertRedirect();
        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => false]);
    }

    public function test_assigned_professor_edits_a_students_topic_and_the_history_is_attributed_to_the_professor(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['content' => 'Conteudo original do aluno.']);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)
            ->put(route('forum.update', [$course, $topic]), [
                'title' => $topic->title,
                'content' => 'Conteudo revisado pela professora.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'content' => 'Conteudo revisado pela professora.']);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $professor->id,
            'previous_content' => 'Conteudo original do aluno.',
        ]);

        $this->actingAs($professor)
            ->get(route('forum.show', [$course, $topic]))
            ->assertOk()
            ->assertSee('Conteudo original do aluno.')
            ->assertSee($professor->name);
    }

    public function test_assigned_professor_removes_a_students_topic_directly(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['content' => 'Conteudo a ser removido.']);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)->delete(route('forum.destroy', [$course, $topic]))->assertRedirect();

        $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $professor->id,
            'previous_content' => 'Conteudo a ser removido.',
        ]);
    }

    public function test_moderation_queue_shows_only_reports_from_assigned_courses(): void
    {
        $org = Organization::factory()->create();
        $courseAssigned = $this->publishedCourse($org);
        $courseOther = $this->publishedCourse($org);

        $studentAssigned = $this->enrolledStudent($courseAssigned, 'Aluno Reportado Um');
        $studentOther = $this->enrolledStudent($courseOther, 'Aluno Reportado Dois');

        $topicAssigned = $this->topicFor($courseAssigned, $studentAssigned, ['content' => 'Publicacao denunciada do curso atribuido.']);
        $topicOther = $this->topicFor($courseOther, $studentOther, ['content' => 'Publicacao denunciada do curso alheio.']);

        $reportAssigned = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicAssigned->id,
            'reported_by' => $studentAssigned->id,
            'reason' => 'Motivo do curso atribuido.',
            'status' => 'pending',
        ]);
        $reportOther = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicOther->id,
            'reported_by' => $studentOther->id,
            'reason' => 'Motivo do curso alheio.',
            'status' => 'pending',
        ]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $courseAssigned, $gestor);

        $response = $this->actingAs($professor)->get(route('forum-moderation.index'));

        $response->assertOk();
        $response->assertSee('report-row-'.$reportAssigned->id, false);
        $response->assertSee('Motivo do curso atribuido.');
        $response->assertSee('Publicacao denunciada do curso atribuido.');
        $response->assertDontSee('report-row-'.$reportOther->id, false);
        $response->assertDontSee('Motivo do curso alheio.');
        $response->assertDontSee('Publicacao denunciada do curso alheio.');
    }

    public function test_assigned_professor_dismisses_a_report_from_their_course(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student);
        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)->post(route('forum-moderation.dismiss', $report))->assertRedirect();

        $this->assertDatabaseHas('forum_reports', [
            'id' => $report->id,
            'status' => 'reviewed_dismissed',
            'reviewed_by' => $professor->id,
        ]);
        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'deleted_at' => null]);
    }

    public function test_assigned_professor_removes_the_reported_post_and_the_report_is_marked_reviewed_removed(): void
    {
        $org = Organization::factory()->create();
        $course = $this->publishedCourse($org);
        $student = $this->enrolledStudent($course);
        $topic = $this->topicFor($course, $student, ['content' => 'Conteudo denunciado e removido.']);
        $report = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'reported_by' => $student->id,
            'status' => 'pending',
        ]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $course, $gestor);

        $this->actingAs($professor)->post(route('forum-moderation.remove', $report))->assertRedirect();

        $this->assertDatabaseHas('forum_reports', [
            'id' => $report->id,
            'status' => 'reviewed_removed',
            'reviewed_by' => $professor->id,
        ]);
        $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $professor->id,
            'previous_content' => 'Conteudo denunciado e removido.',
        ]);
    }

    public function test_content_of_an_unassigned_course_never_reaches_the_moderation_queue_nor_its_actions(): void
    {
        $org = Organization::factory()->create();
        $courseAssigned = $this->publishedCourse($org);
        $courseOther = $this->publishedCourse($org);

        $studentAssigned = $this->enrolledStudent($courseAssigned, 'Aluno Denunciante A');
        $studentOther = $this->enrolledStudent($courseOther, 'Aluno Denunciante B');

        $topicAssigned = $this->topicFor($courseAssigned, $studentAssigned);
        $replyOther = ForumReply::factory()->for($this->topicFor($courseOther, $studentOther), 'topic')
            ->for($studentOther)
            ->create(['content' => 'Resposta alheia fora do perimetro.']);

        $reportAssigned = ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicAssigned->id,
            'reported_by' => $studentAssigned->id,
            'status' => 'pending',
        ]);
        $reportOther = ForumReport::factory()->create([
            'postable_type' => ForumReply::class,
            'postable_id' => $replyOther->id,
            'reported_by' => $studentOther->id,
            'status' => 'pending',
        ]);

        $gestor = $this->actingAsOrgUser($org);
        $professor = $this->professorFor($org);
        $this->assignProfessor($professor, $courseAssigned, $gestor);

        $this->actingAs($professor)
            ->get(route('forum-moderation.index'))
            ->assertOk()
            ->assertSee('report-row-'.$reportAssigned->id, false)
            ->assertDontSee('report-row-'.$reportOther->id, false)
            ->assertDontSee('Resposta alheia fora do perimetro.');

        $this->actingAs($professor)->post(route('forum-moderation.dismiss', $reportOther))->assertForbidden();

        $this->assertDatabaseHas('forum_reports', [
            'id' => $reportOther->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }
}

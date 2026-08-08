<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumPostEdit;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-10 §3 — E2E coverage of the forum's full browser-facing flow:
 * an enrolled Aluno posts a topic and a reply, edits the topic and views
 * its edit-history modal, denounces a reply, and the course's Gestor
 * reviews the moderation queue and pins the topic.
 */
class ForumDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_student_posts_a_topic_and_reply_edits_the_topic_and_views_the_history_modal(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('forum.index', $course))
                ->waitFor('@new-topic-button')
                ->click('@new-topic-button')
                ->waitFor('@new-topic-form')
                ->type('title', 'Dúvida no módulo 2')
                ->type('content', 'Conteúdo original da dúvida.')
                ->click('@new-topic-submit')
                ->waitForText('Dúvida no módulo 2')
                ->assertSee('Dúvida no módulo 2');
        });

        $topic = ForumTopic::query()->where('course_id', $course->id)->firstOrFail();

        $this->browse(function (Browser $browser) use ($student, $course, $topic): void {
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@new-reply-form')
                ->type('content', 'Minha resposta ao tópico.')
                ->click('@new-reply-submit')
                ->waitForText('Minha resposta ao tópico.');
        });

        $this->assertDatabaseHas('forum_replies', ['topic_id' => $topic->id, 'content' => 'Minha resposta ao tópico.']);
    }

    public function test_student_reports_a_topic_and_the_gestor_reviews_and_pins_it(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico a ser denunciado',
            // The moderation queue (`forum.moderation.index`) renders each
            // report's postable `content` (not `title`) as its preview
            // excerpt — set explicitly so `waitForText`/`assertSee` below
            // find it there.
            'content' => 'Tópico a ser denunciado',
            'is_pinned' => false,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $topic): void {
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@report-topic-'.$topic->id)
                ->click('@report-topic-'.$topic->id)
                ->waitFor('@report-form')
                ->type('reason', 'Conteúdo inadequado para o curso.')
                ->click('@report-submit')
                ->waitForText('Denúncia enviada');
        });

        $this->assertDatabaseHas('forum_reports', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'status' => 'pending',
        ]);

        $this->browse(function (Browser $browser) use ($gestor, $course, $topic): void {
            $browser->loginAs($gestor)
                ->visit(route('forum-moderation.index'))
                ->waitForText('Tópico a ser denunciado')
                ->assertSee('Tópico a ser denunciado')
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@pin-topic-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitFor('@pinned-badge-'.$topic->id)
                // `x-ui.badge`'s `text-transform: uppercase` style means
                // Selenium's rendered-text `assertSee` sees "FIXADO", not
                // the "Fixado" literal in the Blade source.
                ->assertSee('FIXADO');
        });

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => true]);
    }

    public function test_the_edit_history_modal_shows_previous_versions_of_a_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico com histórico de edição',
            'content' => 'Conteúdo atual do tópico.',
            // `_edit-history-modal.blade.php` only renders the badge/modal
            // when `$topic->edited_at` is present (`forum/show.blade.php`
            // passes `'editedAt' => $topic->edited_at`) — there is no UI to
            // edit a topic, so the history row and this timestamp are
            // written directly, mirroring `EditForumPostAction`'s fields.
            'edited_at' => now(),
        ]);

        $edit = ForumPostEdit::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $student->id,
            'previous_content' => 'Conteúdo original antes da edição.',
            'edited_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $topic, $edit): void {
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->click('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->waitFor('@edit-history-entry-'.$edit->id)
                ->assertSee('Conteúdo original antes da edição.');
        });
    }

    /**
     * UC15 — the real end-to-end edit flow (as opposed to
     * `test_the_edit_history_modal_shows_previous_versions_of_a_topic`,
     * which seeds `forum_post_edits` directly because no UI existed for
     * it): the topic's author opens `forum.edit`, changes the content,
     * saves, sees the new content on `forum.show`, and the "ver
     * histórico" modal shows the pre-edit content.
     */
    public function test_an_author_can_edit_a_topic_and_the_history_records_the_previous_version(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico original',
            'content' => 'Conteúdo original antes da edição.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $topic): void {
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@edit-topic-'.$topic->id)
                ->click('@edit-topic-'.$topic->id)
                ->waitFor('@edit-topic-form')
                ->clear('content')
                ->type('content', 'Conteúdo atualizado após a edição.')
                ->click('@edit-topic-submit')
                ->waitForText('Conteúdo atualizado após a edição.')
                ->assertSee('Conteúdo atualizado após a edição.')
                ->waitFor('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->click('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->waitForText('Conteúdo original antes da edição.')
                ->assertSee('Conteúdo original antes da edição.');
        });

        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $student->id,
            'previous_content' => 'Conteúdo original antes da edição.',
        ]);

        $this->assertDatabaseHas('forum_topics', [
            'id' => $topic->id,
            'content' => 'Conteúdo atualizado após a edição.',
        ]);
    }

    /**
     * UC15 — `ForumTopicPolicy::update()` only grants the post's author
     * or a same-org Gestor/Admin; another enrolled Aluno must neither see
     * the "Editar" button nor be able to reach `forum.edit` directly.
     */
    public function test_a_student_cannot_edit_someone_elses_topic(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);

        $topic = ForumTopic::factory()->for($course)->for($author)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico de outro aluno',
            'content' => 'Conteúdo do outro aluno.',
        ]);

        $this->browse(function (Browser $browser) use ($otherStudent, $course, $topic): void {
            $browser->loginAs($otherStudent)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@topic-content')
                ->assertMissing('@edit-topic-'.$topic->id)
                ->visit(route('forum.edit', [$course, $topic]))
                ->assertSee('403');
        });
    }

    public function test_a_student_who_is_not_enrolled_cannot_access_the_course_forum(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        /** @var User $notEnrolledStudent */
        $notEnrolledStudent = User::factory()->create(['org_id' => $org->id]);
        $notEnrolledStudent->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($notEnrolledStudent, $course): void {
            $browser->loginAs($notEnrolledStudent)
                ->visit(route('forum.index', $course))
                ->assertSee('403');
        });
    }

    /**
     * RN14 — `ForumContentSanitizerService::sanitize()` is a bare
     * `trim(strip_tags($content))`: it strips the `<script>...</script>`
     * *tags* but not the text between them, so submitting
     * `<script>alert('xss')</script>Texto legítimo` through the UI
     * persists `alert('xss')Texto legítimo` (no `<script>` substring, no
     * executable element — just inert text) and Blade's default `{{ }}`
     * escaping renders it as plain text in `@dusk="topic-content"`.
     */
    public function test_script_tags_submitted_through_the_forum_ui_are_sanitized(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('forum.create', $course))
                ->waitFor('@new-topic-form')
                ->type('title', 'Tópico com script malicioso')
                ->type('content', "<script>alert('xss')</script>Texto legítimo")
                ->click('@new-topic-submit')
                ->waitForText('Tópico com script malicioso')
                ->assertSee('Texto legítimo')
                ->assertScript(
                    "document.querySelectorAll('[dusk=\"topic-content\"] script').length",
                    0
                );
        });

        $topic = ForumTopic::query()->where('course_id', $course->id)->firstOrFail();

        $this->assertStringNotContainsString('<script>', $topic->content);
    }
}

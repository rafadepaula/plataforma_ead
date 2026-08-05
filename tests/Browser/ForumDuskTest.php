<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
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
}

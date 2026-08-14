<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-10 §3 — E2E coverage of the forum's full browser-facing flow.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada do Aluno autor (criar tópico → sanitização do conteúdo →
 * responder → editar → histórico de edição) é um método; a jornada de
 * moderação (denunciar → Gestor revisa a fila → fixa) é outro; as duas
 * negativas de acesso ficam isoladas porque exigem outros atores.
 */
class ForumDuskTest extends DuskTestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_student_forum_participation_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            // 1. Criação do tópico, com conteúdo hostil no corpo.
            //
            //    RN14 — `ForumContentSanitizerService::sanitize()` é um
            //    `trim(strip_tags($content))`: remove as TAGS `<script>` mas
            //    não o texto entre elas, então o que persiste é
            //    `alert('xss')Texto legítimo` (inerte), e o escape padrão do
            //    Blade `{{ }}` o renderiza como texto puro.
            $browser->loginAs($student)
                ->visit(route('forum.index', $course))
                ->waitFor('@new-topic-button')
                ->click('@new-topic-button')
                ->waitFor('@new-topic-form')
                ->type('title', 'Dúvida no módulo 2')
                ->type('content', "<script>alert('xss')</script>Conteúdo original da dúvida.")
                ->click('@new-topic-submit')
                ->waitForText('Dúvida no módulo 2')
                ->assertSee('Dúvida no módulo 2')
                ->assertSee('Conteúdo original da dúvida.')
                ->assertScript(
                    "document.querySelectorAll('[dusk=\"topic-content\"] script').length",
                    0
                );

            $topic = ForumTopic::query()->where('course_id', $course->id)->firstOrFail();
            $this->assertStringNotContainsString('<script>', $topic->content);

            // 2. Resposta ao próprio tópico.
            $browser->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@new-reply-form')
                ->type('content', 'Minha resposta ao tópico.')
                ->click('@new-reply-submit')
                ->waitForText('Minha resposta ao tópico.');

            $this->assertDatabaseHas('forum_replies', [
                'topic_id' => $topic->id,
                'content' => 'Minha resposta ao tópico.',
            ]);

            // 3. UC15 — o autor edita o tópico...
            $browser->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@edit-topic-'.$topic->id)
                ->click('@edit-topic-'.$topic->id)
                ->waitFor('@edit-topic-form')
                ->clear('content')
                ->type('content', 'Conteúdo atualizado após a edição.')
                ->click('@edit-topic-submit')
                ->waitForText('Conteúdo atualizado após a edição.')
                ->assertSee('Conteúdo atualizado após a edição.');

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'content' => 'Conteúdo atualizado após a edição.',
            ]);
            $this->assertDatabaseHas('forum_post_edits', [
                'postable_type' => ForumTopic::class,
                'postable_id' => $topic->id,
                'editor_user_id' => $student->id,
            ]);

            // 4. ...e o histórico público mostra a versão anterior.
            $browser->waitFor('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->click('@edit-history-trigger-edit-history-topic-'.$topic->id)
                ->waitForText('Conteúdo original da dúvida.')
                ->assertSee('Conteúdo original da dúvida.');
        });
    }

    public function test_forum_report_and_moderation_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico a ser denunciado',
            // A fila de moderação (`forum.moderation.index`) renderiza o
            // `content` do postable (não o `title`) como excerto.
            'content' => 'Tópico a ser denunciado',
            'is_pinned' => false,
        ]);

        $this->browse(function (Browser $browser) use ($student, $gestor, $course, $topic): void {
            // 1. O Aluno denuncia o tópico.
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@report-topic-'.$topic->id)
                ->click('@report-topic-'.$topic->id)
                ->waitFor('@report-form')
                ->type('reason', 'Conteúdo inadequado para o curso.')
                ->click('@report-submit')
                ->waitForText('Denúncia enviada');

            $this->assertDatabaseHas('forum_reports', [
                'postable_type' => ForumTopic::class,
                'postable_id' => $topic->id,
                'status' => 'pending',
            ]);

            // 2. O Gestor encontra a denúncia na fila de moderação...
            $browser->loginAs($gestor)
                ->visit(route('forum-moderation.index'))
                ->waitForText('Tópico a ser denunciado')
                ->assertSee('Tópico a ser denunciado');

            // 3. ...e fixa o tópico. `x-ui.badge` aplica
            //    `text-transform: uppercase`, então o texto renderizado é
            //    "FIXADO".
            $browser->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@pin-topic-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitFor('@pinned-badge-'.$topic->id)
                ->assertSee('FIXADO');
        });

        $this->assertDatabaseHas('forum_topics', ['id' => $topic->id, 'is_pinned' => true]);
    }

    /**
     * UC15 — `ForumTopicPolicy::update()` only grants the post's author or a
     * same-org Gestor/Admin; another enrolled Aluno must neither see the
     * "Editar" button nor be able to reach `forum.edit` directly.
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

        $this->assertDatabaseHas('forum_topics', [
            'id' => $topic->id,
            'content' => 'Conteúdo do outro aluno.',
        ]);
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
}

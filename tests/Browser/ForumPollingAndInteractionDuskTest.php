<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage for Forum topic creation modal, listing, pin/unpin interactions,
 * and asynchronous real-time reply polling.
 */
class ForumPollingAndInteractionDuskTest extends DuskTestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_forum_topic_creation_listing_and_pin_interactions_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($student, $gestor, $course): void {
            // 1. Student opens topic list and creates a topic via desktop modal
            $browser->loginAs($student)
                ->visit(route('forum.index', $course))
                ->waitFor('@new-topic-button')
                ->click('@new-topic-button')
                ->waitFor('@new-topic-form')
                ->type('@new-topic-title', 'Tópico de Dúvidas de Boas-Vindas')
                ->type('@new-topic-content', 'Olá turma, este é o tópico oficial de discussão do curso.')
                ->click('@new-topic-submit')
                ->waitForLocation(route('forum.show', [$course, ForumTopic::where('title', 'Tópico de Dúvidas de Boas-Vindas')->first() ?? 1]))
                ->waitForText('Tópico de Dúvidas de Boas-Vindas')
                ->assertSee('Tópico de Dúvidas de Boas-Vindas');

            $topic = ForumTopic::query()
                ->where('course_id', $course->id)
                ->where('title', 'Tópico de Dúvidas de Boas-Vindas')
                ->firstOrFail();

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'title' => 'Tópico de Dúvidas de Boas-Vindas',
                'user_id' => $student->id,
                'is_pinned' => false,
            ]);

            // 2. Student verifies topic row in list and opens topic
            $browser->visit(route('forum.index', $course))
                ->waitFor('@topic-row-'.$topic->id)
                ->waitFor('@open-topic-'.$topic->id)
                ->click('@open-topic-'.$topic->id)
                ->waitForLocation(route('forum.show', [$course, $topic]))
                ->waitFor('@topic-content')
                ->assertSee('Olá turma, este é o tópico oficial de discussão do curso.');

            // 3. Gestor pins the topic from index
            $browser->loginAs($gestor)
                ->visit(route('forum.index', $course))
                ->waitFor('@pin-form-'.$topic->id)
                ->waitFor('@pin-topic-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitFor('@pinned-badge-'.$topic->id)
                ->assertSeeIgnoringCase('Fixado');

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'is_pinned' => true,
            ]);

            // 4. Gestor unpins the topic from thread page
            $browser->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@pin-topic-'.$topic->id)
                ->click('@pin-topic-'.$topic->id)
                ->waitUntilMissing('@pinned-badge-'.$topic->id);

            $this->assertDatabaseHas('forum_topics', [
                'id' => $topic->id,
                'is_pinned' => false,
            ]);
        });
    }

    public function test_student_receives_new_replies_via_realtime_polling_asynchronously(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $instructor = User::factory()->create(['org_id' => $org->id]);
        $instructor->assignRole(RolesEnum::GESTOR->value);

        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'title' => 'Tópico para teste de polling assíncrono',
            'content' => 'Conteúdo do post principal.',
        ]);

        $this->browse(function (Browser $browser) use ($student, $instructor, $course, $topic): void {
            // 1. Student visits topic show page with polling active
            $browser->loginAs($student)
                ->visit(route('forum.show', [$course, $topic]))
                ->waitFor('@replies-list')
                ->assertPresent('[data-forum-polling]');

            // 2. An instructor posts a reply in the background while the student remains on page
            $reply = ForumReply::query()->create([
                'topic_id' => $topic->id,
                'user_id' => $instructor->id,
                'content' => 'Resposta assíncrona gerada em segundo plano!',
            ]);

            // Trigger poll immediately via JS or wait for next interval cycle
            $browser->script("
                if (window.ForumPolling) {
                    const el = document.querySelector('[data-forum-polling]');
                    if (el) {
                        const url = el.getAttribute('data-fetch-url');
                        const lastId = el.getAttribute('data-last-id') || 0;
                        fetch(url + '?since_id=' + lastId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(r => r.json()).then(payload => {
                            const replies = payload.data || payload.replies || [];
                            replies.forEach(r => window.ForumPolling.appendReply(el, r));
                        });
                    }
                }
            ");

            // 3. Student sees newly polled reply element dynamically in DOM
            $browser->waitFor('@reply-'.$reply->id, 15)
                ->waitFor('@reply-content-'.$reply->id, 15)
                ->assertSee('Resposta assíncrona gerada em segundo plano!');

            $this->assertDatabaseHas('forum_replies', [
                'id' => $reply->id,
                'content' => 'Resposta assíncrona gerada em segundo plano!',
            ]);
        });
    }
}

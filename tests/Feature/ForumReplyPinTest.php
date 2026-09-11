<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class ForumReplyPinTest extends TestCase
{
    private function setupCourse(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create(['is_published' => true]);

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Lucas']);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        /** @var User $professor */
        $professor = User::factory()->professor()->inOrg($org->id)->create();
        $course->professors()->attach($professor->id);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return [$org, $course, $student, $professor, $gestor];
    }

    public function test_pinned_reply_renders_at_top_of_show_with_chip_and_highlight(): void
    {
        [$org, $course, $student, $professor, $gestor] = $this->setupCourse();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();

        $reply1 = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Primeira resposta (id menor, unpinned)']);
        $reply2 = ForumReply::factory()->for($topic, 'topic')->for($professor)->pinned()->create(['content' => 'Segunda resposta (id medio, pinned)']);
        $reply3 = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Terceira resposta (id maior, unpinned)']);

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();

        // Pinned reply (reply2) is rendered first, before reply1 and reply3
        $content = $response->getContent();
        $pos2 = strpos($content, 'reply-'.$reply2->id);
        $pos1 = strpos($content, 'reply-'.$reply1->id);
        $pos3 = strpos($content, 'reply-'.$reply3->id);

        $this->assertNotFalse($pos2);
        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos3);
        $this->assertTrue($pos2 < $pos1, 'Pinned reply2 must be rendered before unpinned reply1');
        $this->assertTrue($pos1 < $pos3, 'Unpinned reply1 must be rendered before unpinned reply3');

        // Check badge and highlight on reply2
        $response->assertSee('pinned-reply-badge-'.$reply2->id);
        $response->assertSee('Fixado');
        $response->assertSee('forum-post-pinned');

        // Check data-last-id invariant: MAX(id) across all replies
        $response->assertSee('data-last-id="'.$reply3->id.'"', false);
    }

    public function test_data_last_id_remains_global_max_id_when_the_latest_reply_is_pinned(): void
    {
        [$org, $course, $student] = $this->setupCourse();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();

        $reply1 = ForumReply::factory()->for($topic, 'topic')->for($student)->create();
        $reply2 = ForumReply::factory()->for($topic, 'topic')->for($student)->pinned()->create();

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));
        $response->assertOk();

        // data-last-id must be reply2's id, even though reply2 is visually rendered at the top
        $response->assertSee('data-last-id="'.$reply2->id.'"', false);
    }

    public function test_staff_can_toggle_pin_via_post_and_aluno_gets_403(): void
    {
        [$org, $course, $student, $professor, $gestor] = $this->setupCourse();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['is_pinned' => false]);

        // Aluno cannot pin
        $this->actingAs($student)
            ->post(route('forum-replies.pin', [$course, $reply]))
            ->assertForbidden();
        $this->assertFalse($reply->fresh()->is_pinned);

        // Gestor pins
        $response = $this->actingAs($gestor)
            ->post(route('forum-replies.pin', [$course, $reply]));
        $response->assertRedirect(route('forum.show', [$course, $topic]));
        $response->assertSessionHas('success', 'Mensagem fixada.');
        $this->assertTrue($reply->fresh()->is_pinned);

        // Gestor unpins
        $response = $this->actingAs($gestor)
            ->post(route('forum-replies.pin', [$course, $reply]));
        $response->assertRedirect(route('forum.show', [$course, $topic]));
        $response->assertSessionHas('success', 'Mensagem desafixada.');
        $this->assertFalse($reply->fresh()->is_pinned);

        // Professor pins
        $response = $this->actingAs($professor)
            ->post(route('forum-replies.pin', [$course, $reply]));
        $response->assertRedirect(route('forum.show', [$course, $topic]));
        $this->assertTrue($reply->fresh()->is_pinned);
    }

    public function test_unauthorized_user_does_not_see_pin_button_in_ui(): void
    {
        [$org, $course, $student, $professor] = $this->setupCourse();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create();

        // Aluno cannot see pin button
        $alunoResponse = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));
        $alunoResponse->assertOk();
        $alunoResponse->assertDontSee('reply-pin-'.$reply->id);

        // Professor can see pin button
        $profResponse = $this->actingAs($professor)->get(route('forum.show', [$course, $topic]));
        $profResponse->assertOk();
        $profResponse->assertSee('reply-pin-'.$reply->id);
    }
}

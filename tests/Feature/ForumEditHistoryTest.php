<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumPostEdit;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-10 §2.1/RF27 — the public edit-history contract: every edit writes
 * a `forum_post_edits` row with `previous_content`, and the "ver
 * histórico" modal (rendered by
 * `resources/views/forum/partials/_edit-history-modal.blade.php`) is
 * visible to ANY user with access to the topic — not only the post's
 * author or a Gestor/Admin.
 */
class ForumEditHistoryTest extends TestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_editing_a_reply_writes_a_forum_post_edits_row_with_the_pre_edit_content(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Resposta original.']);

        $this->actingAs($student)->put(route('forum-replies.update', [$course, $topic, $reply]), [
            'content' => 'Resposta corrigida.',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_replies', ['id' => $reply->id, 'content' => 'Resposta corrigida.']);
        $this->assertDatabaseHas('forum_post_edits', [
            'postable_type' => ForumReply::class,
            'postable_id' => $reply->id,
            'previous_content' => 'Resposta original.',
        ]);
        $this->assertNotNull($reply->fresh()->edited_at);
    }

    public function test_a_non_author_aluno_cannot_edit_someone_elses_reply(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $course->org_id]);
        $reply = ForumReply::factory()->for($topic, 'topic')->for($author)->create();

        $this->actingAs($otherStudent)->put(route('forum-replies.update', [$course, $topic, $reply]), [
            'content' => 'Tentativa de edição indevida.',
        ])->assertForbidden();
    }

    public function test_any_user_with_topic_access_can_see_the_edit_history_not_only_the_author_or_gestor(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $author = $this->enrolledStudent($course);
        $otherStudent = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $course->org_id, 'content' => 'Conteúdo atual.']);

        ForumPostEdit::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topic->id,
            'editor_user_id' => $author->id,
            'previous_content' => 'Conteúdo anterior à edição.',
            'edited_at' => now(),
        ]);
        $topic->update(['edited_at' => now()]);

        $response = $this->actingAs($otherStudent)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();
        $response->assertSee('Editado em');
        $response->assertSee('Conteúdo anterior à edição.');
    }

    public function test_a_topic_with_no_edits_shows_no_edited_badge(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id, 'edited_at' => null]);

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();
        $response->assertDontSee('Editado em');
    }
}

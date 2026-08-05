<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-10 RF22 — XSS defense-in-depth for forum content: no HTML-purifier
 * package is installed (CLAUDE.md forbids adding dependencies without
 * approval), so the write-path defense is `strip_tags()` via
 * `App\Services\ForumContentSanitizerService` (Bucket 2) applied to every
 * topic/reply store+update, plus Blade's default `{{ }}` escaping at
 * render time (never `{!! !!}` in `resources/views/forum/**`) as a second
 * layer.
 */
class XssSanitizationTest extends TestCase
{
    private function enrolledStudent(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return $student;
    }

    public function test_a_script_tag_in_a_new_topics_content_is_stripped_before_it_is_persisted(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);

        $this->actingAs($student)->post(route('forum.store', $course), [
            'title' => 'Tópico malicioso',
            'content' => '<script>alert("xss")</script>Texto legítimo.',
        ])->assertRedirect();

        // `ForumTopic` carries `OrgScope`; the acting user here is a
        // multi-org Aluno (`org_id === null`), which would zero out a
        // scoped query (see `OrgScope::bootOrgScope()`).
        $topic = ForumTopic::query()->withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();

        $this->assertStringNotContainsString('<script>', $topic->content);
        $this->assertStringContainsString('Texto legítimo.', $topic->content);
    }

    public function test_a_script_tag_in_a_reply_is_stripped_before_it_is_persisted(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        $topic = ForumTopic::factory()->for($course)->for($student)->create(['org_id' => $course->org_id]);

        $this->actingAs($student)->post(route('forum-replies.store', [$course, $topic]), [
            'content' => '<img src=x onerror=alert(1)>Resposta legítima.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('forum_replies', ['content' => '<img src=x onerror=alert(1)>Resposta legítima.']);
        $reply = $topic->replies()->firstOrFail();
        $this->assertStringNotContainsString('<img', $reply->content);
        $this->assertStringContainsString('Resposta legítima.', $reply->content);
    }

    public function test_the_topic_show_page_renders_stored_html_escaped_never_raw(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = $this->enrolledStudent($course);
        // Simulates a row that predates sanitization (e.g. legacy data) to
        // prove the *rendering* layer is also safe, independent of the
        // write-path sanitizer.
        $topic = ForumTopic::factory()->for($course)->for($student)->create([
            'org_id' => $course->org_id,
            'content' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }
}

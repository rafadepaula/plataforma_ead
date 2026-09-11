<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class ForumStaffHighlightsTest extends TestCase
{
    private function setupCourseWithStaffAndStudent(): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create(['is_published' => true]);

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Silva']);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        /** @var User $professor */
        $professor = User::factory()->professor()->inOrg($org->id)->create(['name' => 'Professora Ana']);
        $course->professors()->attach($professor->id);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create(['name' => 'Gestor Carlos']);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return [$org, $course, $student, $professor, $gestor];
    }

    public function test_staff_replies_render_highlight_and_proper_badge_variants(): void
    {
        [$org, $course, $student, $professor, $gestor] = $this->setupCourseWithStaffAndStudent();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();

        $studentReply = ForumReply::factory()->for($topic, 'topic')->for($student)->create(['content' => 'Duvida do aluno']);
        $profReply = ForumReply::factory()->for($topic, 'topic')->for($professor)->create(['content' => 'Resposta do professor']);
        $gestorReply = ForumReply::factory()->for($topic, 'topic')->for($gestor)->create(['content' => 'Aviso do gestor']);

        $response = $this->actingAs($student)->get(route('forum.show', [$course, $topic]));

        $response->assertOk();

        // Aluno reply has no staff highlight
        $response->assertSeeInOrder([
            'reply-'.$studentReply->id,
            'ds-muted',
            'Aluno',
        ]);
        $this->assertStringNotContainsString('reply-'.$studentReply->id.'" data-reply-id="'.$studentReply->id.'" class="forum-reply card mb-2 forum-post-staff', $response->getContent());

        // Professor reply has staff highlight and info badge
        $response->assertSee('forum-post-staff');
        $response->assertSee('ds-tone-info');
        $response->assertSee('Professor');

        // Gestor reply has primary badge
        $response->assertSee('ds-tone-primary');
        $response->assertSee('Gestor');
    }

    public function test_professor_topic_renders_highlight_in_index_and_show(): void
    {
        [$org, $course, $student, $professor] = $this->setupCourseWithStaffAndStudent();

        $profTopic = ForumTopic::factory()->for($course)->for($professor)->create([
            'org_id' => $org->id,
            'title' => 'Anuncio importante da professora',
        ]);

        // Index screen
        $indexResponse = $this->actingAs($student)->get(route('forum.index', $course));
        $indexResponse->assertOk();
        $indexResponse->assertSee('forum-post-staff');
        $indexResponse->assertSee('ds-tone-info');
        $indexResponse->assertSee('Professor');
        $indexResponse->assertSee('Anuncio importante da professora');

        // Show screen
        $showResponse = $this->actingAs($student)->get(route('forum.show', [$course, $profTopic]));
        $showResponse->assertOk();
        $showResponse->assertSee('forum-post-staff');
        $showResponse->assertSee('ds-tone-info');
        $showResponse->assertSee('Professor');
    }

    public function test_fetch_new_carries_is_staff_boolean_in_polling_contract(): void
    {
        [$org, $course, $student, $professor, $gestor] = $this->setupCourseWithStaffAndStudent();

        $topic = ForumTopic::factory()->for($course)->for($student)->inOrg($org->id)->create();

        $replyStudent = ForumReply::factory()->for($topic, 'topic')->for($student)->create();
        $replyProf = ForumReply::factory()->for($topic, 'topic')->for($professor)->create();
        $replyGestor = ForumReply::factory()->for($topic, 'topic')->for($gestor)->create();

        $response = $this->actingAs($student)->getJson(route('forum-replies.fetch', [$course, $topic]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $replyStudent->id);
        $response->assertJsonPath('data.0.is_staff', false);
        $response->assertJsonPath('data.1.id', $replyProf->id);
        $response->assertJsonPath('data.1.is_staff', true);
        $response->assertJsonPath('data.1.role_label', 'Professor');
        $response->assertJsonPath('data.2.id', $replyGestor->id);
        $response->assertJsonPath('data.2.is_staff', true);
        $response->assertJsonPath('data.2.role_label', 'Gestor');
    }
}

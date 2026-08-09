<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * SPEC-06 Bucket 2 — HTTP-level wiring for the public `/convite/{token}`
 * flow and the Gestor/Admin Invitation Link + Enrollment panels. Full
 * end-to-end coverage (multi-org, race conditions, adaptive form) lives in
 * Bucket 3's `SmartInvitationTest`/`EnrollmentManagementTest`; this file
 * exercises the controllers/routes/form requests added in this bucket.
 */
class InvitationHttpTest extends TestCase
{
    public function test_show_renders_a_usable_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $this->get(route('invitation.show', $invitationLink->token))
            ->assertOk()
            ->assertViewIs('convite.show');
    }

    public function test_show_rejects_an_expired_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $invitationLink = InvitationLink::factory()->expired()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $this->get(route('invitation.show', $invitationLink->token))
            ->assertStatus(404);
    }

    public function test_show_rejects_a_revoked_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $invitationLink = InvitationLink::factory()->revoked()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $this->get(route('invitation.show', $invitationLink->token))
            ->assertStatus(404);
    }

    public function test_show_rejects_an_unknown_token(): void
    {
        $this->get(route('invitation.show', 'does-not-exist'))
            ->assertStatus(404);
    }

    public function test_check_email_reports_existing_and_new_emails(): void
    {
        User::factory()->create(['email' => 'ja-cadastrado@example.com']);

        $this->postJson(route('invitation.check-email'), ['email' => 'ja-cadastrado@example.com'])
            ->assertOk()
            ->assertJson(['exists' => true]);

        $this->postJson(route('invitation.check-email'), ['email' => 'novo@example.com'])
            ->assertOk()
            ->assertJson(['exists' => false]);
    }

    public function test_store_creates_a_new_user_and_enrolls_them(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $response = $this->post(route('invitation.store', $invitationLink->token), [
            'name' => 'Aluno Novo',
            'email' => 'aluno-novo@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'aluno-novo@example.com']);
        $user = User::where('email', 'aluno-novo@example.com')->first();
        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertSame($user->id, Auth::id());
        $this->assertSame(1, $invitationLink->fresh()->current_uses);
    }

    public function test_store_rejects_a_checksum_invalid_cpf_for_a_new_user(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $this->post(route('invitation.store', $invitationLink->token), [
            'name' => 'Aluno Novo',
            'email' => 'aluno-cpf-invalido@example.com',
            'cpf' => '111.444.777-36',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('cpf');

        $this->assertFalse(User::where('email', 'aluno-cpf-invalido@example.com')->exists());
        $this->assertGuest();
    }

    public function test_store_authenticates_an_existing_user_without_duplicating_the_account(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);
        $existing = User::factory()->create(['email' => 'existente@example.com', 'password' => bcrypt('senha-correta')]);

        $response = $this->post(route('invitation.store', $invitationLink->token), [
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
        $this->assertSame($existing->id, Auth::id());
    }

    public function test_store_rejects_wrong_password_for_existing_user(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);
        User::factory()->create(['email' => 'existente2@example.com', 'password' => bcrypt('senha-correta')]);

        $this->post(route('invitation.store', $invitationLink->token), [
            'email' => 'existente2@example.com',
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_invitation_link_controller_index_create_store_destroy_flow(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->get(route('courses.invitation-links.index', $course))->assertOk();
        $this->get(route('courses.invitation-links.create', $course))->assertOk();

        $this->post(route('courses.invitation-links.store', $course), [
            'max_uses' => 5,
        ])->assertRedirect(route('courses.invitation-links.index', $course));

        $invitationLink = InvitationLink::where('course_id', $course->id)->firstOrFail();
        $this->assertSame($org->id, $invitationLink->org_id);

        $this->delete(route('invitation-links.destroy', $invitationLink))
            ->assertRedirect(route('courses.invitation-links.index', $course));

        $this->assertNotNull($invitationLink->fresh()->revoked_at);
    }

    public function test_gestor_from_another_org_cannot_manage_invitation_links(): void
    {
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // `Course` carries its own `OrgScope`, so a cross-org `{course}`
        // route parameter never resolves via implicit binding — the
        // request 404s before `InvitationLinkPolicy` is even reached
        // (mirrors `CourseController`'s own cross-org behavior).
        $this->get(route('courses.invitation-links.index', $course))->assertNotFound();
    }

    public function test_gestor_from_another_org_cannot_revoke_invitation_link(): void
    {
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $otherOrg->id,
            'created_by' => User::factory()->create(['org_id' => $otherOrg->id])->id,
        ]);
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // `InvitationLink` carries its own `OrgScope`, so a cross-org
        // `{invitation_link}` route parameter never resolves via implicit
        // binding — the request 404s before `InvitationLinkPolicy` is even
        // reached (mirrors the `courses.invitation-links.index` case above).
        $this->delete(route('invitation-links.destroy', $invitationLink))->assertNotFound();

        $this->assertNull($invitationLink->fresh()->revoked_at);
    }

    public function test_revoking_an_invitation_link_of_a_soft_deleted_course_redirects_without_crashing(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ]);

        $course->delete();

        $this->delete(route('invitation-links.destroy', $invitationLink))
            ->assertRedirect(route('courses.invitation-links.index', $course));

        $this->assertNotNull($invitationLink->fresh()->revoked_at);
    }

    public function test_enrollment_controller_index_store_destroy_flow(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->get(route('courses.enrollments.index', $course))->assertOk();

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->delete(route('courses.enrollments.destroy', [$course, $student]))
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_double_enroll_an_already_active_student(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertSessionHasErrors('user_id');
    }
}

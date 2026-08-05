<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-06 RF03/RN09 — full HTTP-level coverage of the public, unauthenticated
 * Smart Invitation flow (`/convite/{token}`, `/convite/check-email`): link
 * state guards (expired/exhausted/revoked/unknown), the adaptive
 * check-email AJAX lookup, and `store()`'s two branches (new account vs.
 * existing account) via `ProcessSmartInvitationAction`.
 */
class SmartInvitationTest extends TestCase
{
    private function makeInvitationLink(array $attributes = []): InvitationLink
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $creator = User::factory()->create(['org_id' => $org->id]);
        $creator->assignRole(RolesEnum::GESTOR->value);

        return InvitationLink::factory()->create(array_merge([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $creator->id,
        ], $attributes));
    }

    public function test_show_renders_the_form_for_a_valid_link(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $this->get('/convite/'.$invitationLink->token)
            ->assertOk()
            ->assertViewIs('convite.show')
            ->assertViewHas('invitationLink', fn (InvitationLink $link) => $link->id === $invitationLink->id);
    }

    public function test_show_returns_404_for_an_expired_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['expires_at' => now()->subDay()]);

        $this->get('/convite/'.$invitationLink->token)->assertNotFound();
    }

    public function test_show_returns_404_for_an_exhausted_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['max_uses' => 1, 'current_uses' => 1]);

        $this->get('/convite/'.$invitationLink->token)->assertNotFound();
    }

    public function test_show_returns_404_for_a_revoked_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['revoked_at' => now()]);

        $this->get('/convite/'.$invitationLink->token)->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_token(): void
    {
        $this->get('/convite/token-que-nao-existe')->assertNotFound();
    }

    public function test_check_email_reports_true_for_an_existing_email(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $this->postJson('/convite/check-email', ['email' => 'existente@example.com'])
            ->assertOk()
            ->assertExactJson(['exists' => true]);
    }

    public function test_check_email_reports_false_for_a_new_email(): void
    {
        $this->postJson('/convite/check-email', ['email' => 'novo@example.com'])
            ->assertOk()
            ->assertExactJson(['exists' => false]);
    }

    public function test_check_email_validates_the_email_field(): void
    {
        $this->post('/convite/check-email', ['email' => 'nao-e-um-email'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_store_creates_a_new_user_enrolls_them_and_logs_them_in(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $response = $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno Novo',
            'email' => 'novo@example.com',
            'cpf' => '12345678900',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();

        $user = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(RolesEnum::ALUNO->value));
        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $invitationLink->course_id,
            'status' => 'active',
        ]);
        $this->assertSame(1, $invitationLink->fresh()->current_uses);
    }

    public function test_store_authenticates_an_existing_user_and_enrolls_them_without_duplicating_the_account(): void
    {
        $invitationLink = $this->makeInvitationLink();
        $existing = User::factory()->aluno()->create([
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $response = $this->post('/convite/'.$invitationLink->token, [
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
        $this->assertDatabaseHas('course_user', [
            'user_id' => $existing->id,
            'course_id' => $invitationLink->course_id,
            'status' => 'active',
        ]);
    }

    public function test_store_rejects_an_existing_email_with_the_wrong_password(): void
    {
        $invitationLink = $this->makeInvitationLink();
        User::factory()->aluno()->create([
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $this->from('/convite/'.$invitationLink->token)
            ->post('/convite/'.$invitationLink->token, [
                'email' => 'existente@example.com',
                'password' => 'senha-errada',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('course_user', 0);
        $this->assertSame(0, $invitationLink->fresh()->current_uses);
    }

    public function test_store_requires_name_cpf_and_password_confirmation_for_a_new_email(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $this->post('/convite/'.$invitationLink->token, [
            'email' => 'novo@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors(['name', 'password']);

        $this->assertGuest();
    }

    public function test_store_fails_once_max_uses_has_been_reached(): void
    {
        $invitationLink = $this->makeInvitationLink(['max_uses' => 1, 'current_uses' => 0]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Primeiro',
            'email' => 'primeiro@example.com',
            'cpf' => '12345678900',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertSame(1, $invitationLink->fresh()->current_uses);

        // The `guest` middleware would otherwise redirect this second
        // request away since the first one auto-logged the caller in —
        // log back out to simulate a genuinely separate second visitor.
        $this->post(route('logout'));

        // The link is now exhausted — a second consumption attempt (the
        // sequential proxy for the "two concurrent requests at exactly
        // max_uses" race the `lockForUpdate` re-check guards against, see
        // `ProcessSmartInvitationActionTest` for the transaction-level
        // coverage) must be rejected, not silently over-consume the link.
        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Segundo',
            'email' => 'segundo@example.com',
            'cpf' => '98765432100',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'segundo@example.com']);
        $this->assertSame(1, $invitationLink->fresh()->current_uses);
    }

    public function test_store_returns_404_for_a_revoked_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['revoked_at' => now()]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'aluno@example.com',
            'cpf' => '12345678900',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function test_an_authenticated_user_cannot_reach_the_invitation_show_route(): void
    {
        $invitationLink = $this->makeInvitationLink();
        $this->actingAs(User::factory()->create());

        $this->get('/convite/'.$invitationLink->token)->assertRedirect();
    }

    public function test_show_returns_404_when_the_linked_course_is_unpublished(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => false]);
        $invitationLink = $this->makeInvitationLink(['course_id' => $course->id]);

        $this->get('/convite/'.$invitationLink->token)->assertNotFound();
    }

    public function test_show_returns_404_when_the_linked_course_is_soft_deleted(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $invitationLink = $this->makeInvitationLink(['course_id' => $course->id]);
        $course->delete();

        $this->get('/convite/'.$invitationLink->token)->assertNotFound();
    }

    public function test_store_returns_404_when_the_linked_course_is_unpublished(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => false]);
        $invitationLink = $this->makeInvitationLink(['course_id' => $course->id]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'aluno-curso-nao-publicado@example.com',
            'cpf' => '12345678900',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'aluno-curso-nao-publicado@example.com']);
    }

    public function test_store_rejects_an_existing_staff_email_from_using_the_self_service_flow(): void
    {
        $invitationLink = $this->makeInvitationLink();
        $gestor = User::factory()->create([
            'email' => 'gestor@example.com',
            'password' => 'senha-correta',
        ]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->from('/convite/'.$invitationLink->token)
            ->post('/convite/'.$invitationLink->token, [
                'email' => 'gestor@example.com',
                'password' => 'senha-correta',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('course_user', 0);
        $this->assertSame(0, $invitationLink->fresh()->current_uses);
    }
}

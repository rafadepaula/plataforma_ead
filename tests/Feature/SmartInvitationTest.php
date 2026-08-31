<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\InvitationLinkInvalidException;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * full HTTP-level coverage of the public, unauthenticated
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

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Este convite expirou.');
    }

    public function test_show_returns_404_for_an_exhausted_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['max_uses' => 1, 'current_uses' => 1]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Limite de vagas atingido.');
    }

    public function test_show_returns_404_for_a_revoked_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['revoked_at' => now()]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Este convite foi cancelado.');
    }

    public function test_show_returns_404_for_an_unknown_token(): void
    {
        $this->get('/convite/token-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Este convite não foi encontrado.');
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
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
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
            'consent' => true,
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
                'consent' => true,
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('course_user', 0);
        $this->assertSame(0, $invitationLink->fresh()->current_uses);
    }

    /**
     * Without JavaScript (or when `/convite/check-email` fails and the form
     * degrades to the new-account state) an already-registered student posts
     * the full registration payload — their own name/CPF/confirmação — and
     * must still be authenticated and enrolled instead of being rejected by
     * the CPF uniqueness rule.
     */
    public function test_store_enrolls_an_existing_user_that_also_posts_the_registration_fields(): void
    {
        $invitationLink = $this->makeInvitationLink();
        $existing = User::factory()->aluno()->create([
            'name' => 'Aluno Existente',
            'email' => 'existente@example.com',
            'cpf' => '12345678909',
            'password' => 'senha-correta',
        ]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno Existente',
            'email' => 'existente@example.com',
            'cpf' => '12345678909',
            'password' => 'senha-correta',
            'password_confirmation' => 'senha-correta',
            'consent' => true,
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
        $this->assertDatabaseHas('course_user', [
            'user_id' => $existing->id,
            'course_id' => $invitationLink->course_id,
            'status' => 'active',
        ]);
    }

    /**
     * A deactivated account is blocked at `/login`; the invitation flow must
     * not become a way around that guard.
     */
    public function test_store_rejects_an_existing_account_that_is_inactive(): void
    {
        $invitationLink = $this->makeInvitationLink();
        User::factory()->aluno()->inactive()->create([
            'email' => 'inativo@example.com',
            'password' => 'senha-correta',
        ]);

        $this->from('/convite/'.$invitationLink->token)
            ->post('/convite/'.$invitationLink->token, [
                'email' => 'inativo@example.com',
                'password' => 'senha-correta',
                'consent' => true,
            ])
            ->assertSessionHasErrors('email');

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
            'consent' => true,
        ])->assertSessionHasErrors(['name', 'cpf', 'password']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
        $this->assertDatabaseCount('course_user', 0);
        $this->assertSame(0, $invitationLink->fresh()->current_uses);
    }

    public function test_store_requires_consent_for_a_new_email(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno Sem Consentimento',
            'email' => 'sem-consentimento@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('consent');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'sem-consentimento@example.com']);
    }

    public function test_store_rejects_a_false_consent_value_for_a_new_email(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno Consentimento Falso',
            'email' => 'consentimento-falso@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => false,
        ])->assertSessionHasErrors('consent');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'consentimento-falso@example.com']);
    }

    public function test_store_fails_once_max_uses_has_been_reached(): void
    {
        $invitationLink = $this->makeInvitationLink(['max_uses' => 1, 'current_uses' => 0]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Primeiro',
            'email' => 'primeiro@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
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
            'consent' => true,
        ])
            ->assertNotFound()
            ->assertSee('Limite de vagas atingido.');

        $this->assertDatabaseMissing('users', ['email' => 'segundo@example.com']);
        $this->assertSame(1, $invitationLink->fresh()->current_uses);
    }

    /**
     * CPF is unique at the database level, so a CPF already taken by another
     * account must fail validation instead of crashing the public form.
     */
    public function test_store_rejects_a_cpf_already_used_by_another_account(): void
    {
        $invitationLink = $this->makeInvitationLink();

        User::factory()->create(['cpf' => '12345678909']);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'novo@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
        ])->assertSessionHasErrors('cpf');

        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
    }

    /**
     * The mask is stripped before validation, so the same document typed as
     * `000.000.000-00` cannot slip past the uniqueness rule (nor the
     * `users.cpf` unique index) and open a second account for one person.
     */
    public function test_store_rejects_a_masked_cpf_already_used_by_another_account(): void
    {
        $invitationLink = $this->makeInvitationLink();

        User::factory()->create(['cpf' => '12345678909']);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'novo@example.com',
            'cpf' => '123.456.789-09',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
        ])->assertSessionHasErrors('cpf');

        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
        $this->assertSame(1, User::where('cpf', '12345678909')->count());
    }

    /**
     * A masked CPF for a brand-new account is accepted, but persisted
     * digits-only, so every later comparison sees one canonical value.
     */
    public function test_store_persists_a_masked_cpf_as_digits_only(): void
    {
        $invitationLink = $this->makeInvitationLink();

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'mascarado@example.com',
            'cpf' => '123.456.789-09',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'mascarado@example.com',
            'cpf' => '12345678909',
        ]);
    }

    public function test_store_returns_404_for_a_revoked_link(): void
    {
        $invitationLink = $this->makeInvitationLink(['revoked_at' => now()]);

        $this->post('/convite/'.$invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'aluno@example.com',
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
        ])
            ->assertNotFound()
            ->assertSee('Este convite foi cancelado.');
    }

    /**
     * The guest panel owns the only `h1`; `convite.show` must render its
     * title as an `h2` so the public screen keeps one top-level heading.
     */
    public function test_show_renders_exactly_one_top_level_heading(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $organization->id,
            'is_published' => true,
            'title' => 'Gestão de Projetos Sociais',
        ]);
        $invitationLink = $this->makeInvitationLink([
            'org_id' => $organization->id,
            'course_id' => $course->id,
        ]);

        $html = $this->get('/convite/'.$invitationLink->token)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Matrícula em Gestão de Projetos Sociais', $html);
    }

    /**
     * A reason outside the whitelist must degrade to the not-found copy
     * instead of blowing up the global handler with an undefined index.
     */
    public function test_unknown_reason_degrades_to_the_not_found_message(): void
    {
        $exception = new InvitationLinkInvalidException('Convite indisponível.', 'suspended');

        $this->assertSame('not_found', $exception->reason());
        $this->assertSame('Este convite não foi encontrado.', $exception->userMessage());
    }

    /**
     * The course title is a required element of the enrollment screen: the
     * visitor must know which course the link enrolls them into.
     */
    public function test_show_displays_the_course_title(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $organization->id,
            'is_published' => true,
            'title' => 'Curso Sonda XYZ',
        ]);
        $invitationLink = $this->makeInvitationLink([
            'org_id' => $organization->id,
            'course_id' => $course->id,
        ]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertOk()
            ->assertSee('Curso Sonda XYZ');
    }

    /**
     * A visitor arriving from an invitation has no tenant session, so the
     * brand must come from the link's own organization — the student has to
     * see who invited them, not the platform name.
     */
    public function test_show_displays_the_inviting_organization_brand(): void
    {
        $organization = Organization::factory()->create(['name' => 'Instituto Ponte Verde']);
        $course = Course::factory()->create(['org_id' => $organization->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink([
            'org_id' => $organization->id,
            'course_id' => $course->id,
        ]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertOk()
            ->assertSee('Instituto Ponte Verde');
    }

    /**
     * A staff address is refused on the field itself (`.is-invalid` plus the
     * message), never as a page-level red alert.
     */
    public function test_store_marks_the_email_field_invalid_when_a_staff_account_is_used(): void
    {
        $invitationLink = $this->makeInvitationLink();
        $gestor = User::factory()->create([
            'email' => 'gestor-invalido@example.com',
            'password' => 'senha-correta',
        ]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $html = $this->from('/convite/'.$invitationLink->token)
            ->followingRedirects()
            ->post('/convite/'.$invitationLink->token, [
                'email' => 'gestor-invalido@example.com',
                'password' => 'senha-correta',
                'consent' => true,
            ])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*dusk="invitation-email")(?=[^>]*is-invalid)[^>]*>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/dusk="error-email"[^>]*>.*?Este e-mail pertence a uma conta da equipe/s',
            $html
        );
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

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Este convite não está mais disponível.');
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
            'cpf' => '12345678909',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
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
                'consent' => true,
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('course_user', 0);
        $this->assertSame(0, $invitationLink->fresh()->current_uses);
    }

    public function test_show_returns_the_reason_message_as_json_for_an_api_client(): void
    {
        $invitationLink = $this->makeInvitationLink(['expires_at' => now()->subDay()]);

        $this->getJson('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Este convite expirou.']);
    }

    public function test_a_revoked_link_reports_revocation_even_when_it_is_also_expired(): void
    {
        $invitationLink = $this->makeInvitationLink([
            'revoked_at' => now(),
            'expires_at' => now()->subDay(),
            'max_uses' => 1,
            'current_uses' => 1,
        ]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Este convite foi cancelado.');
    }

    public function test_an_expired_link_reports_expiry_even_when_it_is_also_exhausted(): void
    {
        $invitationLink = $this->makeInvitationLink([
            'expires_at' => now()->subDay(),
            'max_uses' => 1,
            'current_uses' => 1,
        ]);

        $this->get('/convite/'.$invitationLink->token)
            ->assertNotFound()
            ->assertSee('Este convite expirou.');
    }
}

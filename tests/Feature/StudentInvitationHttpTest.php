<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP coverage of the per-student unique invitation: the public
 * `/convite/{token}` finalize flow (identity comes from the token and is
 * immutable; only password + consent are typed) and the Gestor's
 * issue/regenerate/revoke endpoints behind the student directory.
 */
class StudentInvitationHttpTest extends TestCase
{
    private function makePendingStudent(Organization $org, array $userAttributes = []): User
    {
        $student = User::factory()->aluno()->create($userAttributes);
        Credential::factory()->pending()->create(['user_id' => $student->id, 'org_id' => $org->id]);

        return $student;
    }

    private function makeInvitation(Organization $org, User $student, array $attributes = []): StudentInvitation
    {
        return StudentInvitation::factory()->create(array_merge([
            'org_id' => $org->id,
            'user_id' => $student->id,
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // Public: GET /convite/{token}
    // ------------------------------------------------------------------

    public function test_show_renders_the_identity_readonly_from_the_token(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org, ['name' => 'Ana Silva', 'email' => 'ana@acme.test']);
        $invitation = $this->makeInvitation($org, $student);

        $this->onHost($org->host)
            ->get("/convite/{$invitation->token}")
            ->assertOk()
            ->assertSee('Olá, Ana!')
            ->assertSee('escolher a sua senha')
            ->assertSee('Seu e-mail de acesso')
            ->assertSee('ana@acme.test')
            ->assertSee('Ana Silva')
            ->assertSee('readonly', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_show_shows_the_reason_copy_for_every_unusable_state(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);

        $expired = $this->makeInvitation($org, $student, ['expires_at' => now()->subDay()]);
        $revoked = $this->makeInvitation($org, $student, ['revoked_at' => now()]);
        $used = $this->makeInvitation($org, $student, ['used_at' => now()]);

        $this->onHost($org->host)
            ->get("/convite/{$expired->token}")
            ->assertNotFound()->assertSee('Este convite expirou.');
        $this->get("/convite/{$revoked->token}")->assertNotFound()->assertSee('Este convite foi cancelado.');
        $this->get("/convite/{$used->token}")->assertNotFound()->assertSee('Este convite já foi utilizado.');
        $this->get('/convite/token-desconhecido')->assertNotFound()->assertSee('Este convite não foi encontrado.');
    }

    // ------------------------------------------------------------------
    // Public: POST /convite/{token}
    // ------------------------------------------------------------------

    public function test_store_finalizes_the_account_logs_in_and_marks_the_token_used(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student);

        $this->onHost($org->host)
            ->post("/convite/{$invitation->token}", [
                'password' => 'senha-forte-123',
                'password_confirmation' => 'senha-forte-123',
                'consent' => '1',
            ])
            ->assertRedirect(route('student.courses.index'));

        $credential = $student->fresh()->credentialFor($org);
        $this->assertSame('active', $credential->status);
        $this->assertTrue(Hash::check('senha-forte-123', $credential->password));
        $this->assertNotNull($invitation->fresh()->used_at);
        $this->assertSame($student->id, auth()->id());
    }

    /**
     * The token IS the identity: even a hand-crafted `email` field in the
     * POST cannot redirect the redemption to another account — the field
     * is not validated, not read, and the pre-registered user is untouched.
     */
    public function test_store_ignores_a_forged_email_field(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org, ['email' => 'ana@acme.test']);
        $other = $this->makePendingStudent($org, ['email' => 'outra@acme.test']);
        $invitation = $this->makeInvitation($org, $student);

        $this->onHost($org->host)
            ->post("/convite/{$invitation->token}", [
                'email' => $other->email,
                'password' => 'senha-forte-123',
                'password_confirmation' => 'senha-forte-123',
                'consent' => '1',
            ])
            ->assertRedirect(route('student.courses.index'));

        $this->assertSame('ana@acme.test', $student->fresh()->email);
        $this->assertSame('ana@acme.test', auth()->user()->email);
        $this->assertNotNull($invitation->fresh()->used_at);
        // a conta do outro aluno ficou intacta: pendente e com a senha
        // aleatória de origem, nunca acessada pela redenção alheia.
        $this->assertSame('pending', $other->fresh()->credentialFor($org)->status);
    }

    public function test_store_validates_password_and_consent(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student);

        $this->onHost($org->host)
            ->post("/convite/{$invitation->token}", [
                'password' => 'curta',
                'password_confirmation' => 'diferente',
            ])
            ->assertSessionHasErrors(['password', 'consent']);

        $this->assertSame('pending', $student->fresh()->credentialFor($org)->status);
        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_store_rejects_a_gestor_deactivated_account_without_touching_it(): void
    {
        $org = Organization::factory()->create();
        $student = User::factory()->aluno()->create();
        Credential::factory()->inactive()->create(['user_id' => $student->id, 'org_id' => $org->id]);
        $invitation = $this->makeInvitation($org, $student);

        $this->onHost($org->host)
            ->from('/login')
            ->post("/convite/{$invitation->token}", [
                'password' => 'senha-forte-123',
                'password_confirmation' => 'senha-forte-123',
                'consent' => '1',
            ])
            ->assertNotFound()
            ->assertSee('Esta conta está inativa. Procure o gestor da sua organização.');

        $this->assertSame('inactive', $student->fresh()->credentialFor($org)->status);
        $this->assertNull($invitation->fresh()->used_at);
        $this->assertGuest();
    }

    public function test_store_consumes_a_used_token_as_not_found(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student, ['used_at' => now()]);

        $this->onHost($org->host)
            ->post("/convite/{$invitation->token}", [
                'password' => 'senha-forte-123',
                'password_confirmation' => 'senha-forte-123',
                'consent' => '1',
            ])
            ->assertNotFound()->assertSee('Este convite já foi utilizado.');
    }

    // ------------------------------------------------------------------
    // Gestor endpoints
    // ------------------------------------------------------------------

    public function test_issue_is_get_or_create_so_copying_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $first = $this->postJson(route('gestor.students.invitations.issue', $student))
            ->assertOk()
            ->assertJsonStructure(['url']);

        $second = $this->postJson(route('gestor.students.invitations.issue', $student))
            ->assertOk();

        $this->assertSame(
            $first->json('url'),
            $second->json('url'),
            'copying twice must hand back the same live token'
        );
        $this->assertSame(1, StudentInvitation::query()->where('user_id', $student->id)->count());
        $this->assertStringContainsString('/convite/', $first->json('url'));
    }

    public function test_regenerate_revokes_the_live_token_and_issues_another(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $originalUrl = $this->postJson(route('gestor.students.invitations.issue', $student))->json('url');

        $this->post(route('gestor.students.invitations.regenerate', $student))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('invitation_url');

        $invitations = StudentInvitation::query()->where('user_id', $student->id)->get();
        $this->assertSame(2, $invitations->count());
        $this->assertSame(1, $invitations->whereNotNull('revoked_at')->count());
        $this->assertSame(1, $invitations->whereNull('revoked_at')->whereNull('used_at')->count());

        // The old link stops working immediately; the new one renders.
        // Quem abre o link é o aluno (guest), não o gestor autenticado.
        auth()->logout();
        $originalToken = Str::afterLast($originalUrl, '/');
        $newToken = Str::afterLast(session('invitation_url'), '/');
        $this->assertNotSame($originalToken, $newToken);
        $this->onHost($org->host)
            ->get("/convite/{$originalToken}")
            ->assertNotFound()->assertSee('Este convite foi cancelado.');
    }

    public function test_destroy_revokes_without_replacing(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->postJson(route('gestor.students.invitations.issue', $student))->assertOk();

        $this->delete(route('gestor.students.invitations.destroy', $student))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            1,
            StudentInvitation::query()->where('user_id', $student->id)->whereNotNull('revoked_at')->count()
        );
    }

    public function test_cross_org_gestor_cannot_issue_an_invitation(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->actingAsOrgUser($otherOrg, RolesEnum::GESTOR->value);

        $this->postJson(route('gestor.students.invitations.issue', $student))
            ->assertForbidden();

        $this->assertSame(0, StudentInvitation::query()->count());
    }

    public function test_a_student_role_can_never_issue_invitations(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->post(route('gestor.students.invitations.issue', $student))
            ->assertForbidden();

        $this->assertSame(0, StudentInvitation::query()->count());
    }

    public function test_deleting_the_student_cascades_their_invitations(): void
    {
        $org = Organization::factory()->create();
        $student = $this->makePendingStudent($org);
        $this->makeInvitation($org, $student);

        $student->delete();

        $this->assertSame(0, StudentInvitation::query()->count());
    }
}

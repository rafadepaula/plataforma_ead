<?php

namespace Tests\Unit\Actions;

use App\Actions\RedeemStudentInvitationAction;
use App\Enums\Permissions\RolesEnum;
use App\Exceptions\InvitationInvalidException;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * unit coverage for `RedeemStudentInvitationAction`: the
 * `/convite/{token}` finalization transaction — activation of the
 * pending account, single-use enforcement, and the guards that keep the
 * token from ever becoming a staff-password reset or a deactivation
 * override.
 */
class RedeemStudentInvitationActionTest extends TestCase
{
    private RedeemStudentInvitationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RedeemStudentInvitationAction;
    }

    /**
     * The canonical fixture: a Gestor-created Aluno with a `pending`
     * account in `$org`, holding a usable unique invitation.
     */
    private function makePendingStudent(Organization $org, string $email = 'aluno@example.com'): User
    {
        $student = User::factory()->aluno()->create(['email' => $email]);
        Credential::factory()->pending()->create(['user_id' => $student->id, 'org_id' => $org->id]);

        return $student;
    }

    private function makeInvitation(Organization $org, User $student, array $attributes = []): StudentInvitation
    {
        return StudentInvitation::factory()->create(array_merge([
            'org_id' => $org->id,
            'user_id' => $student->id,
            'created_by' => User::factory()->inOrg($org->id)->create()->id,
        ], $attributes));
    }

    public function test_it_activates_a_pending_account_with_the_chosen_password(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student);

        $user = $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);

        $this->assertSame($student->id, $user->id);
        $credential = $user->fresh()->credentialFor($org);
        $this->assertNotNull($credential);
        $this->assertSame('active', $credential->status);
        $this->assertTrue(Hash::check('minha-senha-123', $credential->password));
        $this->assertNotNull($invitation->fresh()->used_at);
        $this->assertSame($student->id, Auth::id());
    }

    public function test_a_redeemed_token_cannot_be_redeemed_again(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student);

        $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);

        Auth::logout();

        try {
            $this->action->execute($invitation->token, ['password' => 'outra-senha-456']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_USED, $e->reason());
            $this->assertSame('Este convite já foi utilizado.', $e->userMessage());
        }
    }

    public function test_it_rejects_an_unknown_token(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);

        try {
            $this->action->execute('token-inexistente', ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_NOT_FOUND, $e->reason());
            $this->assertSame('Este convite não foi encontrado.', $e->userMessage());
        }
    }

    public function test_it_rejects_a_token_from_another_orgs_host(): void
    {
        $orgA = Organization::factory()->create();
        $student = $this->makePendingStudent($orgA);
        $invitation = $this->makeInvitation($orgA, $student);

        $orgB = Organization::factory()->create();
        $this->withOrgContext($orgB);

        try {
            $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_NOT_FOUND, $e->reason());
        }

        $this->assertFalse(Auth::check());
        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_it_rejects_a_revoked_invitation(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student, ['revoked_at' => now()]);

        try {
            $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_REVOKED, $e->reason());
            $this->assertSame('Este convite foi cancelado.', $e->userMessage());
        }
    }

    public function test_it_rejects_an_expired_invitation(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = $this->makePendingStudent($org);
        $invitation = $this->makeInvitation($org, $student, ['expires_at' => now()->subDay()]);

        try {
            $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_EXPIRED, $e->reason());
            $this->assertSame('Este convite expirou.', $e->userMessage());
        }
    }

    /**
     * A Gestor-imposed deactivation must never be resurrected by the
     * token — that is the whole reason `pending` and `inactive` are
     * distinct credential states.
     */
    public function test_it_rejects_redemption_for_a_gestor_deactivated_account(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = User::factory()->aluno()->create();
        Credential::factory()->inactive()->create(['user_id' => $student->id, 'org_id' => $org->id]);
        $invitation = $this->makeInvitation($org, $student);

        try {
            $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_INACTIVE, $e->reason());
            $this->assertSame('Esta conta está inativa. Procure o gestor da sua organização.', $e->userMessage());
        }

        $this->assertSame('inactive', $student->fresh()->credentialFor($org)->status);
        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_it_recreates_a_missing_credential(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        // Aluno sem credencial nesta org (a conta da org foi removida
        // depois do convite emitido).
        $student = User::factory()->aluno()->create();
        $invitation = $this->makeInvitation($org, $student);

        $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);

        $credential = $student->fresh()->credentialFor($org);
        $this->assertNotNull($credential);
        $this->assertSame('active', $credential->status);
        $this->assertTrue(Hash::check('minha-senha-123', $credential->password));
    }

    /**
     * An already-`active` account (the Aluno lost the link and the Gestor
     * reissued one) gets its password replaced — the re-issued invitation
     * doubles as a password reset, which is safe because issuing is a
     * Gestor-gated action.
     */
    public function test_it_replaces_the_password_of_an_already_active_account(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = User::factory()->aluno()->inOrg($org)->withPassword('senha-antiga')->create();
        $invitation = $this->makeInvitation($org, $student);

        $this->action->execute($invitation->token, ['password' => 'senha-nova-789']);

        $credential = $student->fresh()->credentialFor($org);
        $this->assertSame('active', $credential->status);
        $this->assertTrue(Hash::check('senha-nova-789', $credential->password));
        $this->assertFalse(Hash::check('senha-antiga', $credential->password));
    }

    /**
     * If the pre-registered person was promoted to staff after the link
     * went out, the token must not become a staff password reset — reads
     * as not-found, like every other forbidden redemption.
     */
    public function test_it_rejects_redemption_for_a_person_who_became_staff(): void
    {
        $org = Organization::factory()->create();
        $this->withOrgContext($org);
        $student = $this->makePendingStudent($org);
        $student->assignRole(RolesEnum::GESTOR->value);
        $invitation = $this->makeInvitation($org, $student);

        try {
            $this->action->execute($invitation->token, ['password' => 'minha-senha-123']);
            $this->fail('Esperava InvitationInvalidException.');
        } catch (InvitationInvalidException $e) {
            $this->assertSame(InvitationInvalidException::REASON_NOT_FOUND, $e->reason());
        }

        $this->assertFalse(Auth::check());
        $this->assertTrue(Hash::check('password', $student->fresh()->credentialFor($org)->password));
    }
}

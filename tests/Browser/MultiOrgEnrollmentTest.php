<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the multi-org adaptive invitation
 * flow: a user already registered/enrolled under Organization A visits
 * Organization B's course invitation link, the form collapses to a
 * password-only prompt once their existing e-mail is recognized, and
 * submitting logs them in and enrolls them in Org B's course too — without
 * ever creating a second `users` row.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada do usuário multi-org (senha errada rejeitada → senha correta
 * matricula) acontece no MESMO formulário; a jornada de cadastro novo é
 * outro método; e os três estados inválidos de link são percorridos como
 * visitante numa sessão só.
 */
class MultiOrgEnrollmentTest extends DuskTestCase
{
    private function invitationLinkFor(Organization $org, Course $course, string $state = 'valid'): InvitationLink
    {
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $factory = InvitationLink::factory();
        $factory = match ($state) {
            'expired' => $factory->expired(),
            'revoked' => $factory->revoked(),
            'exhausted' => $factory->exhausted(),
            default => $factory,
        };

        return $factory->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $gestor->id,
        ]);
    }

    public function test_existing_multi_org_user_invitation_lifecycle(): void
    {
        $orgA = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $student = User::factory()->create([
            'org_id' => $orgA->id,
            'email' => 'multiorg@example.com',
            'password' => Hash::make('senha-correta'),
        ]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);
        $invitationLink = $this->invitationLinkFor($orgB, $courseB);

        $this->browse(function (Browser $browser) use ($invitationLink, $student, $courseB): void {
            // 1. O e-mail existente colapsa o formulário para só a senha, e uma
            //    senha errada é rejeitada sem matricular ninguém.
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                ->assertVisible('@invitation-name')
                ->type('@invitation-email', 'multiorg@example.com')
                ->click('@invitation-name') // blur the e-mail field to trigger the AJAX check
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                // `SmartInvitationForm` binds BOTH a blur handler (fires
                // immediately) and a 400ms-debounced `input` handler, so a
                // second `checkEmail` is still pending when the collapse
                // completes. Submitting before it settles lets it re-run
                // `toggleFields` mid-navigation and restore `required` on the
                // hidden `password_confirmation`, silently blocking the submit.
                ->pause(700)
                ->type('@invitation-password', 'senha-errada')
                ->press('Matricular-me')
                // `waitForText`, not `assertSee`: the submit triggers a full
                // page reload back to `/convite/{token}` with the error
                // flashed, and asserting immediately races that navigation.
                ->waitForText('Senha incorreta para o e-mail informado.')
                ->assertGuest();

            $this->assertDatabaseMissing('course_user', [
                'user_id' => $student->id,
                'course_id' => $courseB->id,
            ]);

            // 2. Mesma tela, senha correta: autentica e matricula na 2ª Org.
            $browser->waitFor('@invitation-form')
                ->type('@invitation-email', 'multiorg@example.com')
                ->click('@invitation-name')
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                ->pause(700)
                ->type('@invitation-password', 'senha-correta')
                ->press('Matricular-me')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        // RN09 — uma única linha de `users`, `org_id` preso à Org original, e
        // matrícula ativa nas duas Organizações.
        $this->assertSame(1, User::where('email', 'multiorg@example.com')->count());

        $student = $student->fresh();
        $this->assertSame($orgA->id, $student->org_id, 'org_id must stay tied to the original Org.');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $courseB->id,
            'status' => 'active',
        ]);
    }

    public function test_a_new_user_can_register_and_enroll_through_an_invitation_link(): void
    {
        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);
        $invitationLink = $this->invitationLinkFor($orgB, $courseB);

        $this->browse(function (Browser $browser) use ($invitationLink): void {
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                ->assertVisible('@invitation-name')
                ->type('@invitation-email', 'novo.aluno@example.com')
                ->click('@invitation-name') // blur the e-mail field to trigger the AJAX check
                ->pause(500) // no existing account is found — the form must stay expanded
                ->assertVisible('@invitation-name')
                ->type('@invitation-name', 'Novo Aluno')
                ->type('@invitation-cpf', '123.456.789-09')
                ->type('@invitation-password', 'senha-segura-123')
                ->type('@invitation-password-confirmation', 'senha-segura-123')
                ->press('Matricular-me')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        $user = User::where('email', 'novo.aluno@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'novo.aluno@example.com',
            'org_id' => $orgB->id,
        ]);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $courseB->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('invitation_links', [
            'id' => $invitationLink->id,
            'current_uses' => 1,
        ]);
    }

    /**
     * Os três estados inválidos de link devolvem a MESMA mensagem genérica e
     * nunca renderizam o formulário — percorridos como visitante numa única
     * sessão de navegador.
     */
    public function test_invalid_invitation_link_states_are_rejected(): void
    {
        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);

        $expired = $this->invitationLinkFor($orgB, $courseB, 'expired');
        $revoked = $this->invitationLinkFor($orgB, $courseB, 'revoked');
        $exhausted = $this->invitationLinkFor($orgB, $courseB, 'exhausted');

        $message = 'Este link de convite é inválido, expirou ou já atingiu o limite de usos.';

        $this->browse(function (Browser $browser) use ($expired, $revoked, $exhausted, $message): void {
            // 1. Expirado
            $browser->visit('/convite/'.$expired->token)
                ->assertSee($message)
                ->assertMissing('@invitation-form');

            // 2. Revogado
            $browser->visit('/convite/'.$revoked->token)
                ->assertSee($message)
                ->assertMissing('@invitation-form');

            // 3. Esgotado
            $browser->visit('/convite/'.$exhausted->token)
                ->assertSee($message)
                ->assertMissing('@invitation-form');
        });

        $this->assertDatabaseCount('course_user', 0);
    }
}

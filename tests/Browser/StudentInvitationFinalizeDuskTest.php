<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E do convite único por aluno — o fluxo completo definido para a
 * plataforma: o Gestor cadastra o aluno (conta pendente), copia o link
 * ÚNICO de convite na listagem de Alunos, e o Aluno (guest) abre o link
 * com e-mail/nome pré-preenchidos e imutáveis e finaliza o próprio
 * cadastro definindo a senha. Cada jornada roda em UMA sessão de browser
 * (a sessão ociosa do Selenium morre por timeout de inatividade).
 */
class StudentInvitationFinalizeDuskTest extends DuskTestCase
{
    private function makePendingAluno(Organization $org, array $attributes = []): User
    {
        $aluno = User::factory()->aluno()->create($attributes);
        Credential::factory()->pending()->create(['user_id' => $aluno->id, 'org_id' => $org->id]);

        return $aluno;
    }

    /**
     * Jornada do Gestor: o diretório marca a conta como "Convite pendente"
     * e "Copiar convite" emite o toast de confirmação.
     */
    public function test_gestor_sees_pending_status_and_copies_the_invitation(): void
    {
        $org = $this->duskTenant();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        // o diretório lista só Alunos com matrícula viva em curso da org.
        $course = Course::factory()->for($org)->published()->create();
        $aluno = $this->makePendingAluno($org, [
            'name' => 'Aluna Convite',
            'email' => 'aluna.convite@example.com',
        ]);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno): void {
            $browser->loginAs($gestor)
                ->visit(route('gestor.students.index'))
                ->waitFor('@student-row-'.$aluno->id)
                ->assertSee('Convite pendente')
                ->assertPresent('@copy-invitation-'.$aluno->id)
                ->assertPresent('@renew-invitation-'.$aluno->id)
                ->click('@copy-invitation-'.$aluno->id)
                ->waitForText('Link de convite copiado.');
        });

        $this->assertSame(
            1,
            StudentInvitation::query()->where('user_id', $aluno->id)->count(),
            'copiar é get-or-create: nenhum segundo convite deve nascer'
        );
    }

    /**
     * Jornada do Aluno (guest): identidade pré-preenchida e imutável; só
     * senha + consent; termina em /meus-cursos com a conta ativa.
     */
    public function test_aluno_finalizes_the_registration_via_the_unique_link(): void
    {
        $org = $this->duskTenant();
        $course = Course::factory()->for($org)->published()->create();
        $aluno = $this->makePendingAluno($org, [
            'name' => 'Aluna Finaliza',
            'email' => 'aluna.finaliza@example.com',
        ]);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $invitation = StudentInvitation::factory()->create([
            'org_id' => $org->id,
            'user_id' => $aluno->id,
        ]);

        $this->browse(function (Browser $browser) use ($course, $invitation): void {
            $browser->visit('/convite/'.$invitation->token)
                ->waitFor('@invitation-form')
                ->assertSee('Olá, Aluna')
                ->assertValue('@invitation-email', 'aluna.finaliza@example.com')
                ->assertInputValue('@invitation-name', 'Aluna Finaliza')
                ->assertAttribute('@invitation-email', 'readonly', 'true')
                ->assertAttribute('@invitation-name', 'readonly', 'true')
                ->type('@invitation-password', 'senha-forte-123')
                ->type('@invitation-password-confirmation', 'senha-forte-123')
                ->check('consent')
                ->press('Salvar senha e começar')
                ->waitForLocation('/meus-cursos')
                ->assertSee($course->title);
        });

        $credential = $aluno->fresh()->credentialFor($org);
        $this->assertSame('active', $credential->status);
        $this->assertTrue(app('hash')->check('senha-forte-123', $credential->password));
        $this->assertNotNull($invitation->fresh()->used_at);
    }

    /**
     * Token é single-use: reaberto, lê como "já foi utilizado", sem
     * formulário.
     */
    public function test_a_used_link_reads_as_already_used(): void
    {
        $org = $this->duskTenant();
        $aluno = $this->makePendingAluno($org);

        $invitation = StudentInvitation::factory()->create([
            'org_id' => $org->id,
            'user_id' => $aluno->id,
            'used_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($invitation): void {
            $browser->visit('/convite/'.$invitation->token)
                ->assertSee('Este convite já foi utilizado.')
                ->assertNotPresent('@invitation-form');
        });
    }

    /**
     * "Renovar" gira o token: o link antigo morre na hora (revogado) e
     * nasce outro utilizável — rotação de segurança para link vazado.
     */
    public function test_renewing_the_invitation_rotates_the_token(): void
    {
        $org = $this->duskTenant();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->for($org)->published()->create();
        $aluno = $this->makePendingAluno($org, [
            'name' => 'Aluna Renovada',
            'email' => 'aluna.renovada@example.com',
        ]);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        $invitation = StudentInvitation::factory()->create([
            'org_id' => $org->id,
            'user_id' => $aluno->id,
        ]);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $invitation): void {
            $browser->loginAs($gestor)
                ->visit(route('gestor.students.index'))
                ->waitFor('@student-row-'.$aluno->id)
                ->click('@renew-invitation-'.$aluno->id)
                ->waitForModalShown('renew-invitation-'.$aluno->id)
                ->click('@renew-invitation-confirm-'.$aluno->id)
                ->waitForLocation('/gestor/students')
                ->waitFor('@invitation-flash')
                ->assertSee('Novo link de convite gerado.')
                ->assertSee('Copiar novo link');

            $invitations = StudentInvitation::query()->where('user_id', $aluno->id)->orderBy('id')->get();
            $this->assertSame(2, $invitations->count());
            $this->assertNotNull($invitations->first()->revoked_at, 'o link antigo deve ser revogado');
            $this->assertNull($invitations->last()->revoked_at, 'o novo link deve estar utilizável');
            $this->assertNotSame($invitation->token, $invitations->last()->token);

            // O banner de flash aponta para o token novo.
            $this->assertStringContainsString(
                $invitations->last()->token,
                $browser->text('@invitation-flash-link')
            );
        });
    }
}

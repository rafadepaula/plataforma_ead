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
 * Contrato E2E do formulário adaptativo de convite — o único lugar onde o
 * módulo `resources/js/modules/SmartInvitationForm.js` é exercitado com
 * respostas assíncronas de verdade (o projeto não tem runner de teste JS;
 * a cobertura do módulo é, por decisão de arquitetura, esta suíte).
 *
 * Isolamento via `DatabaseTruncation` herdado de `Tests\DuskTestCase`
 * (nunca `RefreshDatabase`: o Dusk dirige navegador e app em processos HTTP
 * separados).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de conta nova (e-mail parcial não colapsa nada → cadastro completo
 * → matrícula), a jornada de conta existente (colapso → matrícula sem
 * duplicar usuário), a virada de veredito durante a digitação, o bloqueio
 * por falta de consentimento e a tela de convite indisponível.
 *
 * Enquanto `MultiOrgEnrollmentTest` cobre a TENANCY do convite (aluno da
 * Org A matriculado também na Org B, `org_id` preservado), esta suíte cobre
 * o COMPORTAMENTO DA DOM: quais nós ganham/perdem `.d-none`, qual campo
 * mantém `required`, e qual texto o aluno lê.
 */
class SmartInvitationAdaptiveDuskTest extends DuskTestCase
{
    /**
     * Convite utilizável de um curso publicado, criado por um Gestor da
     * mesma Organização.
     */
    private function usableInvitationLink(Organization $org, Course $course): InvitationLink
    {
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        return InvitationLink::factory()->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $gestor->id,
        ]);
    }

    /**
     * Jornada de conta nova. Um e-mail ainda incompleto NÃO pode disparar
     * colapso algum (o módulo só consulta o servidor com um endereço bem
     * formado), e o cadastro completo cria a conta e a matrícula.
     */
    public function test_new_account_flow_keeps_the_registration_fields_and_enrolls(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->usableInvitationLink($org, $course);

        $this->browse(function (Browser $browser) use ($invitationLink): void {
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                // Estado inicial: dica escondida, campos de cadastro visíveis
                // e obrigatórios.
                ->assertMissing('@invitation-existing-account-hint')
                ->assertVisible('@invitation-name')
                ->assertVisible('@invitation-cpf')
                ->assertVisible('@invitation-password-confirmation')
                ->assertAttribute('@invitation-name', 'required', 'true')
                ->assertAttribute('@invitation-cpf', 'required', 'true')
                ->assertAttribute('@invitation-password-confirmation', 'required', 'true')
                // E-mail ainda parcial: nem consulta, nem colapso.
                ->type('@invitation-email', 'novo.aluno@exam')
                ->click('@invitation-password')
                ->pause(800)
                ->assertMissing('@invitation-existing-account-hint')
                ->assertVisible('@invitation-name')
                ->assertAttribute('@invitation-name', 'required', 'true')
                // E-mail completo e desconhecido: o formulário segue inteiro.
                ->type('@invitation-email', 'novo.aluno@example.com')
                ->click('@invitation-password')
                ->pause(800)
                ->assertMissing('@invitation-existing-account-hint')
                ->assertVisible('@invitation-name')
                ->type('@invitation-name', 'Novo Aluno')
                ->type('@invitation-cpf', '123.456.789-09')
                ->type('@invitation-password', 'senha-segura-123')
                ->type('@invitation-password-confirmation', 'senha-segura-123')
                ->check('input[name=consent]')
                ->press('@invitation-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        $student = User::where('email', 'novo.aluno@example.com')->firstOrFail();

        $this->assertSame('Novo Aluno', $student->name);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    /**
     * Jornada de conta existente: a dica aparece com o texto verbatim da
     * spec, os três campos de cadastro ganham `.d-none` no wrapper e perdem
     * o `required`, e a matrícula acontece sem criar uma segunda linha em
     * `users`.
     */
    public function test_existing_account_flow_collapses_the_registration_fields_and_enrolls_without_duplicating_the_user(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->usableInvitationLink($org, $course);

        $student = User::factory()->create([
            'org_id' => $org->id,
            'email' => 'ja.cadastrado@example.com',
            'password' => Hash::make('senha-correta'),
        ]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($invitationLink): void {
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                ->type('@invitation-email', 'ja.cadastrado@example.com')
                ->click('@invitation-password') // tira o foco do e-mail: checagem imediata
                ->waitFor('@invitation-existing-account-hint')
                ->assertSeeIn(
                    '@invitation-existing-account-hint',
                    'Já encontramos uma conta com este e-mail. Confirme sua senha para se matricular.'
                )
                // Esconder é SEMPRE `.d-none` no wrapper — nunca `hidden`,
                // nunca `style="display:none"`.
                ->waitUntilMissing('@invitation-name')
                ->assertMissing('@invitation-cpf')
                ->assertMissing('@invitation-password-confirmation')
                ->assertPresent('[data-invitation-field="new-account"].d-none')
                ->assertAttributeMissing('@invitation-name', 'required')
                ->assertAttributeMissing('@invitation-cpf', 'required')
                ->assertAttributeMissing('@invitation-password-confirmation', 'required')
                // E-mail e senha continuam visíveis e obrigatórios.
                ->assertVisible('@invitation-email')
                ->assertVisible('@invitation-password')
                ->assertAttribute('@invitation-password', 'required', 'true')
                ->type('@invitation-password', 'senha-correta')
                ->check('input[name=consent]')
                ->press('@invitation-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        $this->assertSame(1, User::where('email', 'ja.cadastrado@example.com')->count());
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    /**
     * Digitação incremental (sem tirar o foco do campo): o handler de
     * `input` com debounce de 400ms colapsa o formulário quando o endereço
     * bate com uma conta e o REEXPANDE, com `required` restaurado, assim que
     * a continuação da digitação produz um endereço desconhecido.
     */
    public function test_incremental_typing_flips_the_verdict_and_restores_required(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->usableInvitationLink($org, $course);

        $existing = User::factory()->create([
            'org_id' => $org->id,
            'email' => 'ja.cadastrado@example.com',
            'password' => Hash::make('senha-correta'),
        ]);
        $existing->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($invitationLink): void {
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                // 1. Só digitação: o debounce dispara a checagem sem `blur`.
                ->type('@invitation-email', 'ja.cadastrado@example.com')
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                // 2. A digitação continua e o endereço deixa de existir: o
                //    veredito vira e os campos voltam obrigatórios.
                ->append('@invitation-email', '.br')
                ->waitUntilMissing('@invitation-existing-account-hint')
                ->waitFor('@invitation-name')
                ->assertVisible('@invitation-cpf')
                ->assertVisible('@invitation-password-confirmation')
                ->assertAttribute('@invitation-name', 'required', 'true')
                ->assertAttribute('@invitation-cpf', 'required', 'true')
                ->assertAttribute('@invitation-password-confirmation', 'required', 'true')
                ->assertMissing('[data-invitation-field="new-account"].d-none');
        });
    }

    /**
     * O consentimento é o único switch obrigatório do produto. Marcado como
     * `required`, o navegador barra o envio; e mesmo quando o `required`
     * some da DOM (extensão, navegador antigo, submit forjado), o servidor
     * recusa a matrícula e devolve a mensagem à tela.
     */
    public function test_missing_consent_blocks_the_enrollment_on_both_the_client_and_the_server(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->usableInvitationLink($org, $course);
        $path = '/convite/'.$invitationLink->token;

        $this->browse(function (Browser $browser) use ($path): void {
            $browser->visit($path)
                ->waitFor('@invitation-form')
                ->type('@invitation-email', 'sem.consentimento@example.com')
                ->type('@invitation-name', 'Sem Consentimento')
                ->type('@invitation-cpf', '123.456.789-09')
                ->type('@invitation-password', 'senha-segura-123')
                ->type('@invitation-password-confirmation', 'senha-segura-123')
                ->pause(800) // deixa a checagem de e-mail assentar
                // 1. Sem marcar o switch, o navegador nem envia o formulário:
                //    a tela não muda e o campo continua desmarcado.
                //    (`assertGuest` fica para o fim do método: ele navega
                //    para a rota de sondagem do Dusk e recarrega a tela,
                //    limpando tudo o que foi digitado até aqui.)
                ->press('@invitation-submit')
                ->pause(500)
                ->assertPathIs($path)
                ->assertNotChecked('input[name=consent]')
                // 2. Sem o `required` na DOM, quem recusa é o servidor.
                ->script('document.querySelector(\'input[name="consent"]\').required = false;');

            $browser->press('@invitation-submit')
                ->waitForText('É necessário concordar')
                ->assertPathIs($path)
                ->assertNotChecked('input[name=consent]')
                ->assertGuest();
        });

        $this->assertDatabaseMissing('users', ['email' => 'sem.consentimento@example.com']);
        $this->assertDatabaseCount('course_user', 0);
    }

    /**
     * Link indisponível não renderiza formulário nenhum: a tela é o estado
     * vazio de cadeado, servido com 404 pelo handler global (o status é
     * afirmado em `tests/Feature/SmartInvitationTest.php`; aqui o contrato é
     * o da tela).
     */
    public function test_an_unusable_invitation_link_renders_the_empty_state_without_the_form(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $expired = InvitationLink::factory()->expired()->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $gestor->id,
        ]);

        $this->browse(function (Browser $browser) use ($expired): void {
            $browser->visit('/convite/'.$expired->token)
                ->assertSee('Este convite expirou.')
                ->assertSee('Peça um novo link ao responsável pelo curso.')
                ->assertMissing('@invitation-form')
                ->assertMissing('@invitation-email')
                ->assertMissing('@invitation-submit');

            // Token inexistente cai na mesma tela, com o motivo próprio.
            $browser->visit('/convite/token-que-nunca-existiu')
                ->assertSee('Este convite não foi encontrado.')
                ->assertMissing('@invitation-form');
        });

        $this->assertDatabaseCount('course_user', 0);
    }
}

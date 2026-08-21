<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DashboardDuskTest extends DuskTestCase
{
    public function test_admin_dashboard_global_scope_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Instituto Alfa']);
        $course = Course::factory()->for($org)->create(['title' => 'NR12 — Segurança em Máquinas']);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'João Pereira']);
        $student->assignRole('aluno');

        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $org): void {
            // 1. KPIs e matrículas recentes.
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertSee('Dashboard')
                ->assertSee('Um panorama da plataforma nos últimos 30 dias.')
                ->assertDontSee('Novo curso')
                ->assertSeeIgnoringCase('Alunos ativos')
                ->waitFor('@recent-enrollments-table')
                ->assertAttribute('@recent-enrollments-table', 'aria-label', 'Matrículas recentes')
                ->assertSee('Matrículas recentes')
                ->assertSee('João Pereira')
                ->assertSee('Nada aguardando você');

            // 2. Sem impersonation ativa, o Admin vê o resumo por Organização.
            $browser->waitFor('@organizations-summary-table')
                ->assertSee('Instituto Alfa');

            // 2.1. Com Impersonate Org ativa, a tabela deixa de aparecer —
            //      esse é o mesmo gate (`$isGlobalAdminView`) que o
            //      controller usa em produção, exercitado aqui via sessão
            //      real de navegador (não a Kernel de teste do PHPUnit).
            $browser->visit(route('organizations.index'))
                ->waitFor('@impersonate-'.$org->id)
                ->click('@impersonate-'.$org->id)
                ->waitForLocation('/organizations')
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertMissing('@organizations-summary-table')
                ->waitFor('@topbar-exit-impersonation')
                ->click('@topbar-exit-impersonation')
                ->waitForLocation('/admin/dashboard');

            // 3. Ponto de entrada do export aponta para a rota de streaming.
            $browser->waitFor('@export-enrollments-csv')
                ->assertAttribute('@export-enrollments-csv', 'href', route('reports.export', ['type' => 'enrollments']));

            // 4. Tipo de relatório desconhecido é 404, não 500.
            $browser->visit('/admin/reports/invalido/export')
                ->assertSee('404');

            // 5. Configurações do Admin gravam na linha GLOBAL.
            $browser->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->type('smtp_host', 'smtp.global.example')
                ->click('@settings-submit')
                ->waitForText('Configurações salvas com sucesso.');
        });

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'smtp.global.example',
        ]);
    }

    public function test_gestor_dashboard_org_scope_lifecycle(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $gestor = User::factory()->create(['org_id' => $orgA->id]);
        $gestor->assignRole('gestor');

        $studentA = User::factory()->create(['org_id' => $orgA->id, 'name' => 'Ana Costa']);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id, 'name' => 'Marcos Silva']);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);

        $this->browse(function (Browser $browser) use ($gestor): void {
            // 1. Só as matrículas da própria Organização aparecem.
            $browser->loginAs($gestor)
                ->visit(route('admin.dashboard'))
                ->waitFor('@recent-enrollments-table')
                ->assertSee('Um panorama da sua organização nos últimos 30 dias.')
                ->assertSee('Novo curso')
                ->assertSee('Ana Costa')
                ->assertDontSee('Marcos Silva')
                // 2. E o resumo por Organização é exclusivo do Admin.
                ->assertMissing('@organizations-summary-table');

            // 3. Override de configuração no nível da Organização persiste...
            $browser->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->type('signature', 'Diretoria Pedagógica — Minha Org')
                ->type('smtp_host', 'smtp.org.example')
                // `waitForReload()` must WRAP the action, not follow it: on its
                // own it starts polling for the current node to go stale only
                // after the click, so a reload that lands first is never
                // observed and it times out. This was flaky in the full suite
                // and passed in isolation for exactly that reason.
                ->waitForReload(fn (Browser $b) => $b->click('@settings-submit'))
                ->waitForText('Configurações salvas com sucesso.');

            // 4. ...e sobrevive ao recarregamento da tela.
            $browser->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->assertInputValue('signature', 'Diretoria Pedagógica — Minha Org');
        });

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => $orgA->id,
            'setting_value' => 'smtp.org.example',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'signature',
            'org_id' => $orgA->id,
            'setting_value' => 'Diretoria Pedagógica — Minha Org',
        ]);
        // O Gestor jamais grava na linha global.
        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
        ]);
    }
}

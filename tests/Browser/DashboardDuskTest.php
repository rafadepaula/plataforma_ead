<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-12 — E2E coverage of the Admin/Gestor Dashboard: KPI stat cards +
 * recent-enrollments table render for an Admin (global) and a Gestor
 * (org-scoped), the CSV export entry point exposes a working download
 * link, and the settings edit screen persists an org-level override.
 */
class DashboardDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_dashboard_renders_metrics_and_recent_enrollments(): void
    {
        $org = Organization::factory()->create();
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

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertSee('Dashboard')
                // `<x-ui.stat-card>`'s kicker is `text-transform: uppercase`,
                // and Selenium's `getText()` (which backs `assertSee`)
                // returns the CSS-rendered text, not the literal DOM string
                // (see `laravel-dusk` skill / `CertificateRevocationTest`
                // precedent) — assert against the rendered case.
                ->assertSee('ALUNOS ATIVOS')
                ->waitFor('@recent-enrollments-table')
                ->assertSee('Matrículas recentes')
                ->assertSee('João Pereira');
        });
    }

    public function test_gestor_dashboard_shows_only_their_own_orgs_scoped_data(): void
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
            $browser->loginAs($gestor)
                ->visit(route('admin.dashboard'))
                ->waitFor('@recent-enrollments-table')
                ->assertSee('Ana Costa')
                ->assertDontSee('Marcos Silva');
        });
    }

    public function test_dashboard_csv_export_link_points_at_the_streaming_download_route(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@export-enrollments-csv')
                ->assertAttribute('@export-enrollments-csv', 'href', route('reports.export', ['type' => 'enrollments']));
        });
    }

    public function test_gestor_persists_a_settings_override_via_the_edit_screen(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole('gestor');

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->type('signature', 'Diretoria Pedagógica — Minha Org')
                // `waitForReload()` must WRAP the action, not follow it: on
                // its own it starts polling for the current node to go stale
                // only after the click, so a reload that lands first is never
                // observed and it times out. This was flaky in the full suite
                // and passed in isolation for exactly that reason.
                ->waitForReload(fn (Browser $b) => $b->click('@settings-submit'))
                ->visit(route('settings.edit'))
                ->assertInputValue('signature', 'Diretoria Pedagógica — Minha Org');
        });
    }

    public function test_settings_are_scoped_to_the_global_row_for_an_admin_and_to_the_org_row_for_a_gestor(): void
    {
        $org = Organization::factory()->create();

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole('gestor');

        $this->browse(function (Browser $browser) use ($admin, $gestor): void {
            $browser->loginAs($admin)
                ->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->type('smtp_host', 'smtp.global.example')
                ->click('@settings-submit')
                ->waitForText('Configurações salvas com sucesso.');

            $browser->loginAs($gestor)
                ->visit(route('settings.edit'))
                ->waitFor('@settings-form')
                ->type('smtp_host', 'smtp.org.example')
                ->click('@settings-submit')
                ->waitForText('Configurações salvas com sucesso.');
        });

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => SystemSetting::GLOBAL_ORG_ID,
            'setting_value' => 'smtp.global.example',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'smtp_host',
            'org_id' => $org->id,
            'setting_value' => 'smtp.org.example',
        ]);
    }

    public function test_an_unknown_report_type_returns_404(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit('/admin/reports/invalido/export')
                ->assertSee('404');
        });
    }
}

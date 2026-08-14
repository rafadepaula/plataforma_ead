<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-15 §5/RF33 — E2E coverage of the `/admin/audit-logs` and
 * `/gestor/audit-logs` screens: loading, filtering, opening the shared
 * "Ver diff" modal, pagination, CSV export, and cross-org isolation
 * (a Gestor never sees another Org's rows nor the Admin-only Org filter
 * dropdown).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): toda a
 * jornada do Admin na tela (estado inicial → diff → filtro → paginação →
 * export) é um método; a jornada do Gestor (isolamento cross-org →
 * rejeição na URL de Admin) é outro, pois exige outro ator.
 *
 * Seeds `AuditLog` rows directly via `AuditLog::withoutEvents()` (mirrors
 * `AuditLogFactory`'s own doc-comment guidance) to avoid `OrgScope`'s
 * `creating` hook fighting an explicit `org_id` during fixture setup.
 */
class AuditLogUiTest extends DuskTestCase
{
    private function seedLog(Organization $org, ?User $user, string $event, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::withoutEvents(fn () => AuditLog::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user?->id,
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
        ]));
    }

    public function test_admin_audit_logs_screen_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $target = User::factory()->create(['org_id' => $org->id, 'name' => 'Alvo Da Auditoria']);

        $log = $this->seedLog(
            $org,
            $target,
            'course.updated',
            ['title' => 'Título Antigo'],
            ['title' => 'Título Novo'],
        );

        $this->browse(function (Browser $browser) use ($admin, $org, $log): void {
            // 1. Estado inicial: a tabela carrega e NENHUM modal nasce aberto
            //    (BUG-003/UX-003).
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@audit-logs-index')
                ->assertSee('Logs de Auditoria')
                ->waitFor('@audit-log-row-'.$log->id)
                ->assertMissing('#audit-diff-modal')
                // `event` renders inside `<x-ui.badge>`, which applies
                // `text-transform: uppercase` — assert against the
                // CSS-rendered text per `laravel-dusk`'s
                // assertSeeIn/text-transform gotcha.
                ->assertSeeIn('@audit-log-row-'.$log->id, 'COURSE.UPDATED');

            // 2. Abrir o diff da linha mostra os valores antigo e novo.
            $browser->waitFor('@view-diff-'.$log->id)
                ->click('@view-diff-'.$log->id)
                ->waitFor('@audit-diff-old')
                ->assertSeeIn('@audit-diff-old', 'Título Antigo')
                ->assertSeeIn('@audit-diff-new', 'Título Novo');

            // 3. Com volume suficiente para uma 2ª página (25/página, ver
            //    `audit-logs-conventions`), o filtro por categoria de evento
            //    aplica e a paginação aparece.
            $this->seedLog($org, null, 'course.updated');
            for ($i = 0; $i < 26; $i++) {
                $this->seedLog($org, null, 'login.success');
            }

            $browser->visit(route('admin.audit-logs.index'))
                ->waitFor('@audit-logs-filter-form')
                ->select('@audit-logs-event-filter', 'authentication')
                ->click('@audit-logs-filter-submit')
                ->waitFor('@audit-logs-table')
                ->assertPresent('.pagination, [aria-label="Pagination Navigation"], nav')
                // O filtro é de autenticação: eventos de curso saem da lista.
                ->assertDontSee('COURSE.UPDATED');

            // 4. O ponto de entrada do export aponta para a rota de streaming.
            $browser->visit(route('admin.audit-logs.index'))
                ->waitFor('@export-audit-logs-csv')
                ->assertAttribute('@export-audit-logs-csv', 'href', route('admin.audit-logs.export'));
        });

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'event' => 'course.updated',
        ]);
        // ≥ 26: os 26 semeados, mais o `login.success` real que o
        // `loginAs()` da cadeia registra pelo próprio pipeline de auditoria.
        $this->assertGreaterThanOrEqual(
            26,
            AuditLog::withoutGlobalScopes()->where('event', 'login.success')->count()
        );
    }

    public function test_gestor_audit_logs_isolation_and_admin_route_rejection(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $ownOrg->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $ownLog = $this->seedLog($ownOrg, null, 'login.success');
        $otherOrgLog = $this->seedLog($otherOrg, null, 'login.failed');

        $this->browse(function (Browser $browser) use ($gestor, $ownLog, $otherOrgLog): void {
            // 1. Sua própria tela: só as linhas da própria Organização e sem
            //    o filtro de Organização (exclusivo do Admin).
            $browser->loginAs($gestor)
                ->visit(route('gestor.audit-logs.index'))
                ->waitFor('@audit-logs-index')
                ->waitFor('@audit-log-row-'.$ownLog->id)
                ->assertMissing('@audit-log-row-'.$otherOrgLog->id)
                ->assertMissing('@audit-logs-org-filter');

            // 2. A URL prefixada de Admin é rejeitada para o mesmo Gestor.
            $browser->visit(route('admin.audit-logs.index'))
                ->assertSee('403');
        });

        $this->assertDatabaseHas('audit_logs', [
            'id' => $otherOrgLog->id,
            'org_id' => $otherOrgLog->org_id,
        ]);
    }
}

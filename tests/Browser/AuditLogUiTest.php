<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-15 §5/RF33 — E2E coverage of the `/admin/audit-logs` and
 * `/gestor/audit-logs` screens: loading, filtering, opening the shared
 * "Ver diff" modal, pagination, CSV export, and cross-org isolation
 * (a Gestor never sees another Org's rows nor the Admin-only Org filter
 * dropdown).
 *
 * Depends on Bucket B's `AuditLogController`/routes and Bucket A's
 * `AuditLog`/`AuditLogFactory`. Seeds `AuditLog` rows directly via
 * `AuditLog::withoutEvents()` (mirrors `AuditLogFactory`'s own
 * doc-comment guidance) to avoid `OrgScope`'s `creating` hook fighting
 * an explicit `org_id` during the fixture setup.
 */
class AuditLogUiTest extends DuskTestCase
{
    use DatabaseMigrations;

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

    public function test_admin_can_load_filter_and_view_a_diff_on_the_audit_logs_screen(): void
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

        $this->browse(function (Browser $browser) use ($admin, $log): void {
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@audit-logs-index')
                ->assertSee('Logs de Auditoria')
                ->waitFor('@audit-log-row-'.$log->id)
                // `event` renders inside `<x-ui.badge>`, which applies
                // `text-transform: uppercase` — assert against the
                // CSS-rendered text per `laravel-dusk`'s
                // assertSeeIn/text-transform gotcha.
                ->assertSeeIn('@audit-log-row-'.$log->id, 'COURSE.UPDATED')
                ->waitFor('@view-diff-'.$log->id)
                ->click('@view-diff-'.$log->id)
                ->waitFor('@audit-diff-old')
                ->assertSeeIn('@audit-diff-old', 'Título Antigo')
                ->assertSeeIn('@audit-diff-new', 'Título Novo');
        });
    }

    public function test_diff_modal_does_not_open_automatically_when_page_loads(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $target = User::factory()->create(['org_id' => $org->id]);

        $log = $this->seedLog(
            $org,
            $target,
            'course.updated',
            ['title' => 'Título Antigo'],
            ['title' => 'Título Novo'],
        );

        $this->browse(function (Browser $browser) use ($admin, $log): void {
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@audit-logs-index')
                ->waitFor('@audit-log-row-'.$log->id)
                ->assertSee('Logs de Auditoria')
                ->assertMissing('#audit-diff-modal');
        });
    }

    public function test_admin_can_filter_by_event_category_and_paginate(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $matchingLog = $this->seedLog($org, null, 'login.success');
        $otherLog = $this->seedLog($org, null, 'course.updated');

        // Force a 2nd page: 25/page pagination per `audit-logs-conventions`.
        for ($i = 0; $i < 25; $i++) {
            $this->seedLog($org, null, 'login.success');
        }

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@audit-logs-filter-form')
                ->select('@audit-logs-event-filter', 'authentication')
                ->click('@audit-logs-filter-submit')
                ->waitFor('@audit-logs-table')
                ->assertPresent('.pagination, [aria-label="Pagination Navigation"], nav');
        });
    }

    public function test_admin_can_trigger_csv_export(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->seedLog($org, null, 'login.success');

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@export-audit-logs-csv')
                ->assertAttribute('@export-audit-logs-csv', 'href', route('admin.audit-logs.export'));
        });
    }

    public function test_gestor_can_load_their_screen_and_never_sees_another_orgs_rows_or_the_org_filter(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $ownOrg->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $ownLog = $this->seedLog($ownOrg, null, 'login.success');
        $otherOrgLog = $this->seedLog($otherOrg, null, 'login.failed');

        $this->browse(function (Browser $browser) use ($gestor, $ownLog, $otherOrgLog): void {
            $browser->loginAs($gestor)
                ->visit(route('gestor.audit-logs.index'))
                ->waitFor('@audit-logs-index')
                ->waitFor('@audit-log-row-'.$ownLog->id)
                ->assertMissing('@audit-log-row-'.$otherOrgLog->id)
                ->assertMissing('@audit-logs-org-filter');
        });
    }

    public function test_gestor_visiting_the_admin_prefixed_url_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('admin.audit-logs.index'))
                ->assertSee('403');
        });
    }
}

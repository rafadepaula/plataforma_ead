<?php

namespace Tests\Browser\Theme;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Gap 11's "cor nunca é o único sinal" applied to the audit-diff modal:
 * a changed field is marked by weight + container (`.diff-changed`,
 * `fw-semibold`, `border-start`), never by color alone, per
 * `AuditLogDiffModal.js`'s `renderPane()`.
 */
class AuditDiffModalHighlightTest extends DuskTestCase
{
    public function test_changed_fields_carry_a_weight_and_container_signal_not_color_alone(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $target = User::factory()->create(['org_id' => $org->id]);

        $log = AuditLog::withoutEvents(fn () => AuditLog::factory()->create([
            'org_id' => $org->id,
            'user_id' => $target->id,
            'event' => 'course.updated',
            'old_values' => ['title' => 'Título Antigo', 'status' => 'draft'],
            'new_values' => ['title' => 'Título Novo', 'status' => 'draft'],
        ]));

        $this->browse(function (Browser $browser) use ($admin, $log): void {
            $browser->loginAs($admin)
                ->visit(route('admin.audit-logs.index'))
                ->waitFor('@view-diff-'.$log->id)
                ->click('@view-diff-'.$log->id)
                ->waitFor('@audit-diff-old')
                ->assertSeeIn('@audit-diff-old', 'Título Antigo')
                ->assertSeeIn('@audit-diff-new', 'Título Novo');

            // `title` differs between old/new -> flagged. `status` is
            // identical on both sides -> never flagged.
            $result = $browser->script(<<<'JS'
                var oldPane = document.querySelector('[dusk="audit-diff-old"]');
                var newPane = document.querySelector('[dusk="audit-diff-new"]');
                var oldChanged = oldPane.querySelectorAll('.diff-changed');
                var newChanged = newPane.querySelectorAll('.diff-changed');
                return [
                    oldChanged.length,
                    newChanged.length,
                    Array.from(oldChanged).some(function (el) { return el.textContent.indexOf('title') !== -1; }),
                    Array.from(newChanged).some(function (el) { return el.textContent.indexOf('title') !== -1; }),
                    Array.from(oldChanged).some(function (el) { return el.textContent.indexOf('status') !== -1; }),
                    getComputedStyle(oldChanged[0]).fontWeight,
                ];
            JS)[0];

            [$oldChangedCount, $newChangedCount, $oldHasTitle, $newHasTitle, $oldFlagsStatus, $fontWeight] = $result;

            self::assertSame(1, $oldChangedCount, 'Exactly one field differs between old/new — only "title" should be flagged.');
            self::assertSame(1, $newChangedCount);
            self::assertTrue($oldHasTitle, 'The changed "title" line was not flagged in the old pane.');
            self::assertTrue($newHasTitle, 'The changed "title" line was not flagged in the new pane.');
            self::assertFalse($oldFlagsStatus, 'The unchanged "status" field must not be flagged.');

            // Weight, not color: the flagged line renders with a heavier
            // font weight (>= 600 == Bootstrap's `fw-semibold`).
            self::assertGreaterThanOrEqual(600, (int) $fontWeight);
        });
    }
}

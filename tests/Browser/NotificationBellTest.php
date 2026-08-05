<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-13 §4 (RF28) — E2E coverage of the topbar notification bell:
 * visibility is role-gated (gestor/aluno only, never Admin), the badge
 * reflects the unread count, the dropdown lists the 10 most recent
 * notifications (`ORDER BY created_at DESC`), "marcar todas como lidas"
 * clears the badge, and clicking an item marks it read then redirects to
 * its `data.action_url`.
 *
 * Bucket 1's Notification classes aren't the system under test here, so
 * rows are inserted directly via the framework's own
 * `Illuminate\Notifications\DatabaseNotification` model — the same
 * `notifications` table shape any of the 4 SPEC-13 §2 triggers writes to.
 */
class NotificationBellTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function createNotification(User $user, string $message, ?string $actionUrl = null, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\EnrollmentConfirmedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'message' => $message,
                'action_url' => $actionUrl ?? route('student.courses.index', [], false),
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_bell_renders_for_gestor_and_aluno_but_not_for_admin(): void
    {
        $org = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->visit(route('admin.dashboard'))
                ->waitFor('@notifications-bell')
                ->assertVisible('@notifications-bell');
        });

        $this->browse(function (Browser $browser) use ($aluno): void {
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->assertVisible('@notifications-bell');
        });

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertMissing('@notifications-bell');
        });
    }

    public function test_badge_shows_unread_count_and_dropdown_lists_10_most_recent_notifications(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $notifications = [];
        for ($i = 1; $i <= 12; $i++) {
            $notification = $this->createNotification($aluno, "Notificação número {$i}");
            $notification->forceFill(['created_at' => now()->addMinutes($i)])->save();
            $notifications[$i] = $notification;
        }

        $this->browse(function (Browser $browser) use ($notifications): void {
            $aluno = $notifications[12]->notifiable;

            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->waitForTextIn('@notifications-badge', '12')
                ->click('@notifications-toggle')
                ->waitFor('@notifications-dropdown')
                ->assertVisible('@notifications-dropdown')
                // Most recent (id 12/11) must be present, oldest (id 1/2)
                // must have been dropped by the `limit(10)` cap.
                ->assertVisible('@notifications-item-'.$notifications[12]->id)
                ->assertVisible('@notifications-item-'.$notifications[3]->id)
                ->assertMissing('@notifications-item-'.$notifications[1]->id)
                ->assertMissing('@notifications-item-'.$notifications[2]->id);

            $itemCount = $browser->script(
                "return document.querySelectorAll('[data-notifications-item]').length;"
            )[0];

            $this->assertSame(10, $itemCount);
        });
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->createNotification($aluno, 'Primeira notificação não lida');
        $this->createNotification($aluno, 'Segunda notificação não lida');

        $this->browse(function (Browser $browser) use ($aluno): void {
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->waitForTextIn('@notifications-badge', '2')
                ->click('@notifications-toggle')
                ->waitFor('@notifications-dropdown')
                ->click('@notifications-mark-all-read')
                ->waitUntil("document.querySelector('[data-notifications-badge]').style.display === 'none'")
                ->assertMissing('@notifications-badge');
        });

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(0, DatabaseNotification::query()->whereNull('read_at')->count());
    }

    public function test_clicking_a_notification_item_redirects_to_action_url_and_marks_it_read(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $notification = $this->createNotification(
            $aluno,
            'Sua matrícula foi confirmada.',
            route('student.courses.index', [], false)
        );

        $this->browse(function (Browser $browser) use ($aluno, $notification): void {
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->click('@notifications-toggle')
                ->waitFor('@notifications-dropdown')
                ->click('@notifications-item-'.$notification->id)
                ->waitForLocation(route('student.courses.index', [], false))
                ->assertPathIs(route('student.courses.index', [], false));
        });

        $this->assertNotNull($notification->fresh()->read_at);
    }
}

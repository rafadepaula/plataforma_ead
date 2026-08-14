<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-13 §4 (RF28) — E2E coverage of the topbar notification bell:
 * visibility is role-gated (gestor/aluno only, never Admin), the badge
 * reflects the unread count, the dropdown lists the 10 most recent
 * notifications (`ORDER BY created_at DESC`), clicking an item marks it
 * read then redirects to its `data.action_url`, and "marcar todas como
 * lidas" clears the badge.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): toda a
 * interação do sino (badge → dropdown → item individual → marcar todas) é
 * uma jornada contínua num único método. A visibilidade por papel exige
 * três atores distintos, então permanece em método próprio.
 *
 * Bucket 1's Notification classes aren't the system under test here, so
 * rows are inserted directly via the framework's own
 * `Illuminate\Notifications\DatabaseNotification` model — the same
 * `notifications` table shape any of the 4 SPEC-13 §2 triggers writes to.
 */
class NotificationBellTest extends DuskTestCase
{
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

    public function test_notification_bell_visibility_by_role(): void
    {
        $org = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $admin): void {
            // 1. Gestor vê o sino.
            $browser->loginAs($gestor)
                ->visit(route('admin.dashboard'))
                ->waitFor('@notifications-bell')
                ->assertVisible('@notifications-bell');

            // 2. Aluno também vê.
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->assertVisible('@notifications-bell');

            // 3. Admin nunca vê (SPEC-13: Admin não recebe nenhum dos 4 tipos).
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertMissing('@notifications-bell');
        });
    }

    public function test_notification_bell_interaction_lifecycle(): void
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

        $this->browse(function (Browser $browser) use ($aluno, $notifications): void {
            // 1. Badge reflete as 12 não lidas.
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@notifications-bell')
                ->waitForTextIn('@notifications-badge', '12')
                // 2. Dropdown lista apenas as 10 mais recentes.
                ->click('@notifications-toggle')
                ->waitFor('@notifications-dropdown')
                ->assertVisible('@notifications-dropdown')
                ->assertVisible('@notifications-item-'.$notifications[12]->id)
                ->assertVisible('@notifications-item-'.$notifications[3]->id)
                ->assertMissing('@notifications-item-'.$notifications[1]->id)
                ->assertMissing('@notifications-item-'.$notifications[2]->id);

            $itemCount = $browser->script(
                "return document.querySelectorAll('[data-notifications-item]').length;"
            )[0];
            $this->assertSame(10, $itemCount);

            // 3. Clicar num item marca como lida e redireciona ao action_url.
            $browser->click('@notifications-item-'.$notifications[12]->id)
                ->waitForLocation(route('student.courses.index', [], false))
                ->assertPathIs(route('student.courses.index', [], false));

            $this->assertNotNull($notifications[12]->fresh()->read_at);
            $this->assertSame(
                11,
                DatabaseNotification::query()->whereNull('read_at')->count()
            );

            // 4. Badge já reflete 11 e "marcar todas como lidas" o zera.
            $browser->waitFor('@notifications-bell')
                ->waitForTextIn('@notifications-badge', '11')
                ->click('@notifications-toggle')
                ->waitFor('@notifications-dropdown')
                ->click('@notifications-mark-all-read')
                // O badge é ocultado por `.d-none` via `classList`
                // (NotificationBell.js), não por `style.display` inline —
                // `waitUntilMissing` afere visibilidade real e independe do
                // mecanismo de ocultação.
                ->waitUntilMissing('@notifications-badge')
                ->assertMissing('@notifications-badge');
        });

        $this->assertDatabaseCount('notifications', 12);
        $this->assertSame(0, DatabaseNotification::query()->whereNull('read_at')->count());
    }
}

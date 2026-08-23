<?php

namespace Tests\Browser\Theme;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ResponsiveShellTest extends DuskTestCase
{
    private function createNotification(User $user, string $message): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\EnrollmentConfirmedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'message' => $message,
                'action_url' => route('student.courses.index', [], false),
            ],
            'read_at' => null,
        ]);
    }

    public function test_desktop_shell_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Conselho Alpha']);
        $gestor = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Alpha']);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $notification = $this->createNotification($gestor, 'Bem-vindo ao sistema');

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->resize(1920, 1080)
                ->visit(route('admin.dashboard'))
                ->waitFor('@admin-dashboard')
                ->assertVisible('.sidebar')
                ->assertVisible('@sidebar-dashboard-link')
                ->assertVisible('@notifications-bell');
        });
    }

    public function test_mobile_shell_and_drawer_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Conselho Mobile']);
        $gestor = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Mobile']);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($gestor): void {
            $browser->loginAs($gestor)
                ->resize(375, 812)
                ->visit(route('admin.dashboard'))
                ->waitFor('@mobile-menu-button')
                ->click('@mobile-menu-button')
                ->waitFor('#mobile-sidebar.show')
                ->assertVisible('#mobile-sidebar')
                ->click('#mobile-sidebar .btn-close')
                ->waitUntilMissing('#mobile-sidebar.show');

            $browser->resize(1920, 1080);
        });
    }
}

<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-00 §5 — baseline Dusk smoke test used as the template other specs
 * copy from for their own browser suites.
 *
 * SPEC-00 is architecture/database only and ships no auth UI (no /login
 * route or view yet — that lands with a later, feature-specific spec), so
 * this smoke test is scoped to what actually exists today: the app boots
 * and serves a page, and Dusk's browser/session plumbing can authenticate
 * a user via the framework's built-in `loginAs()` helper (which doesn't
 * require a real login form). Once a login UI spec lands, its own Dusk
 * suite should assert the real form flow and this file can be retired.
 *
 * Uses DatabaseMigrations (not RefreshDatabase) because Dusk drives the
 * browser and the app server as separate HTTP processes/connections.
 */
class ExampleSmokeTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_the_homepage_renders(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertSourceHas('Laravel');
        });
    }

    public function test_dusk_can_authenticate_a_user_and_persist_the_session(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->loginAs($user)
                ->visit('/')
                ->assertAuthenticatedAs($user);
        });
    }
}

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
                ->assertTitle(config('app.name'))
                ->assertPresent('main')
                ->assertSeeIn('h1', "Let's get started")
                ->assertSee('Documentation');
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

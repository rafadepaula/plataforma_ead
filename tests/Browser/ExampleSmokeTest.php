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
        // SPEC-11 / RF11 — `/` now serves the public Landing Page
        // (`landing.show`), which displaced the Laravel default `welcome`
        // stub this smoke test originally asserted against.
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('main')
                ->assertSeeIn('h1', 'Capacitação técnica continuada, do jeito certo');
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

    public function test_the_landing_page_renders_its_sections_and_calls_to_action(): void
    {
        // SPEC-11 / RF11 — public Landing Page (`landing.show`), rendered
        // as a GUEST (no loginAs). Asserts the base dusk selectors, the
        // exact section headings, and that the login CTAs point to the
        // `login` route. Also asserts the contextual help button
        // (`<x-help-button key="landing" />`) is present — it renders
        // `disabled` when no article is seeded, so only presence is
        // asserted, never that it opens a modal.
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('main')
                ->assertVisible('@landing-headline')
                ->assertVisible('@landing-login-link')
                ->assertVisible('@landing-cta-login')
                ->assertPresent('@help-button-landing')
                ->assertAttribute('@landing-login-link', 'href', route('login'))
                ->assertAttribute('@landing-cta-login', 'href', route('login'))
                ->assertSee('Cursos e Trilhas')
                ->assertSee('Provas Interativas')
                ->assertSee('Certificados Oficiais')
                ->assertSee('Recebeu um convite?');
        });
    }
}

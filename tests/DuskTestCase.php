<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

/**
 * SPEC-14 / RN13 — dev-DB isolation for the Dusk suite.
 *
 * `vendor/bin/sail dusk` (Laravel\Dusk\Console\DuskCommand) natively backs
 * up the running `.env`, swaps in `.env.dusk.{app.environment}` — which
 * resolves to `.env.dusk.local` for the default `local` environment,
 * pointing DB_DATABASE at the dedicated `testing` MySQL database — and
 * restores the original `.env` once the suite finishes. That swap happens
 * entirely outside this class, before PHPUnit boots the test process, so
 * DuskTestCase itself must NOT read `.env`, connect to the database, or
 * otherwise assume a particular DB_DATABASE here: doing so would run ahead
 * of (or fight) Dusk's own environment swap and risk touching the
 * `plataforma_ead` dev database instead of `testing`.
 *
 * Every concrete test in tests/Browser/* is responsible for its own
 * `DatabaseMigrations` (or `DatabaseTruncation`) trait, which is what
 * actually confines migrations/truncation to whichever database the
 * swapped `.env` resolved to.
 */
abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        static::registerBootstrapModalMacros();
    }

    /**
     * Espera pela ABERTURA/FECHAMENTO REAL de um `bootstrap.Modal`.
     *
     * `waitFor('#id.show')` não basta: o Bootstrap adiciona `.show` no INÍCIO
     * da transição do `.fade` e só zera `_isTransitioning` no fim dela — e
     * `Modal.hide()` chamado enquanto `_isTransitioning` é `true` retorna sem
     * fazer nada (bootstrap/js/dist/modal.js). Um clique em "Cancelar" no meio
     * da transição, portanto, é silenciosamente engolido.
     *
     * O sinal usado é o `transform` do `.modal-dialog` — exatamente a transição
     * que o Bootstrap aguarda: `translate(0, -50px)` (matrix) durante a
     * animação, `none` quando ela termina.
     */
    protected static function registerBootstrapModalMacros(): void
    {
        if (! Browser::hasMacro('waitForModalShown')) {
            Browser::macro('waitForModalShown', function (string $modalId, ?int $seconds = null) {
                /** @var Browser $this */
                return $this->waitFor('#'.$modalId.'.show', $seconds)
                    ->waitUntil(
                        "getComputedStyle(document.querySelector('#".$modalId." .modal-dialog')).transform === 'none'",
                        $seconds
                    );
            });
        }

        if (! Browser::hasMacro('waitForModalClosed')) {
            Browser::macro('waitForModalClosed', function (string $modalId, ?int $seconds = null) {
                /** @var Browser $this */
                return $this->waitUntilMissing('#'.$modalId.'.show', $seconds)
                    ->waitUntilMissing('.modal-backdrop', $seconds);
            });
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}

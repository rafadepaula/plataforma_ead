<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\Attributes\BeforeClass;

/**
 * dev-DB isolation for the Dusk suite.
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
 * O isolamento de dados é centralizado aqui via `DatabaseTruncation`: as
 * migrações rodam **uma única vez** por execução da suíte e, entre os
 * métodos de teste, apenas um `TRUNCATE` rápido é emitido nas tabelas
 * tocadas — sempre na conexão ativa, ou seja, na base que o `.env`
 * trocado pelo Dusk resolveu. Classes em tests/Browser/* NÃO devem
 * declarar `DatabaseMigrations` (migrate:fresh por método, regressão de
 * desempenho) nem `RefreshDatabase` (transação invisível ao processo HTTP).
 */
abstract class DuskTestCase extends BaseTestCase
{
    use DatabaseTruncation;

    /**
     * Tabelas preservadas entre os testes.
     *
     * `roles`/`permissions`/`role_has_permissions` são dados de referência
     * populados pela migração `create_permission_tables` (que roda
     * `Role::findOrCreate()` para cada caso de `RolesEnum`), e não por um
     * seeder. Como as migrações rodam uma única vez por suíte sob
     * `DatabaseTruncation`, truncar essas tabelas deixaria a suíte inteira
     * quebrada a partir do segundo teste com
     * "There is no role named `admin` for guard `web`". As atribuições
     * usuário→role (`model_has_roles`) continuam sendo truncadas.
     *
     * @var array<int, string>
     */
    protected $exceptTables = [
        'migrations',
        'roles',
        'permissions',
        'role_has_permissions',
    ];

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
        static::registerCaseInsensitiveTextMacros();
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
     * Asserções de texto insensíveis à caixa.
     *
     * O Selenium lê texto RENDERIZADO: `text-transform: uppercase` no CSS faz
     * `getText()` devolver "INATIVO" para um DOM que contém "Inativo". Prender
     * a asserção à caixa renderizada acopla o teste a uma decisão puramente
     * apresentacional — foi o que quebrou 10 testes quando o tema Modernist
     * (caixa-alta em badge) saiu de cena, sem que nenhum comportamento tivesse
     * mudado.
     *
     * Estas macros afirmam o CONTEÚDO e ignoram a caixa. Onde a caixa é o
     * comportamento sob teste (o `overline`, único uso deliberado de
     * caixa-alta), continue usando `assertSee`/`assertSeeIn` normais.
     */
    protected static function registerCaseInsensitiveTextMacros(): void
    {
        if (! Browser::hasMacro('assertSeeIgnoringCase')) {
            Browser::macro('assertSeeIgnoringCase', function (string $text) {
                /** @var Browser $this */
                return $this->assertSeeInIgnoringCase('', $text);
            });
        }

        if (! Browser::hasMacro('assertDontSeeIgnoringCase')) {
            Browser::macro('assertDontSeeIgnoringCase', function (string $text) {
                /** @var Browser $this */
                $rendered = $this->resolver->findOrFail('')->getText();

                PHPUnit::assertFalse(
                    mb_stripos($rendered, $text) !== false,
                    'Saw unexpected text ['.$text.'] within element [body].'
                );

                return $this;
            });
        }

        if (! Browser::hasMacro('assertSeeInIgnoringCase')) {
            Browser::macro('assertSeeInIgnoringCase', function (string $selector, string $text) {
                /** @var Browser $this */
                $rendered = $this->resolver->findOrFail($selector)->getText();

                PHPUnit::assertTrue(
                    mb_stripos($rendered, $text) !== false,
                    'Did not see expected text ['.$text.'] within element ['
                        .$this->resolver->format($selector).']. Rendered: ['.$rendered.'].'
                );

                return $this;
            });
        }

        if (! Browser::hasMacro('assertTextEqualsIgnoringCase')) {
            Browser::macro('assertTextEqualsIgnoringCase', function (string $selector, string $text) {
                /** @var Browser $this */
                $rendered = trim($this->resolver->findOrFail($selector)->getText());

                PHPUnit::assertSame(
                    mb_strtolower($text),
                    mb_strtolower($rendered),
                    'Text within element ['.$this->resolver->format($selector).'] was ['.$rendered.'], expected ['.$text.'].'
                );

                return $this;
            });
        }

        if (! Browser::hasMacro('waitForTextInIgnoringCase')) {
            Browser::macro('waitForTextInIgnoringCase', function (string $selector, string $text, ?int $seconds = null) {
                /** @var Browser $this */
                $message = 'Waited %s seconds for text ['.$text.'] within element ['
                    .$this->resolver->format($selector).'].';

                return $this->waitUsing($seconds, 100, function () use ($selector, $text) {
                    /** @var Browser $this */
                    return mb_stripos($this->resolver->findOrFail($selector)->getText(), $text) !== false;
                }, $message);
            });
        }

        if (! Browser::hasMacro('waitForTextIgnoringCase')) {
            Browser::macro('waitForTextIgnoringCase', function (string $text, ?int $seconds = null) {
                /** @var Browser $this */
                return $this->waitForTextInIgnoringCase('', $text, $seconds);
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
            // A suite submete dezenas de formularios com campo de senha no
            // MESMO perfil de navegador. Sem isto o Chrome acumula sugestoes
            // de autofill e abre a bolha "Salvar senha?", que fica por cima
            // da tela e engole as teclas dos testes seguintes -- os cliques
            // continuam funcionando, entao a falha aparece adiante como um
            // formulario vazio, longe da causa.
            '--disable-autofill-keyboard-accessory-view',
            '--disable-features=AutofillServerCommunication,PasswordManagerOnboarding,PasswordManagerEnableAccountStore',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options->setExperimentalOption('prefs', [
                    'credentials_enable_service' => false,
                    'profile.password_manager_enabled' => false,
                    'profile.password_manager_leak_detection' => false,
                    'autofill.profile_enabled' => false,
                    'autofill.credit_card_enabled' => false,
                ])
            )
        );
    }
}

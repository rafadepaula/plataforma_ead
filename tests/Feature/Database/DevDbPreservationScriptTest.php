<?php

namespace Tests\Feature\Database;

use PDO;
use Tests\TestCase;

/**
 * Exercises the RN13 dev-DB-preservation guard  end to end
 * against the real, dedicated `testing` MySQL database — the same database
 * `.env.dusk.local`/`.env.dusk.ci` point Dusk at — via the
 * `harness:verify-dev-db-preserved` wrapper around
 * scripts/verify-dev-db-preserved.php.
 *
 * A disposable fixture table is created per test so this suite never reads
 * or writes anything the application itself owns in that schema.
 */
class DevDbPreservationScriptTest extends TestCase
{
    private PDO $pdo;

    private string $database = 'testing';

    private string $envPath;

    private string $outPath;

    private string $fixtureTable;

    protected function setUp(): void
    {
        parent::setUp();

        $host = $this->resolveDbHost();
        $port = getenv('DB_PORT') ?: '3306';
        $username = getenv('DB_USERNAME') ?: 'sail';
        $password = getenv('DB_PASSWORD') ?: 'password';

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $this->database),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->fixtureTable = 'dev_db_guard_fixture_'.uniqid();
        $this->pdo->exec("CREATE TABLE `{$this->fixtureTable}` (id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(50))");
        $this->pdo->exec("INSERT INTO `{$this->fixtureTable}` (label) VALUES ('initial')");

        $this->envPath = storage_path('framework/testing/.env.dev-db-guard-test-'.uniqid());
        file_put_contents($this->envPath, implode("\n", [
            "DB_HOST={$host}",
            "DB_PORT={$port}",
            "DB_DATABASE={$this->database}",
            "DB_USERNAME={$username}",
            "DB_PASSWORD={$password}",
        ]));

        $this->outPath = storage_path('framework/testing/dusk-db-snapshot-test-'.uniqid().'.json');
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS `{$this->fixtureTable}`");
        @unlink($this->envPath);
        @unlink($this->outPath);

        parent::tearDown();
    }

    /**
     * Resolves a host that is actually reachable from the current process:
     * the Sail Docker network hostname when available, `127.0.0.1` on a
     * bare-metal CI runner where the `mysql` service is port-mapped instead.
     */
    private function resolveDbHost(): string
    {
        if ($host = getenv('DB_HOST')) {
            return $host;
        }

        return @gethostbyname('mysql') !== 'mysql' ? 'mysql' : '127.0.0.1';
    }

    /**
     * @return array<string, string>
     */
    private function commandOptions(string $subcommand): array
    {
        return [
            'subcommand' => $subcommand,
            '--env' => $this->envPath,
            '--database' => $this->database,
            '--out' => $this->outPath,
        ];
    }

    public function test_snapshot_subcommand_writes_a_json_snapshot_of_the_database(): void
    {
        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('snapshot'))
            ->assertExitCode(0);

        $this->assertFileExists($this->outPath);

        $snapshot = json_decode((string) file_get_contents($this->outPath), true);

        $this->assertSame($this->database, $snapshot['database']);
        $this->assertArrayHasKey($this->fixtureTable, $snapshot['tables']);
        $this->assertSame(1, $snapshot['tables'][$this->fixtureTable]['rows']);
    }

    public function test_verify_subcommand_succeeds_when_database_is_unchanged(): void
    {
        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('snapshot'))
            ->assertExitCode(0);

        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('verify'))
            ->expectsOutputToContain('permanece intacto')
            ->assertExitCode(0);
    }

    public function test_verify_subcommand_fails_when_a_row_is_mutated_after_the_snapshot(): void
    {
        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('snapshot'))
            ->assertExitCode(0);

        $this->pdo->exec("INSERT INTO `{$this->fixtureTable}` (label) VALUES ('mutated-by-dusk')");

        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('verify'))
            ->expectsOutputToContain($this->fixtureTable)
            ->assertExitCode(1);
    }

    public function test_verify_subcommand_fails_when_a_table_is_dropped_after_the_snapshot(): void
    {
        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('snapshot'))
            ->assertExitCode(0);

        $this->pdo->exec("DROP TABLE `{$this->fixtureTable}`");

        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('verify'))
            ->expectsOutputToContain('desapareceu')
            ->assertExitCode(1);

        // Recreate so tearDown's DROP TABLE IF EXISTS stays a no-op cleanup.
        $this->pdo->exec("CREATE TABLE `{$this->fixtureTable}` (id INT PRIMARY KEY AUTO_INCREMENT)");
    }

    public function test_verify_subcommand_fails_when_a_new_table_appears_after_the_snapshot(): void
    {
        $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('snapshot'))
            ->assertExitCode(0);

        $newTable = $this->fixtureTable.'_extra';
        $this->pdo->exec("CREATE TABLE `{$newTable}` (id INT PRIMARY KEY)");

        try {
            $this->artisan('harness:verify-dev-db-preserved', $this->commandOptions('verify'))
                ->expectsOutputToContain('surgiu')
                ->assertExitCode(1);
        } finally {
            $this->pdo->exec("DROP TABLE `{$newTable}`");
        }
    }
}

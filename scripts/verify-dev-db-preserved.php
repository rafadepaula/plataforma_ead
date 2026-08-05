<?php

/**
 * Dev-DB preservation guard for SPEC-14 (RN13).
 *
 * Laravel Dusk swaps `.env` for `.env.dusk.local` (which points at the
 * dedicated `testing` MySQL database) for the duration of `sail dusk`, then
 * restores the original `.env` afterwards. This script is the *practical*
 * proof step from SPEC-14 §4 ("Validação prática... confirmando que o banco
 * plataforma_ead não sofreu alterações"): it snapshots a checksum + row
 * count per table of the dev database (`plataforma_ead` by default) before
 * a Dusk run, then re-checks after, failing loudly on any diff.
 *
 * It talks to MySQL directly via PDO and never boots the Laravel
 * application, so it is safe to run before/after `.env` gets swapped by the
 * `dusk` Artisan command.
 *
 * Usage:
 *   php scripts/verify-dev-db-preserved.php snapshot [--env=.env] [--database=plataforma_ead] [--out=storage/framework/testing/dusk-db-snapshot.json]
 *   vendor/bin/sail dusk
 *   php scripts/verify-dev-db-preserved.php verify [--env=.env] [--database=plataforma_ead] [--out=storage/framework/testing/dusk-db-snapshot.json]
 *
 * Exit codes:
 *  - 0: snapshot written, or verify found no differences.
 *  - 1: usage/connection error, or verify found the dev database changed.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Parsed manually (rather than via getopt()) because getopt() stops
// scanning at the first positional token — the "snapshot"/"verify"
// subcommand — and would silently drop every flag that follows it.
$command = $argv[1] ?? null;
$options = [];

foreach (array_slice($argv, 2) as $arg) {
    if ($arg === '--help') {
        $options['help'] = false;
    } elseif (str_starts_with($arg, '--') && str_contains($arg, '=')) {
        [$key, $value] = explode('=', substr($arg, 2), 2);
        $options[$key] = $value;
    }
}

if (isset($options['help']) || ! in_array($command, ['snapshot', 'verify'], true)) {
    fwrite(STDOUT, "Guarda de preservação do banco de desenvolvimento (SPEC-14 / RN13)\n");
    fwrite(STDOUT, "Uso: php scripts/verify-dev-db-preserved.php <snapshot|verify> [opções]\n\n");
    fwrite(STDOUT, "Opções:\n");
    fwrite(STDOUT, "  --env=<caminho>       Arquivo .env a ler as credenciais MySQL (padrão: .env)\n");
    fwrite(STDOUT, "  --database=<nome>     Banco a inspecionar (padrão: DB_DATABASE do .env)\n");
    fwrite(STDOUT, "  --out=<caminho>       Onde salvar/ler o snapshot JSON (padrão: storage/framework/testing/dusk-db-snapshot.json)\n");
    exit($command === null ? 1 : 0);
}

$basePath = dirname(__DIR__);
$envFile = $options['env'] ?? '.env';
$envPath = str_starts_with($envFile, '/') ? $envFile : $basePath.'/'.$envFile;

if (! file_exists($envPath)) {
    fwrite(STDERR, sprintf("Erro: arquivo de ambiente não encontrado em %s.\n", $envPath));
    exit(1);
}

$dotenv = Dotenv\Dotenv::createArrayBacked(dirname($envPath), basename($envPath));
$env = $dotenv->load();

$database = $options['database'] ?? ($env['DB_DATABASE'] ?? null);
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$username = $env['DB_USERNAME'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';

if (! $database) {
    fwrite(STDERR, "Erro: nenhum banco de dados resolvido (defina --database ou DB_DATABASE no .env informado).\n");
    exit(1);
}

if (str_contains($database, 'testing')) {
    fwrite(STDERR, sprintf(
        "Aviso: o banco resolvido (\"%s\") parece ser o banco efêmero do Dusk, não o de desenvolvimento.\n".
        "Este script existe para provar que %s NÃO foi alterado — confirme o --database usado.\n",
        $database,
        'plataforma_ead'
    ));
}

$outPath = $options['out'] ?? ($basePath.'/storage/framework/testing/dusk-db-snapshot.json');
if (! str_starts_with($outPath, '/')) {
    $outPath = $basePath.'/'.$outPath;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $exception) {
    fwrite(STDERR, sprintf("Erro: falha ao conectar em %s@%s:%s/%s — %s\n", $username, $host, $port, $database, $exception->getMessage()));
    exit(1);
}

/**
 * @return array<string, array{checksum: int|string|null, rows: int}>
 */
function snapshotDatabase(PDO $pdo, string $database): array
{
    $tables = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = :database ORDER BY table_name');
    $tables->execute(['database' => $database]);

    $snapshot = [];

    foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $checksumRow = $pdo->query(sprintf('CHECKSUM TABLE `%s`', $table))->fetch(PDO::FETCH_ASSOC);
        $rowCount = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', $table))->fetchColumn();

        $snapshot[$table] = [
            'checksum' => $checksumRow['Checksum'] ?? null,
            'rows' => $rowCount,
        ];
    }

    return $snapshot;
}

if ($command === 'snapshot') {
    $snapshot = snapshotDatabase($pdo, $database);

    $directory = dirname($outPath);
    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        fwrite(STDERR, sprintf("Erro: não foi possível criar o diretório %s.\n", $directory));
        exit(1);
    }

    file_put_contents($outPath, json_encode([
        'database' => $database,
        'taken_at' => date(DATE_ATOM),
        'tables' => $snapshot,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    fwrite(STDOUT, sprintf(
        "Snapshot salvo em %s (%d tabelas do banco \"%s\").\n",
        $outPath,
        count($snapshot),
        $database
    ));
    exit(0);
}

// verify
if (! file_exists($outPath)) {
    fwrite(STDERR, sprintf(
        "Erro: nenhum snapshot encontrado em %s. Rode o subcomando \"snapshot\" antes de \"vendor/bin/sail dusk\".\n",
        $outPath
    ));
    exit(1);
}

$before = json_decode((string) file_get_contents($outPath), true);
$beforeTables = $before['tables'] ?? [];
$after = snapshotDatabase($pdo, $database);

$diffs = [];

foreach ($beforeTables as $table => $beforeState) {
    if (! array_key_exists($table, $after)) {
        $diffs[] = sprintf('Tabela "%s" existia antes e desapareceu.', $table);

        continue;
    }

    $afterState = $after[$table];

    if ($beforeState['checksum'] !== $afterState['checksum'] || $beforeState['rows'] !== $afterState['rows']) {
        $diffs[] = sprintf(
            'Tabela "%s" mudou: checksum %s -> %s, linhas %d -> %d.',
            $table,
            $beforeState['checksum'] ?? 'null',
            $afterState['checksum'] ?? 'null',
            $beforeState['rows'],
            $afterState['rows']
        );
    }
}

foreach ($after as $table => $afterState) {
    if (! array_key_exists($table, $beforeTables)) {
        $diffs[] = sprintf('Tabela "%s" surgiu após o snapshot (não existia antes).', $table);
    }
}

if ($diffs !== []) {
    fwrite(STDERR, sprintf("FALHA: o banco \"%s\" foi alterado durante a execução do Dusk:\n", $database));
    foreach ($diffs as $diff) {
        fwrite(STDERR, ' - '.$diff."\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf(
    "SUCESSO: o banco \"%s\" permanece intacto (%d tabelas verificadas, RN13 preservada).\n",
    $database,
    count($after)
));
exit(0);

<?php

/**
 * Coverage gate used by quality guardrails.
 *
 * Parses storage/coverage/clover.xml, calculates line coverage percentage,
 * and fails (exit code 1) when line coverage drops below the required
 * threshold (default 95.00%).
 *
 * Usage:
 *   php scripts/check-coverage.php [--min=95.00] [--report=storage/coverage/clover.xml]
 */

declare(strict_types=1);

$options = getopt('', ['min::', 'report::']);

$minimumCoverage = isset($options['min']) ? (float) $options['min'] : 95.00;
$reportPath = $options['report'] ?? (__DIR__.'/../storage/coverage/clover.xml');

if (! file_exists($reportPath)) {
    fwrite(STDERR, sprintf("Erro: Arquivo clover.xml não encontrado em %s.\n", $reportPath));
    exit(1);
}

$xml = simplexml_load_file($reportPath);

if ($xml === false) {
    fwrite(STDERR, sprintf("Erro: Não foi possível carregar o arquivo %s.\n", $reportPath));
    exit(1);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, sprintf("Erro: Nó <metrics> de projeto não encontrado em %s.\n", $reportPath));
    exit(1);
}

$totalStatements = (float) ($metrics['elements'] ?? 0);
$coveredStatements = (float) ($metrics['coveredelements'] ?? 0);

$coverage = ($totalStatements > 0) ? ($coveredStatements / $totalStatements) * 100 : 0.0;

fwrite(STDOUT, sprintf("Cobertura de Código Atual: %.2f%%\n", $coverage));

if ($coverage < $minimumCoverage) {
    fwrite(STDERR, sprintf("FALHA: Cobertura mínima de %.2f%% não atingida (Atual: %.2f%%).\n", $minimumCoverage, $coverage));
    exit(1);
}

fwrite(STDOUT, sprintf("SUCESSO: Cobertura dentro dos padrões estipulados! (%.2f%% >= %.2f%%)\n", $coverage, $minimumCoverage));
exit(0);

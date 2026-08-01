<?php

/**
 * Coverage gate used by SPEC-00 §5 quality guardrails.
 *
 * Runs the PHPUnit suite with code coverage enabled (Xdebug or PCOV must be
 * available) and fails (non-zero exit code) when line coverage drops below
 * the configured minimum threshold (default 95.00%).
 *
 * Usage:
 *   php scripts/check-coverage.php [--min=95] [--report=storage/coverage/clover.xml]
 *
 * Composer shortcut:
 *   composer test-coverage
 */

declare(strict_types=1);

$options = getopt('', ['min::', 'report::']);

$minimumCoverage = isset($options['min']) ? (float) $options['min'] : 95.00;
$reportPath = $options['report'] ?? (__DIR__.'/../storage/coverage/clover.xml');
$reportPath = realpath(dirname($reportPath)) !== false
    ? rtrim((string) realpath(dirname($reportPath)), '/').'/'.basename($reportPath)
    : $reportPath;

$projectRoot = dirname(__DIR__);
$phpunitBinary = $projectRoot.'/vendor/bin/phpunit';

if (! is_file($phpunitBinary)) {
    fwrite(STDERR, '[check-coverage] vendor/bin/phpunit not found. Run composer install first.'.PHP_EOL);
    exit(1);
}

@mkdir(dirname($reportPath), 0755, true);

$command = sprintf(
    '%s --coverage-clover=%s',
    escapeshellarg($phpunitBinary),
    escapeshellarg($reportPath)
);

fwrite(STDOUT, "[check-coverage] Running: {$command}".PHP_EOL);

passthru($command, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "[check-coverage] PHPUnit run failed (exit code {$exitCode}). Fix failing tests before checking coverage.".PHP_EOL);
    exit($exitCode);
}

if (! is_file($reportPath)) {
    fwrite(STDERR, "[check-coverage] Coverage report not generated at {$reportPath}. Is a coverage driver (Xdebug/PCOV) enabled?".PHP_EOL);
    exit(1);
}

$xml = simplexml_load_file($reportPath);

if ($xml === false) {
    fwrite(STDERR, "[check-coverage] Unable to parse coverage report at {$reportPath}.".PHP_EOL);
    exit(1);
}

$metrics = $xml->xpath('//project/metrics');

if ($metrics === false || count($metrics) === 0) {
    fwrite(STDERR, "[check-coverage] Coverage report at {$reportPath} has no <metrics> node.".PHP_EOL);
    exit(1);
}

$totals = ['elements' => 0, 'coveredelements' => 0];

foreach ($xml->xpath('//metrics') as $metric) {
    $totals['elements'] += (int) $metric['elements'];
    $totals['coveredelements'] += (int) $metric['coveredelements'];
}

// The project-level <metrics> node already aggregates every file, so prefer
// it when present to avoid double counting per-file/per-class nodes.
$projectMetric = $metrics[0];
$totalElements = (int) $projectMetric['elements'];
$coveredElements = (int) $projectMetric['coveredelements'];

if ($totalElements === 0) {
    fwrite(STDERR, '[check-coverage] No coverable elements found in report.'.PHP_EOL);
    exit(1);
}

$coveragePercentage = round(($coveredElements / $totalElements) * 100, 2);

fwrite(STDOUT, sprintf(
    '[check-coverage] Coverage: %.2f%% (%d/%d elements). Minimum required: %.2f%%.'.PHP_EOL,
    $coveragePercentage,
    $coveredElements,
    $totalElements,
    $minimumCoverage
));

if ($coveragePercentage < $minimumCoverage) {
    fwrite(STDERR, sprintf(
        '[check-coverage] FAILED — coverage %.2f%% is below the required %.2f%% threshold (SPEC-00 §5).'.PHP_EOL,
        $coveragePercentage,
        $minimumCoverage
    ));
    exit(1);
}

fwrite(STDOUT, '[check-coverage] PASSED — coverage meets the minimum threshold.'.PHP_EOL);
exit(0);

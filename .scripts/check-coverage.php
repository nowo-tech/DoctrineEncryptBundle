<?php

declare(strict_types=1);

/**
 * Checks that coverage.xml meets the minimum coverage threshold (default 100%).
 * Exit code 0 if coverage >= threshold, 1 otherwise.
 *
 * Usage: php .scripts/check-coverage.php [coverage.xml] [--min-percent=N]
 */
$coverageFile = $argv[1] ?? 'coverage.xml';
$minPercent   = 100;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--min-percent=')) {
        $minPercent = (int) substr($arg, strlen('--min-percent='));
        break;
    }
}

if (!is_file($coverageFile)) {
    fwrite(\STDERR, "Coverage file not found: {$coverageFile}\n");
    exit(2);
}

$xml = @simplexml_load_file($coverageFile);
if ($xml === false) {
    fwrite(\STDERR, "Invalid XML in {$coverageFile}\n");
    exit(2);
}

$projectMetrics = $xml->project->metrics;
if (!$projectMetrics) {
    fwrite(\STDERR, "No project metrics in {$coverageFile}\n");
    exit(2);
}

$statements        = (int) (string) $projectMetrics['statements'];
$coveredStatements = (int) (string) $projectMetrics['coveredstatements'];
$elements          = (int) (string) $projectMetrics['elements'];
$coveredElements   = (int) (string) $projectMetrics['coveredelements'];

$stmtPercent = $statements > 0 ? round($coveredStatements / $statements * 100, 2) : 100.0;
$elemPercent = $elements > 0 ? round($coveredElements / $elements * 100, 2) : 100.0;

echo sprintf(
    'Coverage: statements %d/%d (%.2f%%), elements %d/%d (%.2f%%)' . \PHP_EOL,
    $coveredStatements,
    $statements,
    $stmtPercent,
    $coveredElements,
    $elements,
    $elemPercent,
);

if ($stmtPercent < $minPercent || $elemPercent < $minPercent) {
    fwrite(\STDERR, sprintf("Coverage below %d%% threshold.\n", $minPercent));
    exit(1);
}

echo "Coverage threshold {$minPercent}% met.\n";
exit(0);

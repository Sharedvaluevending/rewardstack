#!/usr/bin/env php
<?php
/**
 * Reads build/coverage/clover.xml (from a full test run with coverage) and writes
 * a persistent record to docs/coverage-last/ so we know exactly:
 * - What lines are covered (the X%)
 * - What lines are NOT covered (the other 100-X%)
 * Run after: php artisan test --coverage  (full suite, no filter)
 *
 * Usage: php scripts/save-coverage-summary.php
 */

$repoRoot = dirname(__DIR__);
$cloverPath = $repoRoot . '/build/coverage/clover.xml';
$outDir = $repoRoot . '/docs/coverage-last';

if (!is_file($cloverPath)) {
    fwrite(STDERR, "Missing {$cloverPath}. Run full coverage first: php artisan test --coverage\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Failed to parse clover.xml\n");
    exit(1);
}

$project = $xml->project;
$projectMetrics = $project->metrics;
$totalStatements = (int) $projectMetrics['statements'];
$coveredStatements = (int) $projectMetrics['coveredstatements'];
$totalPercent = $totalStatements > 0
    ? round(100 * $coveredStatements / $totalStatements, 2)
    : 0;

$files = [];
foreach ($project->file as $file) {
    $fullPath = (string) $file['name'];
    $relativePath = str_replace($repoRoot . '/', '', $fullPath);
    if (strpos($relativePath, 'app/') !== 0) {
        continue;
    }
    $metrics = $file->metrics;
    $stmts = (int) $metrics['statements'];
    $covered = (int) $metrics['coveredstatements'];
    $pct = $stmts > 0 ? round(100 * $covered / $stmts, 2) : 100;

    $uncoveredLines = [];
    foreach ($file->line as $line) {
        $count = (int) $line['count'];
        if ($count === 0 && in_array((string) $line['type'], ['stmt', 'method'], true)) {
            $uncoveredLines[] = (int) $line['num'];
        }
    }
    sort($uncoveredLines);

    $files[$relativePath] = [
        'percent' => $pct,
        'covered' => $covered,
        'total' => $stmts,
        'uncovered_line_numbers' => $uncoveredLines,
    ];
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$summary = [
    'generated_at' => date('c'),
    'source' => 'build/coverage/clover.xml',
    'total_statements' => $totalStatements,
    'covered_statements' => $coveredStatements,
    'line_coverage_percent' => $totalPercent,
    'files' => $files,
];

file_put_contents(
    $outDir . '/summary.json',
    json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// UNCOVERED.md: what the "other (100-X)%" is — files with <100%, worst first, with line numbers
$byPercent = $files;
uasort($byPercent, function ($a, $b) {
    return $a['percent'] <=> $b['percent'];
});

$md = "# Uncovered code (the \"other " . (100 - $totalPercent) . "%\")\n\n";
$md .= "Generated: " . $summary['generated_at'] . " from full coverage run.\n\n";
$md .= "**Total line coverage: {$totalPercent}%** ({$coveredStatements} / {$totalStatements} statements)\n\n";
$md .= "Below: every file under 100%, sorted by coverage (worst first). Uncovered line numbers listed so we know exactly what to test next.\n\n";
$md .= "---\n\n";

foreach ($byPercent as $path => $data) {
    if ($data['percent'] >= 100) {
        continue;
    }
    $md .= "## {$path}\n";
    $md .= "**{$data['percent']}%** ({$data['covered']}/{$data['total']} lines)\n\n";
    if (!empty($data['uncovered_line_numbers'])) {
        $lines = $data['uncovered_line_numbers'];
        $md .= "Uncovered line numbers: " . implode(', ', array_slice($lines, 0, 50));
        if (count($lines) > 50) {
            $md .= " … +" . (count($lines) - 50) . " more";
        }
        $md .= "\n\n";
    }
}

file_put_contents($outDir . '/UNCOVERED.md', $md);

// COVERED.md: what the X% is — files we have covered (for quick "did we already test this?")
$coveredList = [];
foreach ($files as $path => $data) {
    if ($data['total'] > 0 && $data['percent'] > 0) {
        $coveredList[$path] = $data['percent'];
    }
}
arsort($coveredList);
$mdCovered = "# Covered code (the \"{$totalPercent}%\")\n\n";
$mdCovered .= "Generated: " . $summary['generated_at'] . "\n\n";
$mdCovered .= "Files that have at least some coverage. Use this to avoid re-testing the same code.\n\n";
$mdCovered .= "| File | Coverage |\n|------|----------|\n";
foreach ($coveredList as $path => $pct) {
    $mdCovered .= "| {$path} | {$pct}% |\n";
}
file_put_contents($outDir . '/COVERED.md', $mdCovered);

echo "Saved to docs/coverage-last/\n";
echo "  summary.json   - machine-readable (totals + per-file % and uncovered line numbers)\n";
echo "  UNCOVERED.md   - the other " . (100 - $totalPercent) . "%: files and line numbers not covered\n";
echo "  COVERED.md     - the {$totalPercent}%: files we already touch (so we don't retest the same thing)\n";
echo "\nLine coverage: {$totalPercent}% ({$coveredStatements}/{$totalStatements}). Commit docs/coverage-last/ to keep this record.\n";

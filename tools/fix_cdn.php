<?php
// One-shot: replace all CDN references with local assets
// Run: php tools/fix_cdn.php

$base = __DIR__ . '/..';
$utf8 = 'UTF-8';

$replacements = [
    // ── Chart.js ──
    "https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"
        => "/assets/js/chart.umd.min.js",

    // ── Google Fonts @import (3 variants used in the codebase) ──
    "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');"
        => "@import url('/assets/fonts/inter.css');",

    "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');"
        => "@import url('/assets/fonts/inter.css');",
];

$targets = [
    // Chart.js
    $base . '/public/index.php',
    $base . '/public/accounting/index.php',
    // Google Fonts
    $base . '/templates/layout.php',
    $base . '/public/login.php',
    $base . '/public/setup.php',
];

foreach ($targets as $file) {
    if (!file_exists($file)) { echo "MISSING: $file\n"; continue; }
    $content  = file_get_contents($file);
    $original = $content;
    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename(dirname($file)) . '/' . basename($file) . "\n";
    } else {
        echo "No change: " . basename(dirname($file)) . '/' . basename($file) . "\n";
    }
}
echo "Done.\n";

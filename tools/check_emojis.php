<?php
// Scan all PHP files for remaining emoji characters
$dirs = [
    __DIR__ . '/../public',
    __DIR__ . '/../templates',
];

$found = [];

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') continue;
        $lines = file($file->getPathname());
        foreach ($lines as $n => $line) {
            // Match emoji Unicode blocks (U+1F000–U+1FFFF, U+2600–U+27BF misc symbols, U+2300–U+23FF)
            if (preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{FE00}-\x{FEFF}]/u', $line)) {
                // Skip if it's already wrapped in an fa- icon tag on same line
                $clean = preg_replace('/<i class="fa-[^"]+"><\/i>/u', '', $line);
                if (preg_match('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}]/u', $clean)) {
                    $rel = str_replace([__DIR__ . '/../public/', __DIR__ . '/../templates/'], ['public/', 'templates/'], $file->getPathname());
                    $found[] = sprintf("%s:%d  %s", str_replace('\\', '/', $rel), $n + 1, trim($line));
                }
            }
        }
    }
}

if (empty($found)) {
    echo "✓ Aucun emoji trouvé — toutes les icônes sont Font Awesome.\n";
} else {
    echo count($found) . " occurrence(s) trouvée(s) :\n\n";
    foreach ($found as $f) echo $f . "\n";
}

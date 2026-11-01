<?php

/**
 * Chargeur .env minimaliste aucune dépendance externe.
 * Chargé automatiquement via composer.json autoload.files.
 *
 * Priorité : variables d'environnement système > .env > valeurs par défaut du code.
 * Les variables déjà présentes dans $_ENV ou putenv ne sont jamais écrasées.
 */

declare(strict_types=1);

(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        // Supprimer les guillemets simples ou doubles entourant la valeur
        if (
            strlen($val) >= 2
            && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        if ($key === '') {
            continue;
        }

        // Ne pas écraser une variable déjà définie par le système
        if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
            continue;
        }

        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
})();

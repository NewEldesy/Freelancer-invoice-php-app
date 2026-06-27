<?php
// Final pass: replace all remaining emojis with FA icons
$base = __DIR__ . '/..';
$utf8 = new SplFileInfo($base); // just to have a reference

$files = [

// ── accounting/index.php ──
$base . '/public/accounting/index.php' => [
    '>🖨️ Imprimer / PDF</button>' => '><i class="fa-solid fa-print"></i> Imprimer / PDF</button>',
],

// ── admin/users/create.php ──
$base . '/public/admin/users/create.php' => [
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
    '>💾 Créer l\'utilisateur</button>' => '><i class="fa-solid fa-floppy-disk"></i> Créer l\'utilisateur</button>',
],

// ── admin/users.php ──
$base . '/public/admin/users.php' => [
    'title="Supprimer">🗑️</button>' => 'title="Supprimer"><i class="fa-solid fa-trash"></i></button>',
],

// ── client/create.php ──
$base . '/public/client/create.php' => [
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
],

// ── client/edit.php ──
$base . '/public/client/edit.php' => [
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
],

// ── client/index.php ──
$base . '/public/client/index.php' => [
    '<div style="font-size:2rem;margin-bottom:12px">👥</div>' => '<div style="font-size:2rem;margin-bottom:12px"><i class="fa-solid fa-users"></i></div>',
    '>✅ <?= htmlspecialchars($flash)' => '><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash)',
],

// ── pipeline/edit.php ──
$base . '/public/pipeline/edit.php' => [
    '>🧾 Convertir en facture</button>' => '><i class="fa-solid fa-file-invoice"></i> Convertir en facture</button>',
    '>✓ Opportunité convertie' => '><i class="fa-solid fa-circle-check"></i> Opportunité convertie',
],

// ── pipeline/index.php ──
$base . '/public/pipeline/index.php' => [
    "'icon' => '👤'" => "'icon' => '<i class=\"fa-solid fa-user\"></i>'",
    "'icon' => '📨'" => "'icon' => '<i class=\"fa-solid fa-envelope\"></i>'",
    "'icon' => '🤝'" => "'icon' => '<i class=\"fa-solid fa-handshake\"></i>'",
    '<div class="opp-client">👤 <?=' => '<div class="opp-client"><i class="fa-solid fa-user"></i> <?=',
],

// ── project/index.php ──
$base . '/public/project/index.php' => [
    "'icon' => '⏳'" => "'icon' => '<i class=\"fa-solid fa-hourglass-half\"></i>'",
    "'icon' => '🔨'" => "'icon' => '<i class=\"fa-solid fa-hammer\"></i>'",
],

// ── services/create.php ──
$base . '/public/services/create.php' => [
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
],

// ── services/edit.php ──
$base . '/public/services/edit.php' => [
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
],

// ── services/index.php ──
$base . '/public/services/index.php' => [
    '<div style="font-size:2rem;margin-bottom:12px">📦</div>' => '<div style="font-size:2rem;margin-bottom:12px"><i class="fa-solid fa-box-open"></i></div>',
    '>✅ <?= htmlspecialchars($flash)' => '><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash)',
],

// ── settings.php ──
$base . '/public/settings.php' => [
    '✅ <?= htmlspecialchars($flashSuccess)' => '<i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess)',
    '⚠️ <?= htmlspecialchars($flashError)' => '<i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashError)',
    "'enterprise' => '🏢'" => "'enterprise' => '<i class=\"fa-solid fa-building\"></i>'",
    "'free'       => '🔓'" => "'free'       => '<i class=\"fa-solid fa-lock-open\"></i>'",
    "default      => '❌'" => "default      => '<i class=\"fa-solid fa-circle-xmark\"></i>'",
    '🔑 <?= LicenseService::isFree()' => '<i class="fa-solid fa-key"></i> <?= LicenseService::isFree()',
],

// ── setup.php ──
$base . '/public/setup.php' => [
    '<div class="logo-icon">🧾</div>' => '<div class="logo-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>',
    '<span class="badge">⚙️ Configuration initiale</span>' => '<span class="badge"><i class="fa-solid fa-gear"></i> Configuration initiale</span>',
    '>⚠ <?= implode(\'<br>⚠ \'' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= implode(\'<br><i class="fa-solid fa-triangle-exclamation"></i> \'',
    '<div class="section-label">🔑 Compte ISSU DEV' => '<div class="section-label"><i class="fa-solid fa-key"></i> Compte ISSU DEV',
    '<div class="section-label gold">👤 Compte administrateur' => '<div class="section-label gold"><i class="fa-solid fa-user"></i> Compte administrateur',
    '>✅ Clés générées automatiquement' => '><i class="fa-solid fa-circle-check"></i> Clés générées automatiquement',
    '>🚀 Initialiser l\'application</button>' => '><i class="fa-solid fa-rocket"></i> Initialiser l\'application</button>',
],

// ── login.php ──
$base . '/public/login.php' => [
    '<div class="logo-icon">🧾</div>' => '<div class="logo-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>',
    '>⚠ <?= htmlspecialchars($error)' => '><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error)',
],

// ── activate.php ──
$base . '/public/activate.php' => [
    '<div class="brand-logo">📋</div>' => '<div class="brand-logo"><i class="fa-solid fa-file-invoice-dollar"></i></div>',
    '<h1>🔴 Votre licence a expiré</h1>' => '<h1><i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i> Votre licence a expiré</h1>',
    '⚠️ <?= htmlspecialchars($error)' => '<i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error)',
],

// ── superadmin/keys.php ──
$base . '/public/superadmin/keys.php' => [
    '>✅ <?= htmlspecialchars($flash)' => '><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash)',
    "['Total clés', \$stats['total'], 'var(--navy)', '🔑']" => "['Total clés', \$stats['total'], 'var(--navy)', '<i class=\"fa-solid fa-key\"></i>']",
    "['Disponibles', \$stats['available'], '#059669', '✅']" => "['Disponibles', \$stats['available'], '#059669', '<i class=\"fa-solid fa-circle-check\"></i>']",
    "['Utilisées', \$stats['used'], '#dc2626', '📌']" => "['Utilisées', \$stats['used'], '#dc2626', '<i class=\"fa-solid fa-thumbtack\"></i>']",
    '>➕ Générer une nouvelle clé' => '><i class="fa-solid fa-plus"></i> Générer une nouvelle clé',
    '>🔑 Générer' => '><i class="fa-solid fa-key"></i> Générer',
    '><span class="badge-used">📌 Utilisée</span>' => '><span class="badge-used"><i class="fa-solid fa-thumbtack"></i> Utilisée</span>',
    '><span class="badge-free">✅ Disponible</span>' => '><span class="badge-free"><i class="fa-solid fa-circle-check"></i> Disponible</span>',
    '>📋 Clé copiée !' => '><i class="fa-solid fa-check"></i> Clé copiée !',
],

// ── templates/layout.php ──
$base . '/templates/layout.php' => [
    ">✓ <?= htmlspecialchars(\$flashSuccess)" => '><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess)',
    ">⚠ <?= htmlspecialchars(\$flashError)"  => '><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashError)',
],

];

$utf8enc = new \stdClass();
foreach ($files as $path => $map) {
    if (!file_exists($path)) { echo "MISSING: $path\n"; continue; }
    $content  = file_get_contents($path);
    $original = $content;
    foreach ($map as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: " . basename(dirname($path)) . '/' . basename($path) . "\n";
    } else {
        echo "No change: " . basename(dirname($path)) . '/' . basename($path) . "\n";
    }
}
echo "Done.\n";

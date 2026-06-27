<?php
// One-shot script: replace emojis with Font Awesome icons in target pages
// Run: php tools/fix_icons.php

$base = __DIR__ . '/../public';

$map = [
    // ── Boutons icône seule ──
    '>✏️</a>'         => '><i class="fa-solid fa-pen-to-square"></i></a>',
    '>✏️</button>'    => '><i class="fa-solid fa-pen-to-square"></i></button>',
    '>🗑️</button>'    => '><i class="fa-solid fa-trash"></i></button>',
    '>🗑️</a>'         => '><i class="fa-solid fa-trash"></i></a>',
    // ── Boutons avec texte ──
    '⧉</button>'      => '<i class="fa-solid fa-clone"></i></button>',
    '📄</a>'           => '<i class="fa-solid fa-file-pdf"></i></a>',
    '📄</button>'      => '<i class="fa-solid fa-file-pdf"></i></button>',
    '🧾 Facture'       => '<i class="fa-solid fa-file-invoice"></i> Facture',
    '🧾 Voir</a>'      => '<i class="fa-solid fa-eye"></i> Voir</a>',
    '🧾 Voir facture'  => '<i class="fa-solid fa-eye"></i> Voir facture',
    '⭐ Passer Pro'    => '<i class="fa-solid fa-star"></i> Passer Pro',
    '➕ Ajouter un client'      => '<i class="fa-solid fa-plus"></i> Ajouter un client',
    '➕ Ajouter une prestation' => '<i class="fa-solid fa-plus"></i> Ajouter une prestation',
    '📋 Rapport annuel'  => '<i class="fa-solid fa-chart-bar"></i> Rapport annuel',
    '📊 Exporter Excel'  => '<i class="fa-solid fa-file-excel"></i> Exporter Excel',
    '💰</a>'             => '<i class="fa-solid fa-coins"></i></a>',
    // ── Stat badges ──
    'stat-badge navy">📊</div>'  => 'stat-badge navy"><i class="fa-solid fa-chart-line"></i></div>',
    'stat-badge gold">💼</div>'  => 'stat-badge gold"><i class="fa-solid fa-briefcase"></i></div>',
    'stat-badge green">🏆</div>' => 'stat-badge green"><i class="fa-solid fa-trophy"></i></div>',
    'stat-badge navy">🏗️</div>'  => 'stat-badge navy"><i class="fa-solid fa-helmet-safety"></i></div>',
    'stat-badge gold">🔨</div>'  => 'stat-badge gold"><i class="fa-solid fa-hammer"></i></div>',
    'stat-badge green">📦</div>' => 'stat-badge green"><i class="fa-solid fa-box-open"></i></div>',
    'stat-badge navy">📬</div>'  => 'stat-badge navy"><i class="fa-solid fa-envelope-open-text"></i></div>',
    'stat-badge red">💸</div>'   => 'stat-badge red"><i class="fa-solid fa-arrow-trend-down"></i></div>',
    'stat-badge green">📈</div>' => 'stat-badge green"><i class="fa-solid fa-arrow-trend-up"></i></div>',
    'stat-badge red">📈</div>'   => 'stat-badge red"><i class="fa-solid fa-arrow-trend-down"></i></div>',
    'stat-badge gold">🎯</div>'  => 'stat-badge gold"><i class="fa-solid fa-bullseye"></i></div>',
    'stat-badge green">🎯</div>' => 'stat-badge green"><i class="fa-solid fa-bullseye"></i></div>',
    // ── Empty states ──
    'empty-icon">🔍</div>' => 'empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>',
    'empty-icon">🏗️</div>' => 'empty-icon"><i class="fa-solid fa-helmet-safety"></i></div>',
    'empty-icon">💸</div>' => 'empty-icon"><i class="fa-solid fa-receipt"></i></div>',
    'empty-icon">👥</div>' => 'empty-icon"><i class="fa-solid fa-users"></i></div>',
    'empty-icon">📦</div>' => 'empty-icon"><i class="fa-solid fa-box-open"></i></div>',
    // ── Icônes inline dans les status maps PHP ──
    "'icon' => '✅'" => "'icon' => '<i class=\"fa-solid fa-circle-check\"></i>'",
    "'icon' => '❌'" => "'icon' => '<i class=\"fa-solid fa-circle-xmark\"></i>'",
    "'icon' => '📦'" => "'icon' => '<i class=\"fa-solid fa-box-open\"></i>'",
    // ── Options select statut projet ──
    "'non_commence'=>'⏳ Non commencé'" => "'non_commence'=>'Non commencé'",
    "'en_cours'=>'🔨 En cours'"         => "'en_cours'=>'En cours'",
    "'livre'=>'📦 Livré'"               => "'livre'=>'Livré'",
    "'valide'=>'✅ Validé'"             => "'valide'=>'Validé'",
    // ── Flash / alertes ──
    '✅ <?= htmlspecialchars($flash)'       => '<i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash)',
    '✅ <?= htmlspecialchars($flashSuccess)' => '<i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess)',
    // overdue badge inline
    "'retard' => '⚠ En retard'" => "'retard' => '<i class=\"fa-solid fa-triangle-exclamation\"></i> En retard'",
    '⚠ En retard'  => '<i class="fa-solid fa-triangle-exclamation"></i> En retard',
    '⚠️ <strong><?= $overdueStats' => '<i class="fa-solid fa-triangle-exclamation"></i> <strong><?= $overdueStats',
    '⚠ <?= implode' => '<i class="fa-solid fa-triangle-exclamation"></i> <?= implode',
    // ── Accounting inline ──
    "📬 <?= \$m['nb_envoyee']" => '<i class="fa-solid fa-envelope-open-text"></i> <?= $m[\'nb_envoyee\']',
    "✅ <?= \$m['nb_payee']"   => '<i class="fa-solid fa-circle-check"></i> <?= $m[\'nb_payee\']',
    '📬 <?= $totalNbEnvoyee'   => '<i class="fa-solid fa-envelope-open-text"></i> <?= $totalNbEnvoyee',
    '✅ <?= $totalNbPayee'     => '<i class="fa-solid fa-circle-check"></i> <?= $totalNbPayee',
    // ── Icône loupe inline ──
    'font-size:14px">🔍</span>' => 'font-size:14px"><i class="fa-solid fa-magnifying-glass"></i></span>',
    // ── Supprimer les → dans les liens textuels ──
    '>Voir le pipeline →</a>'  => '>Voir le pipeline</a>',
    '>Voir les projets →</a>'  => '>Voir les projets</a>',
    '>Voir les dépenses →</a>' => '>Voir les dépenses</a>',
    'Voir →</a>'               => 'Voir</a>',
];

$targets = [
    $base . '/invoice/list.php',
    $base . '/client/index.php',
    $base . '/pipeline/index.php',
    $base . '/invoice/create.php',
    $base . '/project/index.php',
    $base . '/expense/index.php',
    $base . '/accounting/index.php',
    $base . '/services/index.php',
];

foreach ($targets as $file) {
    $content = file_get_contents($file);
    $original = $content;
    foreach ($map as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: " . basename($file) . "\n";
    } else {
        echo "No change: " . basename($file) . "\n";
    }
}
echo "Done.\n";

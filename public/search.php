<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\SearchRepository;

$q       = trim($_GET['q'] ?? '');
$results = ['invoices' => [], 'devis' => [], 'clients' => [], 'expenses' => []];
$total   = 0;

if (strlen($q) >= 2) {
    $repo    = new SearchRepository();
    $results = $repo->search($q);
    $total   = count($results['invoices']) + count($results['devis'])
             + count($results['clients'])  + count($results['expenses']);
}

$pageTitle = 'Recherche';
$currentPage = 'search';

$statusLabels = [
    'brouillon' => ['label' => 'Brouillon', 'color' => 'var(--muted)'],
    'envoyée'   => ['label' => 'Envoyée',   'color' => 'var(--blue)'],
    'payée'     => ['label' => 'Payée',     'color' => 'var(--green)'],
    'annulée'   => ['label' => 'Annulée',   'color' => 'var(--red)'],
    'envoyé'    => ['label' => 'Envoyé',    'color' => 'var(--blue)'],
    'accepté'   => ['label' => 'Accepté',   'color' => 'var(--green)'],
    'refusé'    => ['label' => 'Refusé',    'color' => 'var(--red)'],
];

require_once __DIR__ . '/../templates/layout.php';
?>

<style>
.search-hero { margin-bottom: 24px; }
.search-form { display: flex; gap: 10px; max-width: 600px; }
.search-form input[type=text] {
  flex: 1; padding: 10px 14px; border: 1px solid var(--border);
  border-radius: 8px; font-size: .9rem; font-family: inherit;
  outline: none; transition: border-color .15s;
}
.search-form input[type=text]:focus { border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-dim); }
.search-meta { color: var(--muted); font-size: .82rem; margin-top: 10px; }
.results-section { margin-bottom: 28px; }
.results-section h3 {
  font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
  color: var(--muted); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.results-section h3 .badge {
  background: var(--gold-dim); color: var(--gold); border-radius: 99px;
  padding: 1px 8px; font-size: .7rem;
}
.result-item {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 16px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--white);
  text-decoration: none; color: var(--text);
  transition: all .15s; margin-bottom: 6px;
}
.result-item:hover { border-color: var(--gold); box-shadow: var(--shadow-sm); transform: translateX(2px); }
.result-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; flex-shrink: 0;
}
.result-body { flex: 1; min-width: 0; }
.result-title { font-size: .85rem; font-weight: 600; color: var(--navy); }
.result-sub   { font-size: .75rem; color: var(--muted); margin-top: 2px; }
.result-meta  { font-size: .78rem; font-weight: 600; color: var(--text); text-align: right; }
.result-status {
  font-size: .68rem; font-weight: 600; padding: 2px 8px; border-radius: 99px;
  background: var(--bg); border: 1px solid var(--border);
}
.empty-state { text-align: center; padding: 48px; color: var(--muted); }
.empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: .3; }
</style>

<div class="search-hero">
  <form method="GET" action="/search.php" class="search-form">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher une facture, un client, une dépense…" autofocus>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
  </form>
  <?php if ($q !== ''): ?>
  <div class="search-meta">
    <?= $total ?> résultat<?= $total > 1 ? 's' : '' ?> pour <strong>«<?= htmlspecialchars($q) ?>»</strong>
  </div>
  <?php endif; ?>
</div>

<?php if ($q !== '' && $total === 0): ?>
<div class="empty-state">
  <i class="fa-solid fa-magnifying-glass"></i>
  Aucun résultat pour «<?= htmlspecialchars($q) ?>».<br>
  <span style="font-size:.8rem">Essayez un numéro de facture, un nom client ou un mot-clé.</span>
</div>
<?php endif; ?>

<?php if (!empty($results['invoices'])): ?>
<div class="results-section">
  <h3><i class="fa-solid fa-file-invoice"></i> Factures <span class="badge"><?= count($results['invoices']) ?></span></h3>
  <?php foreach ($results['invoices'] as $inv): ?>
    <?php $st = $statusLabels[$inv['status']] ?? ['label' => $inv['status'], 'color' => 'var(--muted)']; ?>
    <a href="/invoice/edit.php?id=<?= $inv['id'] ?>" class="result-item">
      <div class="result-icon" style="background:var(--blue-bg);color:var(--blue)"><i class="fa-solid fa-file-invoice"></i></div>
      <div class="result-body">
        <div class="result-title"><?= htmlspecialchars($inv['number']) ?> — <?= htmlspecialchars($inv['client_name']) ?></div>
        <div class="result-sub"><?= htmlspecialchars($inv['subject'] ?: '—') ?> · <?= htmlspecialchars($inv['issued_at'] ?? '') ?></div>
      </div>
      <div>
        <span class="result-status" style="color:<?= $st['color'] ?>;border-color:currentColor"><?= $st['label'] ?></span>
      </div>
      <div class="result-meta"><?= number_format((int)$inv['total_net'], 0, ',', ' ') ?> FCFA</div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($results['devis'])): ?>
<div class="results-section">
  <h3><i class="fa-solid fa-file-circle-question"></i> Devis <span class="badge"><?= count($results['devis']) ?></span></h3>
  <?php foreach ($results['devis'] as $d): ?>
    <?php $st = $statusLabels[$d['status']] ?? ['label' => $d['status'], 'color' => 'var(--muted)']; ?>
    <a href="/devis/edit.php?id=<?= $d['id'] ?>" class="result-item">
      <div class="result-icon" style="background:var(--purple-bg);color:var(--purple)"><i class="fa-solid fa-file-circle-question"></i></div>
      <div class="result-body">
        <div class="result-title"><?= htmlspecialchars($d['number']) ?> — <?= htmlspecialchars($d['client_name']) ?></div>
        <div class="result-sub"><?= htmlspecialchars($d['subject'] ?: '—') ?> · <?= htmlspecialchars($d['issued_at'] ?? '') ?></div>
      </div>
      <div>
        <span class="result-status" style="color:<?= $st['color'] ?>;border-color:currentColor"><?= $st['label'] ?></span>
      </div>
      <div class="result-meta"><?= number_format((int)$d['total_net'], 0, ',', ' ') ?> FCFA</div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($results['clients'])): ?>
<div class="results-section">
  <h3><i class="fa-solid fa-users"></i> Clients <span class="badge"><?= count($results['clients']) ?></span></h3>
  <?php foreach ($results['clients'] as $c): ?>
    <a href="/client/edit.php?id=<?= $c['id'] ?>" class="result-item">
      <div class="result-icon" style="background:var(--green-bg);color:var(--green)"><i class="fa-solid fa-user"></i></div>
      <div class="result-body">
        <div class="result-title"><?= htmlspecialchars($c['name']) ?></div>
        <div class="result-sub">
          <?= htmlspecialchars($c['email'] ?: '') ?>
          <?php if ($c['phone']): ?> · <?= htmlspecialchars($c['phone']) ?><?php endif; ?>
        </div>
      </div>
      <?php if ($c['whatsapp']): ?>
      <div style="font-size:.78rem;color:#25d366"><i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($c['whatsapp']) ?></div>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($results['expenses'])): ?>
<div class="results-section">
  <h3><i class="fa-solid fa-arrow-trend-down"></i> Dépenses <span class="badge"><?= count($results['expenses']) ?></span></h3>
  <?php foreach ($results['expenses'] as $e): ?>
    <a href="/expense/index.php" class="result-item">
      <div class="result-icon" style="background:var(--orange-bg);color:var(--orange)"><i class="fa-solid fa-receipt"></i></div>
      <div class="result-body">
        <div class="result-title"><?= htmlspecialchars($e['description']) ?></div>
        <div class="result-sub"><?= htmlspecialchars($e['category']) ?> · <?= htmlspecialchars($e['date'] ?? '') ?></div>
      </div>
      <div class="result-meta"><?= number_format((int)$e['amount'], 0, ',', ' ') ?> FCFA</div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/layout_end.php'; ?>

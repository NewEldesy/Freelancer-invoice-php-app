<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\UserRepository;

Auth::requireAdmin();

$repo  = new UserRepository();
$users = $repo->all();
$me    = Auth::user();

$pageTitle     = 'Gestion des utilisateurs';
$currentPage   = 'admin_users';
$topbarActions = '<a href="/admin/users/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvel utilisateur</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card">
  <div class="card-header">
    <h2>Utilisateurs (<?= count($users) ?>)</h2>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Email</th>
          <th>Rôle</th>
          <th>Créé le</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $user): ?>
      <?php
        $fullName    = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $isSelf      = (int)$user['id'] === (int)$me['id'];
        $isSuperAdmin = $user['role'] === 'superadmin';
      ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($user['username']) ?></div>
          <?php if ($fullName !== ''): ?>
          <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($fullName) ?></div>
          <?php endif; ?>
          <?php if ($isSelf): ?>
          <div style="font-size:.7rem;color:var(--gold)">● vous</div>
          <?php endif; ?>
        </td>
        <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($user['email']) ?></td>
        <td>
          <?php
            $roleBadge = match($user['role']) {
                'superadmin'   => ['badge-cancelled', 'Super Admin'],
                'admin'        => ['badge-sent',       'Administrateur'],
                'gestionnaire' => ['badge-paid',       'Gestionnaire'],
                default        => ['badge-draft',      'Utilisateur'],
            };
          ?>
          <span class="badge <?= $roleBadge[0] ?>"><?= $roleBadge[1] ?></span>
        </td>
        <td style="color:var(--muted);font-size:.8rem"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
        <td>
          <?php if (!$isSelf && !$isSuperAdmin): ?>
          <form method="POST" action="/admin/users/delete.php" style="display:inline">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon"
                    onclick="return confirm('Supprimer <?= htmlspecialchars(addslashes($user['username'])) ?> ?')"
                    title="Supprimer"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php else: ?>
          <span style="font-size:.72rem;color:var(--muted-light)"><?= $isSuperAdmin ? '<i class="fa-solid fa-lock" title="Compte protégé"></i>' : '—' ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>

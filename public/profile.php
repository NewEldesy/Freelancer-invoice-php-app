<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\UserRepository;

Auth::check();

$repo   = new UserRepository();
$userId = (int) $_SESSION['user_id'];
$user   = $repo->findById($userId);

if ($user === null) {
    Auth::logout();
    header('Location: /login.php');
    exit;
}

$tab = $_GET['tab'] ?? 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Upload photo de profil ────────────────────────────────────────────────
    if ($action === 'update_avatar') {
        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['_profile_error'] = 'Erreur lors du téléchargement du fichier.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['_profile_error'] = 'La photo ne doit pas dépasser 2 Mo.';
        } else {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mimeType])) {
                $_SESSION['_profile_error'] = 'Format non supporté. Utilisez JPG, PNG ou WebP.';
            } else {
                $ext      = $allowed[$mimeType];
                $filename = 'avatar_' . $userId . '.' . $ext;
                $dest     = __DIR__ . '/uploads/avatars/' . $filename;
                // Supprimer l'ancien avatar si l'extension change
                foreach (['jpg', 'png', 'webp'] as $e) {
                    $old = __DIR__ . '/uploads/avatars/avatar_' . $userId . '.' . $e;
                    if ($old !== $dest && file_exists($old)) unlink($old);
                }
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $avatarDbPath = '/uploads/avatars/' . $filename;
                    $repo->updateAvatar($userId, $avatarDbPath);
                    $_SESSION['auth_user']['avatar'] = $avatarDbPath;
                    $_SESSION['_profile_success'] = 'Photo de profil mise à jour.';
                } else {
                    $_SESSION['_profile_error'] = 'Impossible d\'enregistrer la photo.';
                }
            }
        }
        header('Location: /profile.php?tab=info');
        exit;
    }

    // ── Supprimer photo de profil ─────────────────────────────────────────────
    if ($action === 'delete_avatar') {
        if ($user['avatar']) {
            $path = realpath(__DIR__ . $user['avatar']);
            $allowed = realpath(__DIR__ . '/uploads/avatars');
            if ($path && $allowed && str_starts_with($path, $allowed . DIRECTORY_SEPARATOR)) {
                unlink($path);
            }
        }
        $repo->updateAvatar($userId, null);
        $_SESSION['auth_user']['avatar'] = null;
        $_SESSION['_profile_success'] = 'Photo de profil supprimée.';
        header('Location: /profile.php?tab=info');
        exit;
    }

    // ── Modifier les informations ─────────────────────────────────────────────
    if ($action === 'update_info') {
        $email     = strtolower(trim($_POST['email']      ?? ''));
        $phone     = trim($_POST['phone']      ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');

        if ($email === '') {
            $_SESSION['_profile_error'] = 'L\'adresse e-mail est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['_profile_error'] = 'Adresse e-mail invalide.';
        } elseif ($repo->emailTakenByOther($email, $userId)) {
            $_SESSION['_profile_error'] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        } else {
            $repo->updateProfile($userId, $email, $phone, $firstName, $lastName);
            $_SESSION['auth_user']['email'] = $email;
            $_SESSION['_profile_success'] = 'Informations mises à jour avec succès.';
        }
        header('Location: /profile.php?tab=info');
        exit;
    }

    // ── Changer le mot de passe ───────────────────────────────────────────────
    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $_SESSION['_profile_error'] = 'Tous les champs sont obligatoires.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $_SESSION['_profile_error'] = 'Mot de passe actuel incorrect.';
        } elseif (strlen($new) < 8) {
            $_SESSION['_profile_error'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } elseif ($new !== $confirm) {
            $_SESSION['_profile_error'] = 'Les mots de passe ne correspondent pas.';
        } else {
            $repo->updatePassword($userId, $new);
            $_SESSION['_profile_success'] = 'Mot de passe modifié avec succès.';
        }
        header('Location: /profile.php?tab=password');
        exit;
    }
}

// Lire et effacer les messages flash de session
if (!empty($_SESSION['_profile_success'])) {
    $flashSuccess = $_SESSION['_profile_success'];
    unset($_SESSION['_profile_success']);
}
if (!empty($_SESSION['_profile_error'])) {
    $flashError = $_SESSION['_profile_error'];
    unset($_SESSION['_profile_error']);
}

$roleLabels = [
    'superadmin'   => 'Super Administrateur',
    'admin'        => 'Administrateur',
    'gestionnaire' => 'Gestionnaire',
    'utilisateur'  => 'Utilisateur',
];

$pageTitle   = 'Mon profil';
$currentPage = 'profile';

require __DIR__ . '/../templates/layout.php';
?>

<style>
.profile-tabs { display:flex; gap:4px; margin-bottom:24px; border-bottom:2px solid var(--border); padding-bottom:0; }
.profile-tab {
  padding:9px 18px; font-size:.82rem; font-weight:600; color:var(--muted);
  cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;
  text-decoration:none; transition:color .15s, border-color .15s;
  display:flex; align-items:center; gap:7px;
}
.profile-tab:hover { color:var(--navy); }
.profile-tab.active { color:var(--navy); border-bottom-color:var(--navy); }

.profile-grid { display:grid; grid-template-columns:280px 1fr; gap:24px; align-items:start; }
@media(max-width:700px){ .profile-grid { grid-template-columns:1fr; } }

.profile-card {
  background:#fff; border-radius:14px; border:1px solid var(--border);
  padding:28px; text-align:center;
}
.profile-avatar {
  width:72px; height:72px; border-radius:50%; background:var(--navy);
  display:flex; align-items:center; justify-content:center;
  font-size:1.6rem; color:#fff; margin:0 auto 14px; font-weight:700;
}
.profile-name  { font-size:1rem; font-weight:700; color:var(--navy); }
.profile-role  { font-size:.75rem; color:var(--muted); margin-top:4px; }
.profile-meta  { margin-top:16px; text-align:left; border-top:1px solid var(--border-soft); padding-top:14px; }
.profile-meta-row {
  display:flex; align-items:center; gap:10px; padding:7px 0;
  font-size:.79rem; color:var(--muted);
  border-bottom:1px solid var(--border-soft);
}
.profile-meta-row:last-child { border-bottom:none }
.profile-meta-row i { width:14px; text-align:center; color:#94a3b8 }
.profile-meta-row span { color:var(--navy); font-weight:500 }

.readonly-badge {
  font-size:.6rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.4px; background:#f1f5f9; color:#94a3b8;
  padding:2px 7px; border-radius:10px; margin-left:auto;
}
</style>

<!-- Tabs -->
<div class="profile-tabs">
  <a href="?tab=info"     class="profile-tab <?= $tab === 'info'     ? 'active' : '' ?>">
    <i class="fa-solid fa-user"></i> Informations
  </a>
  <a href="?tab=password" class="profile-tab <?= $tab === 'password' ? 'active' : '' ?>">
    <i class="fa-solid fa-lock"></i> Mot de passe
  </a>
</div>

<div class="profile-grid">

  <!-- Carte identité -->
  <div class="profile-card">
    <!-- Avatar -->
    <div style="position:relative;width:80px;margin:0 auto 14px">
      <?php if ($user['avatar'] && file_exists(__DIR__ . $user['avatar'])): ?>
        <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= filemtime(__DIR__ . $user['avatar']) ?>"
             alt="Avatar"
             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">
      <?php else: ?>
        <div class="profile-avatar" style="margin:0">
          <?php
            $initials = $user['first_name'] && $user['last_name']
              ? strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))
              : strtoupper(substr($user['username'], 0, 1));
            echo htmlspecialchars($initials);
          ?>
        </div>
      <?php endif; ?>
      <!-- Bouton upload -->
      <label for="avatar-input" title="Changer la photo"
             style="position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:11px;border:2px solid #fff;transition:background .15s"
             onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='var(--navy)'">
        <i class="fa-solid fa-camera"></i>
      </label>
    </div>

    <!-- Formulaire upload caché -->
    <form method="POST" enctype="multipart/form-data" id="avatar-form">
      <input type="hidden" name="action" value="update_avatar">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp"
             style="display:none" onchange="document.getElementById('avatar-form').submit()">
    </form>

    <?php if ($user['avatar']): ?>
    <form method="POST" style="margin-bottom:10px">
      <input type="hidden" name="action" value="delete_avatar">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <button type="submit" style="background:none;border:none;font-size:.68rem;color:#94a3b8;cursor:pointer;text-decoration:underline"
              onclick="return confirm('Supprimer la photo de profil ?')">
        <i class="fa-solid fa-trash"></i> Supprimer la photo
      </button>
    </form>
    <?php endif; ?>
    <div class="profile-name"><?= htmlspecialchars($user['username']) ?></div>
    <?php if ($user['first_name'] || $user['last_name']): ?>
    <div class="profile-role" style="color:var(--navy);font-weight:500;margin-top:2px">
      <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
    </div>
    <?php endif; ?>
    <div class="profile-role"><?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role']) ?></div>

    <div class="profile-meta">
      <div class="profile-meta-row">
        <i class="fa-solid fa-at"></i>
        <span><?= htmlspecialchars($user['email']) ?></span>
      </div>
      <?php if ($user['phone']): ?>
      <div class="profile-meta-row">
        <i class="fa-solid fa-phone"></i>
        <span><?= htmlspecialchars($user['phone']) ?></span>
      </div>
      <?php endif; ?>
      <div class="profile-meta-row">
        <i class="fa-solid fa-calendar"></i>
        <span>Depuis le <?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
      </div>
    </div>
  </div>

  <!-- Panneau principal -->
  <div>

    <?php if ($tab === 'info'): ?>
    <!-- ── Formulaire informations ── -->
    <div class="card">
      <div style="padding:16px 22px;border-bottom:1px solid var(--border-soft);font-weight:700;color:var(--navy);font-size:.9rem">
        <i class="fa-solid fa-pen-to-square"></i> Modifier mes informations
      </div>
      <div style="padding:24px 22px">
        <form method="POST">
          <input type="hidden" name="action" value="update_info">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

          <!-- Nom d'utilisateur (lecture seule) -->
          <div style="margin-bottom:20px;padding:14px 16px;background:#f8fafc;border-radius:10px;border:1px solid var(--border-soft)">
            <label style="font-size:.72rem;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:6px">
              <i class="fa-solid fa-user"></i> Nom d'utilisateur
              <span class="readonly-badge">non modifiable</span>
            </label>
            <div style="padding:9px 12px;background:#f1f5f9;border:1px solid var(--border);border-radius:8px;font-size:.84rem;color:#64748b">
              <?= htmlspecialchars($user['username']) ?>
            </div>
          </div>

          <!-- Champs modifiables -->
          <div class="form-grid-2">
            <div class="field">
              <label><i class="fa-solid fa-user-tag"></i> Prénom(s)</label>
              <input type="text" name="first_name"
                     value="<?= htmlspecialchars($_POST['first_name'] ?? $user['first_name']) ?>"
                     placeholder="Jean">
            </div>
            <div class="field">
              <label><i class="fa-solid fa-id-card"></i> Nom de famille</label>
              <input type="text" name="last_name"
                     value="<?= htmlspecialchars($_POST['last_name'] ?? $user['last_name']) ?>"
                     placeholder="Doe">
            </div>
            <div class="field">
              <label><i class="fa-solid fa-at"></i> Adresse e-mail <span style="color:#dc2626">*</span></label>
              <input type="email" name="email" required
                     value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>">
            </div>
            <div class="field">
              <label><i class="fa-solid fa-phone"></i> Téléphone</label>
              <input type="text" name="phone"
                     value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone']) ?>"
                     placeholder="(+226) 00 00 00 00">
            </div>
          </div>

          <div style="margin-top:20px;display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>
    <!-- ── Formulaire mot de passe ── -->
    <div class="card">
      <div style="padding:16px 22px;border-bottom:1px solid var(--border-soft);font-weight:700;color:var(--navy);font-size:.9rem">
        <i class="fa-solid fa-lock"></i> Changer mon mot de passe
      </div>
      <div style="padding:24px 22px">
        <form method="POST">
          <input type="hidden" name="action" value="update_password">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

          <div style="display:flex;flex-direction:column;gap:16px;max-width:400px">
            <div class="field">
              <label><i class="fa-solid fa-lock"></i> Mot de passe actuel <span style="color:#dc2626">*</span></label>
              <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="field">
              <label><i class="fa-solid fa-key"></i> Nouveau mot de passe <span style="color:#dc2626">*</span></label>
              <input type="password" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">
              <span class="hint"><i class="fa-solid fa-circle-info"></i> 8 caractères minimum</span>
            </div>
            <div class="field">
              <label><i class="fa-solid fa-key"></i> Confirmer le nouveau mot de passe <span style="color:#dc2626">*</span></label>
              <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
              <div id="pwd-match" style="font-size:.7rem;display:none"></div>
            </div>
          </div>

          <div style="margin-top:24px;display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Changer le mot de passe
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
// Vérification en temps réel de la correspondance des mots de passe
const np = document.getElementById('new_password');
const cp = document.getElementById('confirm_password');
const hint = document.getElementById('pwd-match');
if (cp) {
  cp.addEventListener('input', () => {
    if (!cp.value) { hint.style.display = 'none'; return; }
    hint.style.display = 'block';
    if (np.value === cp.value) {
      hint.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#059669"></i> <span style="color:#059669">Les mots de passe correspondent</span>';
    } else {
      hint.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#dc2626"></i> <span style="color:#dc2626">Les mots de passe ne correspondent pas</span>';
    }
  });
}
</script>

<?php require __DIR__ . '/../templates/layout_end.php'; ?>

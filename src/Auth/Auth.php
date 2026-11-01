<?php

declare(strict_types=1);

namespace App\Auth;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']),
            ]);
            ini_set('session.use_strict_mode', '1');
            session_start();
        }
    }

    /** Redirige vers la page de connexion si non authentifié ou si l'utilisateur de session n'existe plus en base. */
    public static function check(): void
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        // Vérifie que l'utilisateur de session existe toujours dans la base courante.
        // Gère les sessions obsolètes après un remplacement, renommage ou restauration de la base.
        $now = time();
        $lastCheck = $_SESSION['_db_check_at'] ?? 0;
        if ($now - $lastCheck > 300) { // re-vérification toutes les 5 minutes
            $pdo  = \App\Database\Database::connection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            if ($stmt->fetchColumn() === false) {
                self::logout();
                header('Location: /login.php?session_expired=1');
                exit;
            }
            $_SESSION['_db_check_at'] = $now;
        }
    }

    /**
     * Exige le rôle gestionnaire ou utilisateur (pages métier).
     * Bloque l'accès aux comptes admin aux données métier.
     */
    public static function requireBusiness(): void
    {
        self::check();
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'admin') {
            header('Location: /admin/users.php'); exit;
        }
        if ($role === 'superadmin') {
            header('Location: /superadmin/keys.php'); exit;
        }
    }

    /**
     * Exige le rôle gestionnaire (opérations d'écriture).
     * Bloque les rôles admin et utilisateur pour la modification des données métier.
     */
    public static function requireManager(): void
    {
        self::check();
        $role = $_SESSION['user_role'] ?? '';
        if ($role !== 'gestionnaire') {
            if ($role === 'admin') {
                header('Location: /admin/users.php');
            } else {
                header('Location: /index.php?forbidden=1');
            }
            exit;
        }
    }

    /** Redirige vers le tableau de bord si le rôle n'est pas admin. */
    public static function requireAdmin(): void
    {
        self::check();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: /index.php');
            exit;
        }
    }

    /** Exige le rôle superadmin (gestion des clés de licence). */
    public static function requireSuperAdmin(): void
    {
        self::check();
        if (($_SESSION['user_role'] ?? '') !== 'superadmin') {
            header('Location: /index.php');
            exit;
        }
    }

    public static function isSuperAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'superadmin';
    }

    /**
     * Vérifie si l'utilisateur courant peut effectuer une action donnée.
     *
     * 'write'      → gestionnaire uniquement
     * 'read'       → gestionnaire ou utilisateur (pas admin)
     * 'admin'      → admin uniquement
     * 'superadmin' → superadmin uniquement
     */
    public static function can(string $action): bool
    {
        self::start();
        $role = $_SESSION['user_role'] ?? '';
        return match($action) {
            'write'      => $role === 'gestionnaire',
            'read'       => in_array($role, ['gestionnaire', 'utilisateur'], true),
            'admin'      => $role === 'admin',
            'superadmin' => $role === 'superadmin',
            default      => false,
        };
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function role(): string
    {
        return $_SESSION['user_role'] ?? '';
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function isManager(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'gestionnaire';
    }

    public static function isViewer(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'utilisateur';
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['auth_user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'avatar'   => $user['avatar'] ?? null,
        ];
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        self::start();
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            exit('Requête invalide (token CSRF manquant ou incorrect).');
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        // Supprime le cookie de session du navigateur
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }
}

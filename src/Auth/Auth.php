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

    /** Redirect to login if not authenticated. */
    public static function check(): void
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Require gestionnaire or utilisateur role (business pages).
     * Blocks admin accounts from accessing business data.
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
     * Require gestionnaire role (write operations).
     * Blocks admin and utilisateur from modifying business data.
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

    /** Redirect to dashboard if not admin. */
    public static function requireAdmin(): void
    {
        self::check();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: /index.php');
            exit;
        }
    }

    /** Require superadmin role (license key management). */
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
     * Check if the current user can perform a given action.
     *
     * 'write'  → gestionnaire only
     * 'read'   → gestionnaire or utilisateur (not admin)
     * 'admin'  → admin only
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
        // Delete the session cookie from the browser
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }
}

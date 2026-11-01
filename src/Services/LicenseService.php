<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

/**
 * Système de licence pour Freelancer-invoice.
 *
 * Format de clé : BASE64_PAYLOAD.HMAC32
 *   payload = base64_encode(json({edition, expires, machine_id}))
 *   HMAC32  = les 32 premiers caractères de hash_hmac('sha256', payload, SECRET)
 *
 * Éditions : free | pro | enterprise
 */
final class LicenseService
{
    private static ?string $secret = null;

    private static function secret(): string
    {
        if (self::$secret !== null) return self::$secret;

        // Priorité 1 : variable d'environnement LICENSE_SECRET (définie dans .env ou le système)
        $envSecret = $_ENV['LICENSE_SECRET'] ?? getenv('LICENSE_SECRET') ?: '';
        if ($envSecret !== '' && $envSecret !== 'REMPLACER_PAR_VOTRE_SECRET_UNIQUE_ICI') {
            return self::$secret = $envSecret;
        }

        // Priorité 2 : fichier config/license.secret (rétrocompatibilité)
        $path = __DIR__ . '/../../config/license.secret';
        if (!is_file($path)) {
            throw new \RuntimeException('Secret de licence manquant : définir LICENSE_SECRET dans .env ou créer config/license.secret');
        }
        $value = trim(file_get_contents($path));
        if ($value === '' || $value === 'REMPLACER_PAR_VOTRE_SECRET_UNIQUE_ICI') {
            throw new \RuntimeException('Le secret de licence n\'a pas été configuré (LICENSE_SECRET dans .env ou config/license.secret)');
        }
        return self::$secret = $value;
    }

    /** @internal Réservé aux tests : injecte un secret connu sans accès au système de fichiers. */
    public static function setSecretForTesting(string $secret): void
    {
        self::$secret = $secret;
        self::$cache  = null;
    }

    /** @internal Réservé aux tests : réinitialise tout l'état en mémoire. */
    public static function resetForTesting(): void
    {
        self::$secret = null;
        self::$cache  = null;
    }

    private const FREE_LIMITS = [
        'invoice_max'   => 10,
        'duplicate_max' => 5,
        'pdf_max'       => 15,
        'excel_max'     => 15,
        'pipeline_max'  => 5,
        'expense_max'   => 5,
        'accounting'    => false,
        'multi_users'   => false,
    ];

    private static ?array $cache = null;

    // ── API publique ─────────────────────────────────────────────────────────

    public static function current(): array
    {
        if (self::$cache !== null) return self::$cache;

        $db  = Database::connection();
        $row = $db->query("SELECT * FROM license ORDER BY id DESC LIMIT 1")->fetch();

        if (!$row) {
            return self::$cache = ['edition' => 'none', 'valid' => false, 'expires_at' => null];
        }

        $expired = $row['expires_at'] && $row['expires_at'] < date('Y-m-d');

        return self::$cache = [
            'edition'    => $row['edition'],
            'valid'      => !$expired,
            'expired'    => $expired,
            'expires_at' => $row['expires_at'],
            'key'        => $row['license_key'] ?? null,
        ];
    }

    public static function edition(): string
    {
        return self::current()['edition'] ?? 'none';
    }

    public static function isValid(): bool
    {
        return self::current()['valid'] ?? false;
    }

    public static function isFree(): bool
    {
        return self::edition() === 'free';
    }

    /** Vérifie qu'une licence valide existe. Active silencieusement le plan gratuit au premier démarrage. */
    public static function requireValid(): void
    {
        $lic = self::current();
        if ($lic['edition'] === 'none') {
            // Premier démarrage : activation silencieuse du plan gratuit
            self::activateFree();
            self::$cache = null;
            return;
        }
        if (!$lic['valid']) {
            $target = ($lic['expired'] ?? false) ? '/activate.php?expired=1' : '/activate.php';
            header('Location: ' . $target);
            exit;
        }
    }

    public static function invoiceMax(): int   { return self::isFree() ? self::FREE_LIMITS['invoice_max']   : PHP_INT_MAX; }
    public static function duplicateMax(): int { return self::isFree() ? self::FREE_LIMITS['duplicate_max'] : PHP_INT_MAX; }
    public static function pdfMax(): int       { return self::isFree() ? self::FREE_LIMITS['pdf_max']       : PHP_INT_MAX; }
    public static function excelMax(): int     { return self::isFree() ? self::FREE_LIMITS['excel_max']     : PHP_INT_MAX; }
    public static function pipelineMax(): int  { return self::isFree() ? self::FREE_LIMITS['pipeline_max']  : PHP_INT_MAX; }
    public static function expenseMax(): int   { return self::isFree() ? self::FREE_LIMITS['expense_max']   : PHP_INT_MAX; }
    public static function canAccounting(): bool  { return !self::isFree() || self::FREE_LIMITS['accounting']; }
    public static function canMultiUsers(): bool  { return !self::isFree() || self::FREE_LIMITS['multi_users']; }

    /** Vérifie si une nouvelle entrée peut être créée. $current = nombre actuel en base. */
    public static function canAdd(string $feature, int $current): bool
    {
        return match ($feature) {
            'invoice'   => $current < self::invoiceMax(),
            'duplicate' => $current < self::duplicateMax(),
            'pdf'       => $current < self::pdfMax(),
            'excel'     => $current < self::excelMax(),
            'pipeline'  => $current < self::pipelineMax(),
            'expense'   => $current < self::expenseMax(),
            default     => true,
        };
    }

    // ── Compteurs d'utilisation (pdf / excel / duplication sur plan gratuit) ──

    public static function getCounter(string $feature): int
    {
        $db  = Database::connection();
        $row = $db->prepare("SELECT count FROM feature_usage WHERE feature = ?");
        $row->execute([$feature]);
        $r = $row->fetch();
        return $r ? (int) $r['count'] : 0;
    }

    public static function incrementCounter(string $feature): void
    {
        $db = Database::connection();
        $db->prepare(
            "INSERT INTO feature_usage (feature, count) VALUES (?, 1)
             ON CONFLICT(feature) DO UPDATE SET count = count + 1"
        )->execute([$feature]);
    }

    // Activation

    /** Active le plan gratuit sans clé requise. */
    public static function activateFree(): void
    {
        $db = Database::connection();
        $db->exec("DELETE FROM license");
        $db->prepare(
            "INSERT INTO license (license_key, edition, expires_at, machine_id, activated_at)
             VALUES (NULL, 'free', NULL, NULL, datetime('now','localtime'))"
        )->execute();
        self::$cache = null;
    }

    /**
     * Valide et active une clé de licence.
     * Retourne ['success'=>bool, 'error'=>string, 'edition'=>string].
     */
    public static function activate(string $rawKey): array
    {
        $rawKey = trim($rawKey);
        $result = self::validateKey($rawKey);

        if (!$result['valid']) {
            return ['success' => false, 'error' => $result['error'], 'edition' => ''];
        }

        $payload = $result['payload'];

        // Vérification de liaison machine (si la clé contient un machine_id)
        if (!empty($payload['machine_id']) && $payload['machine_id'] !== '*') {
            if ($payload['machine_id'] !== self::machineId()) {
                return ['success' => false, 'error' => 'Cette clé est liée à un autre poste.', 'edition' => ''];
            }
        }

        // Résolution de l'expiration + vérification de réutilisation : la clé ne doit pas déjà être utilisée sur un autre poste
        $db = Database::connection();
        $storedRow = $db->prepare("SELECT period, used, used_machine FROM license_keys WHERE key_value = ?");
        $storedRow->execute([$rawKey]);
        $stored  = $storedRow->fetch();

        if ($stored && $stored['used'] && $stored['used_machine'] !== self::machineId()) {
            return ['success' => false, 'error' => 'Cette clé a déjà été activée sur un autre poste.', 'edition' => ''];
        }

        $expires = $stored ? self::periodToDate($stored['period']) : ($payload['expires'] ?? null);

        // Vérification d'expiration (uniquement pour les clés déjà activées, cas limite)
        if ($expires !== null && $expires < date('Y-m-d')) {
            return ['success' => false, 'error' => 'Cette clé de licence a expiré.', 'edition' => ''];
        }

        // Enregistrement
        $db->exec("DELETE FROM license");
        $db->prepare(
            "INSERT INTO license (license_key, edition, expires_at, machine_id, activated_at)
             VALUES (?, ?, ?, ?, datetime('now','localtime'))"
        )->execute([
            $rawKey,
            $payload['edition'],
            $expires,
            $payload['machine_id'] ?? null,
        ]);
        self::$cache = null;

        // Marquer la clé comme utilisée dans license_keys si elle était pré-générée
        self::markKeyUsed($rawKey, self::machineId());

        return ['success' => true, 'error' => '', 'edition' => $payload['edition']];
    }

    // Gestion des clés stockées

    /**
     * Génère et enregistre une clé dans la table license_keys.
     * Retourne la chaîne de clé générée.
     */
    public static function generateAndStore(string $edition, string $period): string
    {
        // expires_at est volontairement NULL ici, calculé lors de l'activation côté client
        $key = self::generate($edition, null);

        $db = Database::connection();
        $db->prepare(
            "INSERT OR IGNORE INTO license_keys (key_value, edition, period, expires_at)
             VALUES (?, ?, ?, NULL)"
        )->execute([$key, $edition, $period]);

        return $key;
    }

    /** Marque une clé stockée comme utilisée et calcule son expiration à partir de la période enregistrée. */
    public static function markKeyUsed(string $key, string $machineId): void
    {
        $db = Database::connection();

        // Récupérer la période stockée pour calculer expires_at au moment de l'activation
        $row = $db->prepare("SELECT period FROM license_keys WHERE key_value = ?");
        $row->execute([$key]);
        $stored = $row->fetch();
        $expires = $stored ? self::periodToDate($stored['period']) : null;

        $db->prepare(
            "UPDATE license_keys
             SET used=1, used_at=datetime('now','localtime'), used_machine=?, expires_at=?
             WHERE key_value=?"
        )->execute([$machineId, $expires, $key]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function allStoredKeys(): array
    {
        $db = Database::connection();
        return $db->query(
            "SELECT * FROM license_keys ORDER BY edition ASC, period ASC, generated_at DESC"
        )->fetchAll();
    }

    /** @return array{total:int, used:int, available:int} */
    public static function keyStats(): array
    {
        $db    = Database::connection();
        $total = (int) $db->query("SELECT COUNT(*) FROM license_keys")->fetchColumn();
        $used  = (int) $db->query("SELECT COUNT(*) FROM license_keys WHERE used=1")->fetchColumn();
        return ['total' => $total, 'used' => $used, 'available' => $total - $used];
    }

    private static function periodToDate(?string $period): ?string
    {
        return match($period) {
            '3m'        => date('Y-m-d', strtotime('+3 months')),
            '6m'        => date('Y-m-d', strtotime('+6 months')),
            '1y'        => date('Y-m-d', strtotime('+1 year')),
            '2y'        => date('Y-m-d', strtotime('+2 years')),
            'permanent' => null,
            default     => null,
        };
    }

    // ── Génération de clé (utilisé par keygen.php) ───────────────────────────

    /** Génère une clé de licence signée. */
    public static function generate(string $edition, ?string $expires = null, ?string $machineId = null): string
    {
        $payload = [
            'edition'    => $edition,
            'expires'    => $expires,
            'machine_id' => $machineId ?? '*',
            'issued_at'  => date('Y-m-d'),
            'nonce'      => bin2hex(random_bytes(8)),
        ];
        $payloadB64 = rtrim(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '=');
        $sig        = strtoupper(substr(hash_hmac('sha256', $payloadB64, self::secret()), 0, 32));
        return $payloadB64 . '.' . $sig;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private static function validateKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return ['valid' => false, 'error' => 'Format de clé invalide.', 'payload' => []];
        }

        [$payloadB64, $sig] = $parts;

        $payloadJson = base64_decode($payloadB64, true);
        if ($payloadJson === false) {
            return ['valid' => false, 'error' => 'Clé corrompue.', 'payload' => []];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['edition'])) {
            return ['valid' => false, 'error' => 'Clé illisible.', 'payload' => []];
        }

        $expected = strtoupper(substr(hash_hmac('sha256', $payloadB64, self::secret()), 0, 32));
        if (!hash_equals($expected, strtoupper($sig))) {
            return ['valid' => false, 'error' => 'Clé invalide ou falsifiée.', 'payload' => []];
        }

        if (!in_array($payload['edition'], ['pro', 'enterprise'], true)) {
            return ['valid' => false, 'error' => 'Édition inconnue.', 'payload' => []];
        }

        return ['valid' => true, 'error' => '', 'payload' => $payload];
    }

    public static function machineId(): string
    {
        return md5(strtolower(gethostname() . php_uname('n')));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

/**
 * License system for Invoices Project.
 *
 * Key format: BASE64_PAYLOAD.HMAC32
 *   payload = base64_encode(json({edition, expires, machine_id}))
 *   HMAC32  = first 32 chars of hash_hmac('sha256', payload, SECRET)
 *
 * Editions: free | pro | enterprise
 */
final class LicenseService
{
    private static ?string $secret = null;

    private static function secret(): string
    {
        if (self::$secret !== null) return self::$secret;

        $path = __DIR__ . '/../../config/license.secret';
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier de licence manquant : config/license.secret');
        }
        $value = trim(file_get_contents($path));
        if ($value === '' || $value === 'REMPLACER_PAR_VOTRE_SECRET_UNIQUE_ICI') {
            throw new \RuntimeException('Le secret de licence n\'a pas été configuré dans config/license.secret');
        }
        return self::$secret = $value;
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

    // ── Public API ────────────────────────────────────────────────────────────

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

    /** Ensure a valid license exists. Auto-activates free plan on first run. */
    public static function requireValid(): void
    {
        $lic = self::current();
        if ($lic['edition'] === 'none') {
            // First run — silently activate free plan
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

    /** Check if a new entry can be created. $current = current count from DB. */
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

    // ── Usage counters (for pdf / excel / duplicate on free plan) ─────────────

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

    // ── Activation ────────────────────────────────────────────────────────────

    /** Activate the free plan — no key required. */
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
     * Validate and activate a license key.
     * Returns ['success'=>bool, 'error'=>string, 'edition'=>string].
     */
    public static function activate(string $rawKey): array
    {
        $rawKey = trim($rawKey);
        $result = self::validateKey($rawKey);

        if (!$result['valid']) {
            return ['success' => false, 'error' => $result['error'], 'edition' => ''];
        }

        $payload = $result['payload'];

        // Machine binding check (if key has machine_id)
        if (!empty($payload['machine_id']) && $payload['machine_id'] !== '*') {
            if ($payload['machine_id'] !== self::machineId()) {
                return ['success' => false, 'error' => 'Cette clé est liée à un autre poste.', 'edition' => ''];
            }
        }

        // Resolve expiry + check reuse: key must not already be used on another machine
        $db = Database::connection();
        $storedRow = $db->prepare("SELECT period, used, used_machine FROM license_keys WHERE key_value = ?");
        $storedRow->execute([$rawKey]);
        $stored  = $storedRow->fetch();

        if ($stored && $stored['used'] && $stored['used_machine'] !== self::machineId()) {
            return ['success' => false, 'error' => 'Cette clé a déjà été activée sur un autre poste.', 'edition' => ''];
        }

        $expires = $stored ? self::periodToDate($stored['period']) : ($payload['expires'] ?? null);

        // Expiry check (only for keys that were already activated before — edge case)
        if ($expires !== null && $expires < date('Y-m-d')) {
            return ['success' => false, 'error' => 'Cette clé de licence a expiré.', 'edition' => ''];
        }

        // Store
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

        // Mark key as used in license_keys table if it was pre-generated
        self::markKeyUsed($rawKey, self::machineId());

        return ['success' => true, 'error' => '', 'edition' => $payload['edition']];
    }

    // ── Stored key management ─────────────────────────────────────────────────

    /**
     * Generate and store a key in the license_keys table.
     * Returns the generated key string.
     */
    public static function generateAndStore(string $edition, string $period): string
    {
        // expires_at is intentionally NULL here — calculated at client activation
        $key = self::generate($edition, null);

        $db = Database::connection();
        $db->prepare(
            "INSERT OR IGNORE INTO license_keys (key_value, edition, period, expires_at)
             VALUES (?, ?, ?, NULL)"
        )->execute([$key, $edition, $period]);

        return $key;
    }

    /** Mark a stored key as used and compute its expiry from the stored period. */
    public static function markKeyUsed(string $key, string $machineId): void
    {
        $db = Database::connection();

        // Fetch the stored period so we can compute expires_at at activation time
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

    // ── Key generation (used by keygen.php) ──────────────────────────────────

    /** Generate a signed license key. */
    public static function generate(string $edition, ?string $expires = null, ?string $machineId = null): string
    {
        $payload = [
            'edition'    => $edition,
            'expires'    => $expires,
            'machine_id' => $machineId ?? '*',
            'issued_at'  => date('Y-m-d'),
        ];
        $payloadB64 = rtrim(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '=');
        $sig        = strtoupper(substr(hash_hmac('sha256', $payloadB64, self::secret()), 0, 32));
        return $payloadB64 . '.' . $sig;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private static function validateKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return ['valid' => false, 'error' => 'Format de clé invalide.', 'payload' => []];
        }

        [$payloadB64, $sig] = $parts;

        $payloadJson = base64_decode($payloadB64);
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

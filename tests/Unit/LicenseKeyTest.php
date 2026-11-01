<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LicenseService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du système de clés de licence.
 *
 * Ces tests n'accèdent pas à la base de données.
 * Un secret injecté via setSecretForTesting() remplace la lecture du fichier.
 */
final class LicenseKeyTest extends TestCase
{
    private const TEST_SECRET = 'unit-test-secret-key-32chars-xxx';

    protected function setUp(): void
    {
        LicenseService::setSecretForTesting(self::TEST_SECRET);
    }

    protected function tearDown(): void
    {
        LicenseService::resetForTesting();
    }

    // ── Génération ────────────────────────────────────────────────────────────

    public function testGeneratedKeyHasTwoParts(): void
    {
        $key = LicenseService::generate('pro');
        $this->assertStringContainsString('.', $key);
        $this->assertCount(2, explode('.', $key, 2));
    }

    public function testSignatureIsThirtyTwoCharsUppercase(): void
    {
        $key  = LicenseService::generate('pro');
        $sig  = explode('.', $key, 2)[1];
        $this->assertSame(32, strlen($sig));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $sig);
    }

    public function testPayloadContainsEdition(): void
    {
        foreach (['pro', 'enterprise'] as $edition) {
            $key        = LicenseService::generate($edition);
            $payloadB64 = explode('.', $key, 2)[0];
            $payload    = json_decode(base64_decode($payloadB64 . '=='), true);
            $this->assertSame($edition, $payload['edition'], "edition mismatch for $edition");
        }
    }

    public function testTwoGeneratedKeysAreDifferent(): void
    {
        // nonce guarantees uniqueness
        $a = LicenseService::generate('pro');
        $b = LicenseService::generate('pro');
        $this->assertNotSame($a, $b);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function testValidProKeyValidates(): void
    {
        $key    = LicenseService::generate('pro');
        $result = $this->callActivate($key);
        // activate() hits the DB; we test validateKey() indirectly via generate+tamper
        // A well-formed key must not fail the format or HMAC check.
        $this->assertStringNotContainsString('Format', $result);
        $this->assertStringNotContainsString('invalide', $result);
        $this->assertStringNotContainsString('falsifiée', $result);
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $key    = LicenseService::generate('pro');
        $parts  = explode('.', $key, 2);
        $tampered = $parts[0] . '.' . str_repeat('X', 32);  // wrong signature
        $result = $this->callActivate($tampered);
        $this->assertSame('Clé invalide ou falsifiée.', $result);
    }

    public function testMalformedKeyNoDotIsRejected(): void
    {
        $result = $this->callActivate('BADKEY');
        $this->assertSame('Format de clé invalide.', $result);
    }

    public function testCorruptBase64IsRejected(): void
    {
        $result = $this->callActivate('!!!.ABCDEF1234567890ABCDEF1234567890AB');
        // base64_decode of '!!!' returns false
        $this->assertSame('Clé corrompue.', $result);
    }

    public function testUnknownEditionIsRejected(): void
    {
        // Build a key with a valid HMAC but unknown edition
        $payload = base64_encode(json_encode([
            'edition'    => 'unknown',
            'expires'    => null,
            'machine_id' => '*',
            'issued_at'  => '2026-01-01',
            'nonce'      => 'aabbccdd',
        ]));
        $payload = rtrim($payload, '=');
        $sig     = strtoupper(substr(hash_hmac('sha256', $payload, self::TEST_SECRET), 0, 32));
        $result  = $this->callActivate($payload . '.' . $sig);
        $this->assertSame('Édition inconnue.', $result);
    }

    // ── InvoiceType ───────────────────────────────────────────────────────────

    public function testInvoiceTypeEnumCoversAllDocumentTypes(): void
    {
        $values = array_map(fn($c) => $c->value, \App\Enums\InvoiceType::cases());
        $this->assertContains('FACTURE PROFORMA', $values);
        $this->assertContains('FACTURE',          $values);
        $this->assertContains('DEVIS',            $values);
        $this->assertContains('AVOIR',            $values);
    }

    public function testCountsAsRevenueForInvoicesOnly(): void
    {
        $this->assertTrue(\App\Enums\InvoiceType::Proforma->countsAsRevenue());
        $this->assertTrue(\App\Enums\InvoiceType::Invoice->countsAsRevenue());
        $this->assertFalse(\App\Enums\InvoiceType::Quote->countsAsRevenue());
        $this->assertFalse(\App\Enums\InvoiceType::CreditNote->countsAsRevenue());
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Call the internal validateKey() path by using Reflection,
     * avoiding the DB-access part of activate().
     */
    private function callActivate(string $key): string
    {
        $ref    = new \ReflectionClass(LicenseService::class);
        $method = $ref->getMethod('validateKey');
        $method->setAccessible(true);
        $result = $method->invoke(null, $key);
        return $result['valid'] ? '' : $result['error'];
    }
}

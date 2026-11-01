<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AmountInWordsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de AmountInWordsService.
 *
 * Le service retourne des phrases complètes du type :
 *   "Arrêter la présente facture Proforma à la somme de <Mots> <Devise>"
 * Le premier mot des montants est capitalisé (ucfirst).
 * On utilise assertStringContainsStringIgnoringCase pour la robustesse sur la casse.
 */
final class AmountInWordsServiceTest extends TestCase
{
    private AmountInWordsService $svc;

    protected function setUp(): void
    {
        $this->svc = new AmountInWordsService();
    }

    public function testZero(): void
    {
        $result = $this->svc->convert(0);
        $this->assertSame('Arrêter à zéro Francs CFA', $result);
    }

    public function testSimpleUnit(): void
    {
        $result = $this->svc->convert(5);
        $this->assertStringContainsStringIgnoringCase('cinq', $result);
        $this->assertStringContainsString('Francs CFA', $result);
    }

    public function testTens(): void
    {
        $result = $this->svc->convert(21);
        $this->assertStringContainsStringIgnoringCase('vingt-et-un', $result);
    }

    public function testHundred(): void
    {
        $result = $this->svc->convert(100);
        $this->assertStringContainsStringIgnoringCase('cent', $result);
        // 100 = exactly 1×100, no 's'
        $this->assertStringNotContainsStringIgnoringCase('cents', $result);
    }

    public function testTwoHundredPlural(): void
    {
        // 200 = 2×100 standalone → "deux cents"
        $result = $this->svc->convert(200);
        $this->assertStringContainsStringIgnoringCase('deux cents', $result);
    }

    public function testTwoHundredOneNoPlural(): void
    {
        // 201 = 2×100 + 1 → "deux cent un" (pas de 's')
        $result = $this->svc->convert(201);
        $this->assertStringContainsStringIgnoringCase('deux cent un', $result);
    }

    public function testThousand(): void
    {
        $result = $this->svc->convert(1000);
        $this->assertStringContainsStringIgnoringCase('mille', $result);
    }

    public function testFiveHundredThousand(): void
    {
        // 500 000 = cinq cent mille (pas de 's' car "cent" est multiplicateur)
        $result = $this->svc->convert(500_000);
        $this->assertStringContainsStringIgnoringCase('cinq cent mille', $result);
        $this->assertStringNotContainsStringIgnoringCase('cinq cents mille', $result);
    }

    public function testMillion(): void
    {
        $result = $this->svc->convert(1_000_000);
        $this->assertStringContainsStringIgnoringCase('million', $result);
    }

    public function testCustomCurrency(): void
    {
        $result = $this->svc->convert(100, 'euros');
        $this->assertStringContainsString('euros', $result);
    }

    public function testCapitalization(): void
    {
        // Chaque montant commence par une majuscule (ucfirst)
        $result = $this->svc->convert(42);
        $this->assertMatchesRegularExpression(
            '/à la somme de [A-Z]/',
            $result
        );
    }

    public function testEighty(): void
    {
        // 80 standalone = "quatre-vingts" (avec 's')
        $result = $this->svc->convert(80);
        $this->assertStringContainsStringIgnoringCase('quatre-vingts', $result);
    }

    public function testEightyOne(): void
    {
        // 81 = "quatre-vingt-un" (sans 's')
        $result = $this->svc->convert(81);
        $this->assertStringContainsStringIgnoringCase('quatre-vingt-un', $result);
        $this->assertStringNotContainsStringIgnoringCase('quatre-vingts-un', $result);
    }

    public function testSeventyOne(): void
    {
        // 71 = "soixante-et-onze"
        $result = $this->svc->convert(71);
        $this->assertStringContainsStringIgnoringCase('soixante-et-onze', $result);
    }
}

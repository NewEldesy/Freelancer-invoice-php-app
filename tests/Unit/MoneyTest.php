<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromIntStoresAmount(): void
    {
        $m = Money::fromInt(150_000);
        $this->assertSame(150_000, $m->amountCentimes);
    }

    public function testAddTwoAmounts(): void
    {
        $a = Money::fromInt(100_000);
        $b = Money::fromInt(50_000);
        $this->assertSame(150_000, $a->add($b)->amountCentimes);
    }

    public function testMultiplyByQuantity(): void
    {
        $unit = Money::fromInt(75_000);
        $this->assertSame(225_000, $unit->multiply(3)->amountCentimes);
    }

    public function testApplyTaxDeductionFivePercent(): void
    {
        // 730 000 × 5 % prélevé = 693 500 net
        $ht  = Money::fromInt(730_000);
        $net = $ht->applyTaxDeduction(5.0);
        $this->assertSame(693_500, $net->amountCentimes);
    }

    public function testTaxAmountFivePercent(): void
    {
        $ht  = Money::fromInt(730_000);
        $tax = $ht->taxAmount(5.0);
        $this->assertSame(36_500, $tax->amountCentimes);
    }

    public function testTaxDeductionPlusTaxAmountEqualsOriginal(): void
    {
        $ht  = Money::fromInt(500_000);
        $net = $ht->applyTaxDeduction(10.0);
        $tax = $ht->taxAmount(10.0);
        $this->assertSame($ht->amountCentimes, $net->amountCentimes + $tax->amountCentimes);
    }

    public function testFormatWithSpaceSeparator(): void
    {
        $m = Money::fromInt(1_234_567);
        $this->assertSame('1 234 567 FCFA', $m->format());
    }

    public function testFormatNumberWithoutCurrency(): void
    {
        $m = Money::fromInt(693_500);
        $this->assertSame('693 500', $m->formatNumber());
    }

    public function testCurrencyDefaultsToFCFA(): void
    {
        $m = Money::fromInt(0);
        $this->assertSame('FCFA', $m->currency);
    }

    public function testMultiplyByZeroGivesZero(): void
    {
        $m = Money::fromInt(500_000);
        $this->assertSame(0, $m->multiply(0)->amountCentimes);
    }
}

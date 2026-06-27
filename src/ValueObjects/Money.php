<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Money
{
    public function __construct(
        public readonly int $amountCentimes,
        public readonly string $currency = 'FCFA',
    ) {}

    public static function fromInt(int $amount): self
    {
        return new self($amount);
    }

    public function add(self $other): self
    {
        return new self($this->amountCentimes + $other->amountCentimes, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amountCentimes * $quantity, $this->currency);
    }

    public function applyTaxDeduction(float $ratePercent): self
    {
        $deduction = (int) round($this->amountCentimes * $ratePercent / 100);
        return new self($this->amountCentimes - $deduction, $this->currency);
    }

    public function taxAmount(float $ratePercent): self
    {
        return new self((int) round($this->amountCentimes * $ratePercent / 100), $this->currency);
    }

    public function format(): string
    {
        return number_format($this->amountCentimes, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function formatNumber(): string
    {
        return number_format($this->amountCentimes, 0, ',', ' ');
    }
}

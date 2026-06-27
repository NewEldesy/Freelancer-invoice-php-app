<?php

declare(strict_types=1);

namespace App\DTO;

use App\ValueObjects\Money;

final readonly class InvoiceLineDTO
{
    public function __construct(
        public string $description,
        public int    $quantity,
        public Money  $unitPrice,
    ) {}

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) ($data['description'] ?? ''),
            quantity:    (int)    ($data['quantity']    ?? 1),
            unitPrice:   Money::fromInt((int) ($data['unit_price'] ?? 0)),
        );
    }
}

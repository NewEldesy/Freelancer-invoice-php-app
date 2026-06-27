<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\InvoiceType;
use App\ValueObjects\Money;
use DateTimeImmutable;

final readonly class InvoiceDTO
{
    /** @param InvoiceLineDTO[] $lines */
    public function __construct(
        public InvoiceType        $type,
        public string             $number,
        public DateTimeImmutable  $issuedAt,
        public DateTimeImmutable  $dueAt,
        public CompanyDTO         $issuer,
        public ClientDTO          $client,
        public string             $subject,
        public array              $lines,
        public float              $taxRatePercent,
        public string             $taxLabel,
        public string             $signatoryTitle,
        public string             $signatoryName,
        public string             $footerText,
    ) {}

    public function totalHT(): Money
    {
        return array_reduce(
            $this->lines,
            fn(Money $carry, InvoiceLineDTO $line) => $carry->add($line->total()),
            Money::fromInt(0),
        );
    }

    public function taxAmount(): Money
    {
        return $this->totalHT()->taxAmount($this->taxRatePercent);
    }

    public function totalNet(): Money
    {
        return $this->totalHT()->applyTaxDeduction($this->taxRatePercent);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn(array $l) => InvoiceLineDTO::fromArray($l),
            $data['lines'] ?? [],
        );

        return new self(
            type:            InvoiceType::from($data['type'] ?? InvoiceType::Proforma->value),
            number:          (string) ($data['number']   ?? ''),
            issuedAt:        new DateTimeImmutable($data['issued_at'] ?? 'now'),
            dueAt:           new DateTimeImmutable($data['due_at']    ?? '+1 month'),
            issuer:          CompanyDTO::fromArray($data['issuer']  ?? []),
            client:          ClientDTO::fromArray($data['client']   ?? []),
            subject:         (string) ($data['subject']  ?? ''),
            lines:           $lines,
            taxRatePercent:  (float)  ($data['tax_rate'] ?? 5.0),
            taxLabel:        (string) ($data['tax_label'] ?? 'Prelevement 5%'),
            signatoryTitle:  (string) ($data['signatory_title'] ?? 'Le Président'),
            signatoryName:   (string) ($data['signatory_name']  ?? ''),
            footerText:      (string) ($data['footer_text']     ?? ''),
        );
    }
}

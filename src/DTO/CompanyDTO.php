<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CompanyDTO
{
    public function __construct(
        public string $name,
        public string $address,
        public string $phone,
        public string $email,
        public string $ifu,
        public string $logoPath = '',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name:     (string) ($data['name']      ?? ''),
            address:  (string) ($data['address']   ?? ''),
            phone:    (string) ($data['phone']      ?? ''),
            email:    (string) ($data['email']      ?? ''),
            ifu:      (string) ($data['ifu']        ?? ''),
            logoPath: (string) ($data['logo_path']  ?? ''),
        );
    }
}

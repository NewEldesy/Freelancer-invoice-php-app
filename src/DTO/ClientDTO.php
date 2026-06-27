<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ClientDTO
{
    public function __construct(
        public string $name,
        public string $address,
        public string $contact,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name:    (string) ($data['name']    ?? ''),
            address: (string) ($data['address'] ?? ''),
            contact: (string) ($data['contact'] ?? ''),
        );
    }
}

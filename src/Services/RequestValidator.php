<?php

declare(strict_types=1);

namespace App\Services;

final class RequestValidator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function validate(array $data): bool
    {
        $this->errors = [];

        $this->required($data, 'number',       'Le numéro de facture est obligatoire');
        $this->required($data, 'issuer_name',  "Le nom de l'entreprise est obligatoire");
        $this->required($data, 'issuer_email', "L'email est obligatoire");

        if (!empty($data['issuer_email']) && !filter_var($data['issuer_email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['issuer_email'] = 'Email invalide';
        }

        $lines = $data['lines'] ?? [];
        if (empty($lines) || !array_filter($lines, fn($l) => trim($l['description'] ?? '') !== '')) {
            $this->errors['lines'] = 'Au moins une ligne est requise';
        }

        return empty($this->errors);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @param array<string, mixed> $data */
    private function required(array $data, string $key, string $message): void
    {
        if (empty($data[$key])) {
            $this->errors[$key] = $message;
        }
    }
}

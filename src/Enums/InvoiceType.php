<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceType: string
{
    case Proforma = 'FACTURE PROFORMA';
    case Invoice  = 'FACTURE';
    case Quote    = 'DEVIS';

    public function dueDateLabel(): string
    {
        return match($this) {
            self::Proforma => 'Date limite du Proforma',
            self::Invoice  => "Date d'échéance",
            self::Quote    => 'Valable jusqu\'au',
        };
    }
}

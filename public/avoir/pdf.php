<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\InvoiceRepository;
use App\DTO\InvoiceDTO;
use App\Services\AmountInWordsService;
use App\Services\LicenseService;
use App\Services\PdfGeneratorService;

$repo = new InvoiceRepository();
$id   = (int) ($_GET['id'] ?? 0);

$record = $repo->find($id);
if ($record === null || $record['type'] !== 'AVOIR') {
    http_response_code(404);
    exit('Avoir introuvable.');
}

if (!LicenseService::canAdd('pdf', LicenseService::getCounter('pdf'))) {
    http_response_code(403);
    exit('Limite du plan gratuit atteinte (' . LicenseService::pdfMax() . ' exports PDF). Activez une licence Pro.');
}

$lines = $repo->linesOf($id);

$invoiceData = [
    'type'            => $record['type'],
    'number'          => $record['number'],
    'issued_at'       => $record['issued_at'],
    'due_at'          => $record['due_at'],
    'subject'         => $record['subject'],
    'tax_rate'        => $record['tax_rate'],
    'tax_label'       => $record['tax_label'],
    'signatory_title' => $record['signatory_title'],
    'signatory_name'  => $record['signatory_name'],
    'footer_text'     => $record['footer_text'],
    'issuer' => [
        'name'      => $record['issuer_name'],
        'address'   => $record['issuer_address'],
        'phone'     => $record['issuer_phone'],
        'email'     => $record['issuer_email'],
        'ifu'       => $record['issuer_ifu'],
        'logo_path' => $record['issuer_logo_path'],
    ],
    'client' => [
        'name'    => $record['client_name'],
        'address' => $record['client_address'],
        'contact' => $record['client_contact'],
    ],
    'lines' => array_merge(
        array_map(fn($l) => [
            'description' => $l['description'],
            'quantity'    => $l['quantity'],
            'unit_price'  => $l['unit_price'],
        ], $lines),
        $record['prestation_amount'] > 0 ? [[
            'description' => $record['prestation_label'],
            'quantity'    => 1,
            'unit_price'  => $record['prestation_amount'],
        ]] : []
    ),
];

$dto       = new InvoiceDTO($invoiceData);
$words     = new AmountInWordsService();
$generator = new PdfGeneratorService($words);

LicenseService::incrementCounter('pdf');

$filename = 'avoir-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $record['number']) . '.pdf';
$generator->streamPdf($dto, $filename);

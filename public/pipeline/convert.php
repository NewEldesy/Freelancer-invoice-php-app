<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Database\OpportunityRepository;
use App\Database\SettingsRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pipeline/index.php');
    exit;
}
Auth::verifyCsrf();

$oppRepo  = new OpportunityRepository();
$invRepo  = new InvoiceRepository();
$settings = (new SettingsRepository())->all();

$id  = (int) ($_POST['id'] ?? 0);
$opp = $oppRepo->find($id);

if ($opp === null) {
    header('Location: /pipeline/index.php');
    exit;
}

/* Idempotency: already converted — redirect to the existing invoice */
if (!empty($opp['invoice_id'])) {
    header('Location: /invoice/edit.php?id=' . (int)$opp['invoice_id']);
    exit;
}

/* Build invoice data pre-filled from the opportunity */
$today     = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+1 month'));

$invData = [
    'number'          => $invRepo->nextNumber(),
    'type'            => 'FACTURE PROFORMA',
    'status'          => 'brouillon',
    'subject'         => $opp['title'],
    'issued_at'       => $today,
    'due_at'          => $nextMonth,
    'issuer_name'     => $settings['issuer_name']     ?? '',
    'issuer_address'  => $settings['issuer_address']  ?? '',
    'issuer_phone'    => $settings['issuer_phone']    ?? '',
    'issuer_email'    => $settings['issuer_email']    ?? '',
    'issuer_ifu'      => $settings['issuer_ifu']      ?? '',
    'issuer_logo_path'=> $settings['issuer_logo_path']?? '',
    'client_name'     => $opp['client_name']    ?? '',
    'client_address'  => $opp['client_address'] ?? '',
    'client_contact'  => $opp['client_contact'] ?? '',
    'tax_rate'        => (float) ($settings['tax_rate']   ?? 5),
    'tax_label'       => $settings['tax_label']           ?? 'Prelevement 5%',
    'signatory_title' => $settings['signatory_title']     ?? '',
    'signatory_name'  => $settings['signatory_name']      ?? '',
    'footer_text'     => $settings['footer_text']         ?? '',
    'prestation_label'  => $settings['prestation_label']  ?? 'Frais de prestation',
    'prestation_amount' => 0,
    'total_ht'          => 0,
    'total_net'         => 0,
    'lines'             => [
        ['description' => $opp['title'] ?: 'Prestation', 'quantity' => 1, 'unit_price' => (int)$opp['estimated_amount']],
    ],
];

$invoiceId = $invRepo->create($invData);

/* Mark opportunity as won and link the invoice */
$oppRepo->linkInvoice($id, $invoiceId);

header('Location: /invoice/edit.php?id=' . $invoiceId . '&from_pipeline=1');
exit;

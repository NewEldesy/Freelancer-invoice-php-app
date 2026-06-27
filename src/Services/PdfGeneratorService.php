<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\InvoiceDTO;
use App\DTO\InvoiceLineDTO;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfGeneratorService
{
    public function __construct(
        private readonly AmountInWordsService $wordsService,
    ) {}

    public function generate(InvoiceDTO $invoice): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml($invoice), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildHtml(InvoiceDTO $invoice): string
    {
        $totalHT   = $invoice->totalHT();
        $taxAmount = $invoice->taxAmount();
        $totalNet  = $invoice->totalNet();
        $amountWords = $this->wordsService->convert($totalNet->amountCentimes);

        $logoHtml = $invoice->issuer->logoPath && file_exists($invoice->issuer->logoPath)
            ? '<img src="' . $invoice->issuer->logoPath . '" style="max-width:110px;max-height:75px;">'
            : '';

        $linesHtml  = '';
        $ref        = 1;
        foreach ($invoice->lines as $line) {
            /** @var InvoiceLineDTO $line */
            $linesHtml .= sprintf(
                '<tr style="background:%s">
                    <td>%d</td>
                    <td>%s</td>
                    <td style="text-align:right">%d</td>
                    <td style="text-align:right">%s</td>
                    <td style="text-align:right">%s</td>
                </tr>',
                $ref % 2 === 0 ? '#fafafa' : '#fff',
                $ref++,
                htmlspecialchars($line->description),
                $line->quantity,
                $line->unitPrice->formatNumber(),
                $line->total()->formatNumber(),
            );
        }

        $dueDateLabel = $invoice->type->dueDateLabel();
        $footerEscaped = nl2br(htmlspecialchars($invoice->footerText));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #222;
    margin: 0;
    padding: 0 0 60px 0;
  }
  table { border-collapse: collapse; }
  .layout { width: 100%; }
  .items-table { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 10px; }
  .items-table th {
    background: #1a1a2e;
    color: #fff;
    padding: 6px 8px;
    font-weight: bold;
    font-size: 10px;
  }
  .items-table td { padding: 5px 8px; border-bottom: 1px solid #e8e8e8; }
  .totals-table { border-collapse: collapse; }
  .totals-table td { padding: 3px 8px; font-size: 11px; }
  .total-final td {
    border-top: 2px solid #1a1a2e;
    font-weight: bold;
    font-size: 12px;
    padding-top: 5px;
  }
  hr { border: none; border-top: 1px solid #ddd; margin: 6px 0; }
</style>
</head>
<body>

<!-- ═══ EN-TÊTE ═══ -->
<table class="layout" style="margin-bottom:12px;">
  <tr>
    <td style="width:50%;vertical-align:top;">
      {$logoHtml}
      <br>
      <strong style="font-size:13px;">{$invoice->issuer->name}</strong><br>
      <span style="font-size:10px;line-height:1.7;">
        Sise à {$invoice->issuer->address}<br>
        Tél : {$invoice->issuer->phone}<br>
        Email : {$invoice->issuer->email}
      </span>
    </td>
    <td style="width:50%;vertical-align:top;text-align:right;">
      <div style="font-size:22px;font-weight:bold;color:#1a1a2e;margin-bottom:8px;">{$invoice->type->value}</div>
      <span style="font-size:10px;line-height:1.8;">
        N° Facture : {$invoice->number}<br>
        Date : {$invoice->issuedAt->format('d/m/Y')}<br>
        {$dueDateLabel} : {$invoice->dueAt->format('d/m/Y')}
      </span>
    </td>
  </tr>
</table>

<div style="font-size:10px;margin-bottom:6px;">N°IFU : {$invoice->issuer->ifu}</div>
<hr>

<!-- ═══ ENVOYÉ À ═══ -->
<table class="layout" style="margin:8px 0 12px;">
  <tr>
    <td style="width:50%;"></td>
    <td style="width:50%;vertical-align:top;">
      <strong style="font-size:11px;">Envoyé à :</strong><br>
      <span style="font-size:10.5px;line-height:1.7;">
        {$invoice->client->name}<br>
        {$invoice->client->address}<br>
        {$invoice->client->contact}
      </span>
    </td>
  </tr>
</table>

<!-- ═══ OBJET ═══ -->
<p style="font-size:11px;margin-bottom:12px;">
  <strong>Objet</strong> : {$invoice->subject}
</p>

<!-- ═══ LIGNES ═══ -->
<table class="items-table">
  <thead>
    <tr>
      <th style="width:6%;text-align:center;">REF</th>
      <th style="width:46%;text-align:left;">DESCRIPTION</th>
      <th style="width:10%;text-align:right;">QTE</th>
      <th style="width:18%;text-align:right;">P. U</th>
      <th style="width:18%;text-align:right;">TOTAL</th>
    </tr>
  </thead>
  <tbody>{$linesHtml}</tbody>
</table>

<!-- ═══ TOTAUX ═══ -->
<table class="layout" style="margin-bottom:14px;">
  <tr>
    <td style="width:55%;"></td>
    <td style="width:45%;text-align:right;">
      <table class="totals-table" style="width:100%;">
        <tr>
          <td>TOTAL H.T</td>
          <td style="text-align:right;font-weight:600;">{$totalHT->formatNumber()}</td>
        </tr>
        <tr>
          <td>{$invoice->taxLabel}</td>
          <td style="text-align:right;font-weight:600;">{$taxAmount->formatNumber()}</td>
        </tr>
        <tr class="total-final">
          <td>Total Net à Payer</td>
          <td style="text-align:right;">{$totalNet->formatNumber()}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- ═══ MONTANT EN LETTRES ═══ -->
<p style="font-weight:bold;font-size:10.5px;text-align:center;margin:10px 0 24px;line-height:1.6;">
  {$amountWords}
</p>

<!-- ═══ SIGNATURE ═══ -->
<table class="layout" style="margin-bottom:40px;">
  <tr>
    <td style="width:60%;"></td>
    <td style="width:40%;text-align:right;font-size:11px;">
      <strong>{$invoice->signatoryTitle}</strong>
      <br><br><br>
      <em>{$invoice->signatoryName}</em>
    </td>
  </tr>
</table>

<!-- ═══ PIED DE PAGE ═══ -->
<div style="position:fixed;bottom:0;left:0;right:0;border-top:1px solid #ccc;padding-top:6px;font-size:8.5px;color:#555;text-align:center;line-height:1.7;">
  {$footerEscaped}
</div>

</body>
</html>
HTML;
    }
}

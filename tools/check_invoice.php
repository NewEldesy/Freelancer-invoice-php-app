<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/invoices.sqlite');

$inv = $pdo->query("SELECT id, number, status, due_at, total_net, issued_at FROM invoices WHERE number='20260627-1'")->fetch(PDO::FETCH_ASSOC);
if (!$inv) { echo "Facture introuvable\n"; exit; }

$paid = $pdo->query("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE invoice_id=" . $inv['id'])->fetch(PDO::FETCH_ASSOC);

$today   = date('Y-m-d');
$overdue = $inv['status'] === 'envoyée' && !empty($inv['due_at']) && $inv['due_at'] < $today;
$pct     = $inv['total_net'] > 0 ? round($paid['total'] / $inv['total_net'] * 100) : 0;

echo "Numéro   : " . $inv['number'] . "\n";
echo "Statut   : " . $inv['status'] . "\n";
echo "Émise le : " . $inv['issued_at'] . "\n";
echo "Échéance : " . ($inv['due_at'] ?: '(non définie)') . "\n";
echo "Total net: " . $inv['total_net'] . " FCFA\n";
echo "Encaissé : " . $paid['total'] . " FCFA ($pct%)\n";
echo "Aujourd'hui : $today\n";
echo "En retard : " . ($overdue ? "OUI" : "NON") . "\n";

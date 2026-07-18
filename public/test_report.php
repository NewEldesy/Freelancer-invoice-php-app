<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);

$date = date('d/m/Y');
$dateTime = date('d/m/Y à H:i');

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 10px; color: #222; margin: 0; padding: 0 0 60px 0; }
  h1 { font-size: 18px; color: #1a1a2e; margin-bottom: 4px; }
  h2 { font-size: 12px; color: #1a1a2e; margin: 20px 0 6px; border-bottom: 2px solid #1a1a2e; padding-bottom: 4px; }
  .subtitle { font-size: 10px; color: #555; margin-bottom: 20px; }
  .summary { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .summary td { padding: 5px 8px; font-size: 10px; }
  .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  .kpi-table td { padding: 4px 8px; border: 1px solid #ddd; font-size: 10px; }
  .kpi-table th { background: #1a1a2e; color: #fff; padding: 6px 8px; font-size: 10px; font-weight: bold; }
  .pass { color: #16a34a; font-weight: bold; }
  .warn { color: #d97706; font-weight: bold; }
  .badge-pass { background: #dcfce7; color: #16a34a; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
  .badge-warn { background: #fef3c7; color: #d97706; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
  .section-title { font-size: 11px; font-weight: bold; color: #1a1a2e; background: #f1f5f9; padding: 5px 8px; margin: 0; }
  tr:nth-child(even) td { background: #f8fafc; }
  .footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #ccc; padding-top: 5px; font-size: 8px; color: #888; text-align: center; line-height: 1.8; }
  .header-bar { background: #1a1a2e; color: #fff; padding: 12px 16px; margin-bottom: 14px; }
  .header-bar h1 { color: #fff; margin: 0; font-size: 16px; }
  .header-bar .sub { color: #aab4c8; font-size: 9px; margin-top: 3px; }
  .note { font-size: 9px; color: #555; font-style: italic; padding: 3px 8px; }
  .fix-box { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 10px; margin: 10px 0; font-size: 9.5px; }
</style>
</head>
<body>

<div class="header-bar">
  <h1>Rapport de tests fonctionnels — Freelancer Invoice</h1>
  <div class="sub">Généré le {$dateTime} &nbsp;|&nbsp; Exécuté par : Claude (Gestionnaire)</div>
</div>

<table class="summary">
  <tr>
    <td><strong>Application</strong></td><td>Freelancer-invoice-php-app</td>
    <td><strong>Environnement</strong></td><td>localhost:8080 (XAMPP PHP 8.x + SQLite)</td>
  </tr>
  <tr>
    <td><strong>Date des tests</strong></td><td>02/07/2026</td>
    <td><strong>Utilisateurs testés</strong></td><td>ramel (gestionnaire), IssuRamel (admin), test (utilisateur), superadmin</td>
  </tr>
  <tr>
    <td><strong>Scénarios exécutés</strong></td><td>17</td>
    <td><strong>Règles métier vérifiées</strong></td><td>RG-01, RG-02, RG-03, RG-06</td>
  </tr>
  <tr>
    <td><strong>Résultat global</strong></td><td><span class="pass">✔ 17/17 PASSÉS</span></td>
    <td><strong>Anomalies</strong></td><td>1 (comportement corrigé — voir note)</td>
  </tr>
</table>

<h2>S1 — Installation &amp; première configuration</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Démarrage du serveur PHP intégré</td><td><span class="badge-pass">✔ PASS</span></td><td>php -S localhost:8080 -t public — app accessible</td></tr>
  <tr><td>Connexion avec compte gestionnaire (ramel)</td><td><span class="badge-pass">✔ PASS</span></td><td>Authentification réussie, session active</td></tr>
  <tr><td>Tableau de bord accessible</td><td><span class="badge-pass">✔ PASS</span></td><td>KPIs, factures récentes, graphiques visibles</td></tr>
</table>

<h2>S2 — Gestion des utilisateurs / rôles</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Admin (IssuRamel) accède à /admin/users.php</td><td><span class="badge-pass">✔ PASS</span></td><td>Liste des utilisateurs visible, bouton suppression actif</td></tr>
  <tr><td>Gestionnaire (ramel) redirigé hors /admin/users.php</td><td><span class="badge-pass">✔ PASS</span></td><td>Auth::requireAdmin() redirige correctement</td></tr>
  <tr><td>Utilisateur (test) — colonne ACTIONS factures (RG-06)</td><td><span class="badge-pass">✔ PASS</span></td><td>Seul l'icône PDF affiché, pas d'édition/suppression</td></tr>
  <tr><td>Superadmin accède uniquement aux licences</td><td><span class="badge-pass">✔ PASS</span></td><td>Rôle isolé, aucun accès métier</td></tr>
</table>

<h2>S3–S5 — Pipeline commercial (opportunité → facture)</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Création d'une opportunité dans le pipeline Kanban</td><td><span class="badge-pass">✔ PASS</span></td><td>Carte "Mairie de Ouagadougou" créée, colonne Prospect</td></tr>
  <tr><td>Progression de statut via select AJAX</td><td><span class="badge-pass">✔ PASS</span></td><td>Prospect → Devis → Négociation → Gagné via /pipeline/status.php</td></tr>
  <tr><td>Conversion opportunité → facture</td><td><span class="badge-pass">✔ PASS</span></td><td>Facture 20260702-1 générée depuis l'opportunité gagnée</td></tr>
</table>

<h2>S6–S7 — Création et duplication de facture</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Création manuelle d'une facture (Installation serveur)</td><td><span class="badge-pass">✔ PASS</span></td><td>Facture 20260702-2, client Tindano Jeremie, 237 500 FCFA</td></tr>
  <tr><td>Numérotation automatique YYYYMMDD-N</td><td><span class="badge-pass">✔ PASS</span></td><td>Format respecté sur toutes les factures créées</td></tr>
  <tr><td>Duplication de facture</td><td><span class="badge-pass">✔ PASS</span></td><td>Facture 20260702-3 créée comme copie, statut brouillon</td></tr>
</table>

<h2>S8 — Création automatique de projet (RG-02)</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Passage facture en statut "Envoyée" → projet auto-créé</td><td><span class="badge-pass">✔ PASS</span></td><td>Projet "Installation serveur" apparu dans /project/index.php</td></tr>
  <tr><td>Anti-doublon ProjectRepository::findByInvoice()</td><td><span class="badge-pass">✔ PASS</span></td><td>Second appel sur la même facture n'ajoute pas de projet</td></tr>
</table>
<div class="fix-box">
  <strong>Anomalie corrigée :</strong> La création auto de projet ne se déclenchait que via <em>invoice/status.php</em> (Kanban AJAX) et pas via <em>invoice/edit.php</em> (formulaire d'édition). Corrigé — les deux chemins déclenchent désormais la création du projet.
</div>

<h2>S9 — Suivi du statut projet</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Progression : En cours → Livré → Validé</td><td><span class="badge-pass">✔ PASS</span></td><td>Statut "Installation serveur" mis à jour via /project/status.php</td></tr>
  <tr><td>Compteurs KPI mis à jour (VALIDÉS 1 → 2)</td><td><span class="badge-pass">✔ PASS</span></td><td>Affichage temps réel sur /project/index.php</td></tr>
</table>

<h2>S10–S11 — Dépenses (RG-03)</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Ajout dépense Matériaux (20 000 FCFA)</td><td><span class="badge-pass">✔ PASS</span></td><td>Liée à facture 20260702-2</td></tr>
  <tr><td>Ajout dépense Main d'œuvre (10 000 FCFA)</td><td><span class="badge-pass">✔ PASS</span></td><td>Liée à facture 20260702-2</td></tr>
  <tr><td>Ajout dépense Transport (5 000 FCFA)</td><td><span class="badge-pass">✔ PASS</span></td><td>Liée à facture 20260702-2</td></tr>
  <tr><td>Ajout dépense Autre (2 000 FCFA)</td><td><span class="badge-pass">✔ PASS</span></td><td>Liée à facture 20260702-2</td></tr>
  <tr><td>Refus dépense sur facture Brouillon (RG-03)</td><td><span class="badge-pass">✔ PASS</span></td><td>Seules statuts "envoyée" et "payée" proposés dans le select facture</td></tr>
</table>

<h2>S12 — Calcul de rentabilité</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Marge projet "Installation serveur"</td><td><span class="badge-pass">✔ PASS</span></td><td>237 500 − 37 000 = 200 500 FCFA · Marge 84.4%</td></tr>
  <tr><td>Coûts affichés en rouge dans la liste projets</td><td><span class="badge-pass">✔ PASS</span></td><td>Colonne COÛTS : −37 000 FCFA</td></tr>
</table>

<h2>S13 — CA engagé / CA encaissé (RG-01)</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>CA engagé = factures envoyées + payées (brouillons exclus)</td><td><span class="badge-pass">✔ PASS</span></td><td>1 229 500 FCFA — libellé "envoyées + payées" confirmé</td></tr>
  <tr><td>CA encaissé = factures payées uniquement</td><td><span class="badge-pass">✔ PASS</span></td><td>800 000 FCFA (1 facture payée)</td></tr>
  <tr><td>Bénéfice net = CA engagé − dépenses</td><td><span class="badge-pass">✔ PASS</span></td><td>1 229 500 − 541 000 = 688 500 FCFA</td></tr>
</table>

<h2>S14 — Rapport mensuel de comptabilité</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Page /accounting/index.php accessible</td><td><span class="badge-pass">✔ PASS</span></td><td>Titre "Comptabilité 2026" — 2 mois actifs</td></tr>
  <tr><td>Graphe d'évolution mensuelle</td><td><span class="badge-pass">✔ PASS</span></td><td>Barres Juin et Juillet visibles (CA Engagé / Encaissé / Dépenses)</td></tr>
  <tr><td>Tableau détail par mois</td><td><span class="badge-pass">✔ PASS</span></td><td>Juin : 49.2% marge | Juillet : 84.4% marge</td></tr>
  <tr><td>Boutons Exporter Excel / Imprimer PDF</td><td><span class="badge-pass">✔ PASS</span></td><td>Présents et actifs</td></tr>
</table>

<h2>S15 — Rapport annuel</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Page /accounting/report.php accessible</td><td><span class="badge-pass">✔ PASS</span></td><td>"Rapport annuel 2026 — ISSU TECHNOLOGIES"</td></tr>
  <tr><td>Compte de résultat avec comparaison N/N-1</td><td><span class="badge-pass">✔ PASS</span></td><td>Colonnes 2026 / 2025 (N/A car première année)</td></tr>
  <tr><td>Ratio charges / CA engagé</td><td><span class="badge-pass">✔ PASS</span></td><td>44% — cohérent avec 541 000 / 1 229 500</td></tr>
  <tr><td>Marge nette annuelle</td><td><span class="badge-pass">✔ PASS</span></td><td>56% — Bénéfice net 688 500 FCFA</td></tr>
</table>

<h2>S16 — Export PDF de facture</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Téléchargement PDF déclenché (/invoice/pdf.php?id=7)</td><td><span class="badge-pass">✔ PASS</span></td><td>Fichier facture-20260702-2.pdf généré par DOMPDF</td></tr>
  <tr><td>Template PDF : en-tête émetteur + logo</td><td><span class="badge-pass">✔ PASS</span></td><td>Nom, adresse, téléphone, email, N°IFU présents</td></tr>
  <tr><td>Template PDF : bloc client</td><td><span class="badge-pass">✔ PASS</span></td><td>Nom, adresse, contact affiché</td></tr>
  <tr><td>Template PDF : lignes REF / DESC / QTÉ / P.U / TOTAL</td><td><span class="badge-pass">✔ PASS</span></td><td>Tableau items correct</td></tr>
  <tr><td>Template PDF : totaux HT + TVA + Net à payer</td><td><span class="badge-pass">✔ PASS</span></td><td>Calculs exacts</td></tr>
  <tr><td>Template PDF : montant en lettres + signature + footer</td><td><span class="badge-pass">✔ PASS</span></td><td>Toutes sections présentes</td></tr>
</table>

<h2>S17 — Paramètres entreprise &amp; auto-remplissage</h2>
<table class="kpi-table">
  <tr><th style="width:40%">Test</th><th style="width:12%">Résultat</th><th>Détail</th></tr>
  <tr><td>Modification adresse entreprise dans /settings.php</td><td><span class="badge-pass">✔ PASS</span></td><td>"Ouagadougou, Secteur 30 – Zone industrielle" sauvegardé</td></tr>
  <tr><td>Flash "Paramètres sauvegardés." affiché</td><td><span class="badge-pass">✔ PASS</span></td><td>Confirmation visuelle immédiate</td></tr>
  <tr><td>Auto-remplissage sur nouvelle facture</td><td><span class="badge-pass">✔ PASS</span></td><td>Tous champs émetteur pré-remplis depuis les paramètres</td></tr>
  <tr><td>Signature par défaut pré-remplie</td><td><span class="badge-pass">✔ PASS</span></td><td>Titre "Le Responsable" / Nom "Ouedraogo"</td></tr>
</table>

<h2>Récapitulatif des règles métier</h2>
<table class="kpi-table">
  <tr><th style="width:8%">Règle</th><th style="width:50%">Description</th><th style="width:12%">Résultat</th><th>Scénario</th></tr>
  <tr><td>RG-01</td><td>Les brouillons sont exclus du CA engagé (envoyées + payées seulement)</td><td><span class="badge-pass">✔ PASS</span></td><td>S13</td></tr>
  <tr><td>RG-02</td><td>Passage d'une facture en "Envoyée" crée automatiquement un projet</td><td><span class="badge-pass">✔ PASS</span></td><td>S8</td></tr>
  <tr><td>RG-03</td><td>Impossible d'associer une dépense à une facture en brouillon</td><td><span class="badge-pass">✔ PASS</span></td><td>S11</td></tr>
  <tr><td>RG-06</td><td>L'utilisateur (read-only) ne voit pas les boutons édition/suppression</td><td><span class="badge-pass">✔ PASS</span></td><td>S2</td></tr>
</table>

<h2>Anomalies &amp; corrections</h2>
<table class="kpi-table">
  <tr><th style="width:5%">#</th><th style="width:20%">Sévérité</th><th style="width:35%">Description</th><th>Correction appliquée</th></tr>
  <tr>
    <td>1</td>
    <td><span class="badge-warn">⚠ MINEUR</span></td>
    <td>RG-02 ne se déclenchait pas lors du changement de statut via le formulaire d'édition de facture (invoice/edit.php). Seul le Kanban AJAX (invoice/status.php) créait le projet.</td>
    <td>Ajout de la logique de création auto-projet dans invoice/edit.php, identique à status.php. Les deux chemins créent désormais le projet au passage en "Envoyée".</td>
  </tr>
</table>

<div class="footer">
  Freelancer-invoice-php-app &nbsp;|&nbsp; Rapport généré le {$dateTime} &nbsp;|&nbsp; Tests exécutés par Claude (Gestionnaire) &nbsp;|&nbsp; 17/17 scénarios validés
</div>

</body>
</html>
HTML;

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'rapport-tests-freelancer-invoice-' . date('Ymd') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($dompdf->output()));
echo $dompdf->output();

# Freelancer-invoice

Application de gestion commerciale pour freelances et PME — suivi du pipeline, facturation, exécution de projets, rentabilité et reporting comptable.

## Démarrage rapide

**Prérequis :** XAMPP (PHP 8.1+) installé sur Windows.

```bat
# Installer les dépendances
C:\xampp\php\php.exe C:\composer\composer.phar install

# Démarrer le serveur de développement
C:\xampp\php\php.exe -S localhost:8080 -t public
```

1. Ouvrir [http://localhost:8080/setup.php](http://localhost:8080/setup.php) — créer le premier compte administrateur
2. Se connecter sur [http://localhost:8080/login.php](http://localhost:8080/login.php)
3. L'administrateur crée ensuite les comptes **Gestionnaire** et **Utilisateur** depuis Administration → Utilisateurs

La base de données SQLite est créée automatiquement au premier accès — aucune configuration supplémentaire n'est nécessaire.

## Gestion des accès

L'application dispose d'un système de connexion avec 3 rôles distincts :

| Rôle | Droits |
|------|--------|
| **Administrateur** | Gestion des comptes utilisateurs uniquement. N'a pas accès aux données métier. |
| **Gestionnaire** | Accès complet : création, modification, suppression sur toutes les fonctionnalités. |
| **Utilisateur** | Lecture seule — consultation de toutes les pages sans modification. |

## Fonctionnalités

### Pipeline commercial
Suivi des opportunités du prospect à la signature en 5 étapes : Prospect → Devis envoyé → Négociation → Gagné / Perdu. Conversion en facture en un clic avec pré-remplissage automatique des données client.

### Facturation
Génération de Factures Proforma, Devis et Factures avec export PDF. Numérotation automatique par date (`YYYYMMDD-N`). Duplication de factures existantes. Suivi CA par statut :
- **CA Engagé** — factures Envoyées + Payées
- **CA Encaissé** — factures Payées uniquement

### Suivi de projet
Un projet est créé automatiquement quand une facture passe au statut "Envoyée". Suivi de l'avancement : Non commencé → En cours → Livré → Validé client.

### Dépenses & Rentabilité
Saisie multi-lignes des coûts par projet (Matériaux, Main d'œuvre, Transport, Autre). Calcul automatique de la marge nette par projet et globale.

```
Bénéfice net = CA Engagé − Total Dépenses
```

### Comptabilité
Deux vues de reporting accessibles depuis le menu **Comptabilité** :

| Vue | URL | Description |
|-----|-----|-------------|
| Vue mensuelle | `/accounting/index.php` | Tableau mois par mois + graphique Chart.js, navigation par année |
| Rapport annuel | `/accounting/report.php` | P&L style rapport financier avec comparaison N vs N-1 et variation % |

Les deux vues incluent un bouton **Imprimer / PDF** optimisé (CSS `@media print`, A4).  
Le rapport annuel récupère automatiquement le nom de l'entreprise depuis les Paramètres.

## Structure

```
public/
  login.php          Connexion
  setup.php          Création du premier compte admin
  logout.php         Déconnexion
  invoice/           Facturation (create, edit, list, pdf, duplicate…)
  pipeline/          Pipeline commercial (kanban, convert…)
  project/           Suivi projet
  expense/           Dépenses
  accounting/        Comptabilité (index.php mensuel, report.php annuel)
  admin/users/       Gestion des utilisateurs (admin uniquement)
src/
  Auth/              Auth.php — guards de session et vérification des rôles
  Database/          Repositories (Invoice, Opportunity, Project, Expense, Settings, User)
  DTO/               Objets de transfert de données (readonly)
  Services/          PDF (Dompdf), validation, conversion en lettres
  ValueObjects/      Money
templates/           Layout partagé (sidebar conditionnelle selon le rôle, topbar)
storage/             Base SQLite (auto-générée)
```

## Règles métier clés

- Les **brouillons** n'entrent dans aucun calcul de CA ni de bénéfice.
- Les **dépenses** ne peuvent être rattachées qu'aux factures Envoyées ou Payées.
- La **valeur gagnée** dans le pipeline utilise le montant réel de la facture liée (pas l'estimation initiale) pour refléter le résultat de la négociation.
- Le **PDF** utilise des tableaux HTML (`<table>`) — Dompdf ne supporte pas flexbox ni float.

## Paramètres

Les informations de l'entreprise (nom, adresse, logo, signature, TVA, pied de page) se configurent dans **Paramètres** et pré-remplissent automatiquement chaque nouvelle facture. Cette section est réservée aux **Gestionnaires**.

# Guide utilisateur — Freelancer-invoice

> **Pour qui ?** Ce guide s'adresse aux gestionnaires et utilisateurs de l'application. Les administrateurs trouveront la section [Gestion des comptes](#gestion-des-comptes) qui leur est dédiée.

---

## Connexion

Accédez à l'URL fournie par votre administrateur et entrez votre nom d'utilisateur et mot de passe.

En cas d'oubli de mot de passe, contactez votre administrateur — il peut réinitialiser votre mot de passe depuis la gestion des utilisateurs.

---

## Votre profil

Cliquez sur votre nom d'utilisateur en bas de la barre latérale pour accéder à votre profil.

**Onglet Informations**
- Modifier votre prénom, nom, adresse e-mail et téléphone
- Ajouter ou changer votre photo de profil (JPG, PNG ou WebP, max 2 Mo)
- Le nom d'utilisateur ne peut pas être modifié

**Onglet Mot de passe**
- Changer votre mot de passe (minimum 8 caractères)
- L'ancien mot de passe est requis pour confirmer le changement

---

## Pipeline commercial

Le pipeline suit vos opportunités depuis le premier contact jusqu'à la signature.

### Étapes

| Étape | Signification |
|-------|--------------|
| **Prospect** | Premier contact établi |
| **Devis envoyé** | Proposition transmise au client |
| **Négociation** | Discussions en cours |
| **Gagné** | Affaire conclue |
| **Perdu** | Opportunité abandonnée |

### Créer une opportunité

1. Pipeline → **Nouvelle opportunité**
2. Renseigner le client, le montant estimé et la date de clôture prévue
3. Glisser la carte d'une colonne à l'autre pour faire avancer l'étape

### Convertir en facture

Une fois l'opportunité **Gagnée** : cliquer sur **Convertir en facture**. Les données du client et le montant sont pré-remplis automatiquement. L'opportunité est alors marquée comme gagnée et liée à la facture créée.

---

## Facturation

### Types de documents

| Type | Usage |
|------|-------|
| **Facture Proforma** | Document provisoire avant prestation |
| **Facture** | Document définitif après prestation |
| **Devis** | Proposition commerciale chiffrée |
| **Avoir** | Remboursement ou correction d'une facture |

### Créer une facture

1. Factures → **Nouvelle facture**
2. Sélectionner un client existant (le bouton "Choisir" pré-remplit les champs) ou saisir manuellement
3. Ajouter les lignes de prestation (description, quantité, prix unitaire HT)
4. La TVA et le total sont calculés automatiquement
5. Enregistrer — la facture est créée en statut **Brouillon**

### Cycle de vie d'une facture

```
Brouillon → Envoyée → Payée
                    ↘ Annulée
```

- **Brouillon** : en préparation, non comptabilisé
- **Envoyée** : transmise au client, entre dans le CA Engagé — déclenche la création automatique d'un projet
- **Payée** : encaissée, entre dans le CA Encaissé
- **Annulée** : exclue de tous les calculs

Pour changer le statut : ouvrir la facture → glisser la carte sur le tableau de bord ou utiliser le menu de statut sur la page de la facture.

### Exporter en PDF

Bouton **PDF** en haut à droite de n'importe quelle facture ou devis. Le PDF reprend le logo et les informations de l'entreprise configurées dans Paramètres.

### Dupliquer une facture

Bouton **Dupliquer** sur la liste des factures — crée une copie en brouillon avec un nouveau numéro et les dates du jour.

### Avoirs

Sur une facture envoyée ou payée : bouton **Créer un avoir** → renseigner le motif et le montant. L'avoir est lié à la facture d'origine et apparaît dans sa fiche.

---

## Devis

Le cycle d'un devis est distinct :

```
Brouillon → Envoyé → Accepté → [Convertir en facture]
                   ↘ Refusé
```

Une fois **Accepté**, le bouton **Convertir en facture proforma** crée la facture correspondante avec `origin_id` lié au devis.

---

## Clients

Carnet d'adresses de l'application. Chaque client peut être associé à des factures, des opportunités et des dépenses.

- **Créer** : Clients → Nouveau client
- **Modifier** : cliquer sur le nom du client dans la liste
- **Numéro WhatsApp** : si renseigné, un bouton "Relancer" apparaît sur les factures impayées pour ouvrir directement une conversation WhatsApp pré-remplie

---

## Projets

Un projet est créé **automatiquement** dès qu'une facture passe au statut "Envoyée". Vous pouvez aussi le gérer depuis le menu Projets.

### Statuts de projet

| Statut | Signification |
|--------|--------------|
| Non commencé | Projet créé, travaux pas encore démarrés |
| En cours | Travaux en cours |
| Livré | Livraison effectuée |
| Validé client | Validation reçue du client |

---

## Dépenses

Les dépenses se rattachent à des projets pour calculer la rentabilité.

**Règle importante :** les dépenses ne peuvent être saisies que sur des factures au statut **Envoyée** ou **Payée**.

### Saisir des dépenses

1. Dépenses → Ajouter des dépenses
2. Sélectionner la facture concernée
3. Ajouter autant de lignes que nécessaire (catégorie, description, montant)
4. Valider

### Catégories

Matériaux, Main d'œuvre, Transport, Hébergement, Communication, Logiciels, Autre.

### Rentabilité

```
Bénéfice net = CA Engagé − Total Dépenses
```

Le récapitulatif global est visible sur le tableau de bord et dans la section Dépenses.

---

## Comptabilité

Accessible aux gestionnaires et utilisateurs (lecture seule pour les utilisateurs).

### Vue mensuelle

Comptabilité → tableau mois par mois pour l'année en cours. Navigation par année. Graphique visuel du CA et des dépenses. Bouton **Exporter CSV** pour l'import dans Excel (séparateur `;`, encodage UTF-8).

### Rapport annuel

Comptabilité → Rapport annuel : comparaison N vs N-1 avec variations en pourcentage. Format P&L (compte de résultat simplifié). Bouton **Imprimer / PDF** optimisé A4.

---

## Prestations récurrentes

Pour les clients avec des missions récurrentes (mensuel, trimestriel, annuel) : Récurrentes → Nouvelle récurrence. L'application génère automatiquement une facture brouillon à chaque échéance lors du chargement du tableau de bord.

---

## Catalogue de prestations

Bibliothèque de services pré-définis pour accélérer la saisie des factures. Récurrentes, Développement, Design, Conseil, etc. À configurer dans Configuration → Catalogue.

---

## Paramètres

Réservé aux gestionnaires.

- **Entreprise** : nom, adresse, email, téléphone, SIRET, TVA, pied de page
- **Logo** : importé en PNG/JPG, apparaît sur toutes les factures PDF
- **Numérotation** : préfixe personnalisé (ex. `ISSU-`) ajouté devant chaque numéro de facture
- **Licence** : voir l'édition active, les quotas restants (plan gratuit) et changer de clé
- **Sauvegarde** : exporter ou restaurer la base de données

---

## Gestion des comptes

> Section réservée aux **Administrateurs**.

### Créer un utilisateur

Administration → Utilisateurs → **Nouvel utilisateur**

- **Prénom / Nom** : optionnels mais recommandés pour identifier les entrées dans le journal
- **Nom d'utilisateur** : identifiant de connexion, non modifiable après création
- **Rôle** : Gestionnaire (accès complet), Utilisateur (lecture seule), Administrateur (gestion comptes)

### Supprimer un utilisateur

Bouton corbeille sur la ligne — impossible de supprimer le compte Super Admin ni son propre compte.

### Journal d'activité

Administration → Journal d'activité : toutes les actions de tous les utilisateurs, filtrables par période (aujourd'hui, semaine, mois, période personnalisée), par utilisateur et par catégorie. Bouton **Imprimer** pour générer un rapport PDF A4 paysage avec statistiques de synthèse.

---

## Recherche globale

Barre de recherche en haut de toutes les pages — recherche simultanément dans les factures, devis, clients et dépenses. Minimum 2 caractères.

---

## Limites du plan gratuit

| Fonctionnalité | Limite |
|----------------|--------|
| Factures | 10 |
| Duplications | 5 |
| Exports PDF | 15 |
| Exports Excel | 15 |
| Opportunités pipeline | 5 |
| Dépenses | 5 |
| Comptabilité | Bloqué |
| Multi-utilisateurs | Bloqué |

Pour lever ces limites, contactez ISSU DEV pour obtenir une clé de licence Pro ou Entreprise.

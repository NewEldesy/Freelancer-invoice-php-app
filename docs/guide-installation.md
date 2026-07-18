# Guide d'installation — Freelancer-invoice

> **Pour qui ?** Ce guide s'adresse au technicien qui installe et configure l'application chez un client final. Prévoir 15 à 30 minutes pour une installation complète.

---

## Prérequis

| Composant | Version minimale | Notes |
|-----------|-----------------|-------|
| PHP | 8.1+ | Extensions requises : `pdo_sqlite`, `fileinfo`, `mbstring` |
| Composer | toute version | Gestionnaire de dépendances PHP |
| Système | Windows 10/11 | Testé avec XAMPP et Laragon |

**Recommandation Windows :** utiliser **Laragon** (tout-en-un PHP + serveur) — installation en 2 minutes, aucune configuration PATH.

- Laragon : https://laragon.org/download/ → version Full
- XAMPP : https://www.apachefriends.org/

---

## Étape 1 — Copier les fichiers

Placer le dossier de l'application dans le répertoire web du client :

- **Laragon** : `C:\laragon\www\freelancer-invoice\`
- **XAMPP** : `C:\xampp\htdocs\freelancer-invoice\`
- **Autre** : n'importe quel dossier accessible par PHP

---

## Étape 2 — Installer les dépendances

Ouvrir un terminal dans le dossier de l'application et exécuter :

```bat
# Si PHP et Composer sont dans le PATH (Laragon, installation standard)
composer install --no-dev --optimize-autoloader

# Si XAMPP avec chemins non standards
C:\xampp\php\php.exe C:\composer\composer.phar install --no-dev --optimize-autoloader
```

> **Alternative Windows :** double-cliquer sur `install.bat` — le script détecte PHP et Composer, installe les dépendances et démarre le serveur.

---

## Étape 3 — Vérifier la clé de licence

Le fichier `config/license.secret` doit être présent. C'est la clé privée ISSU DEV qui valide les licences clients.

```
config/
  license.secret        ← doit exister (fourni par ISSU DEV, ne pas partager)
  license.secret.example
```

Si le fichier est absent ou contient la valeur d'exemple, l'application affichera une erreur au démarrage. Contacter ISSU DEV pour obtenir le fichier.

---

## Étape 4 — Démarrer l'application

### Option A — Serveur de développement PHP intégré (le plus simple)

```bat
# Double-cliquer sur start.bat
# Ou en ligne de commande :
php -S localhost:8080 -t public
```

L'application est accessible sur **http://localhost:8080**

### Option B — Laragon (recommandé pour un usage permanent)

1. Placer le dossier dans `C:\laragon\www\`
2. Laragon détecte automatiquement le dossier et crée un virtual host `freelancer-invoice.test`
3. Démarrer Laragon → Start All

### Option C — XAMPP avec Apache

Configurer un VirtualHost Apache pointant vers le dossier `public/` :

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/freelancer-invoice/public"
    ServerName freelancer-invoice.local
    <Directory "C:/xampp/htdocs/freelancer-invoice/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Étape 5 — Première configuration

1. Ouvrir **http://localhost:8080/setup.php** (ou l'URL correspondante)

   La page setup crée automatiquement :
   - Le compte **Super Admin** (ISSU DEV — ne pas communiquer au client)
   - Le compte **Admin** pour le client
   - 15 clés de licence pré-générées (5 Pro + 10 Entreprise)

   > `setup.php` se désactive automatiquement dès qu'un utilisateur existe — impossible de l'exécuter deux fois.

2. Se connecter avec le compte **Admin** créé
3. Administration → Utilisateurs → créer les comptes du client (gestionnaire, utilisateurs)

---

## Étape 6 — Paramètres entreprise

Se connecter avec un compte **Gestionnaire** :

1. Configuration → Paramètres
2. Renseigner : nom de l'entreprise, adresse, email, téléphone, SIRET, taux de TVA
3. Uploader le logo (PNG recommandé, fond transparent)
4. Enregistrer

Ces informations apparaîtront sur toutes les factures PDF.

---

## Étape 7 — Activation de la licence

Sans licence, l'application démarre en plan **Gratuit** (fonctionnalités limitées).

Pour activer une licence Pro ou Entreprise :

1. Récupérer l'identifiant machine affiché sur la page d'activation (`/activate.php`)
2. Générer une clé liée à cet identifiant depuis le panneau Super Admin (`/superadmin/keys.php`)
3. Le client saisit la clé sur `/activate.php` → Activer

> Les clés permanentes ne sont pas liées à une date d'expiration. Les clés à durée limitée (3 mois, 6 mois, 1 an, 2 ans) calculent l'expiration au moment de l'activation.

---

## Permissions fichiers

S'assurer que PHP peut écrire dans ces dossiers :

| Dossier | Usage |
|---------|-------|
| `storage/` | Base de données SQLite |
| `public/uploads/` | Logos des factures |
| `public/uploads/avatars/` | Photos de profil des utilisateurs |

```bat
# Windows — via l'explorateur : clic droit → Propriétés → Sécurité
# Donner le contrôle total à l'utilisateur qui exécute PHP (souvent SYSTEM ou l'utilisateur courant)
```

---

## Sauvegarde

La base de données est un unique fichier : `storage/invoices.sqlite`

**Sauvegarde manuelle :** copier ce fichier vers un emplacement sûr (clé USB, cloud, NAS).

**Sauvegarde depuis l'interface :** Paramètres → Sauvegarde → Télécharger. Le fichier `.sqlite` peut être restauré depuis la même page.

**Recommandation :** planifier une copie quotidienne automatique du fichier `storage/invoices.sqlite` (tâche planifiée Windows, script robocopy, etc.).

---

## Vérifications post-installation

- [ ] `setup.php` a été exécuté et les comptes sont créés
- [ ] Le compte Admin se connecte correctement
- [ ] Les paramètres entreprise sont renseignés (nom, TVA, logo)
- [ ] La licence est activée (ou le client accepte le plan gratuit)
- [ ] Un compte Gestionnaire est créé pour l'utilisateur principal
- [ ] Une première facture test a été créée et exportée en PDF
- [ ] La sauvegarde automatique est configurée

---

## Résolution de problèmes courants

### Page blanche ou erreur 500

```bat
# Vérifier les erreurs PHP
php -l public/index.php

# Vérifier que les extensions requises sont chargées
php -m | findstr "pdo_sqlite fileinfo mbstring"
```

### "Clé de licence invalide"

Vérifier que la clé a été générée pour l'identifiant machine exact affiché sur la page d'activation. Une clé générée sans machine ID fonctionne sur n'importe quel poste.

### Le logo ne s'affiche pas sur les PDF

- Vérifier que PHP peut lire `public/uploads/`
- Le logo doit être PNG ou JPG (pas de SVG — non supporté par Dompdf)
- Taille recommandée : max 500×200 px

### Session expirée après restauration de base

Comportement normal — la restauration change les identifiants en base. Les utilisateurs doivent se reconnecter.

### `setup.php` affiche "déjà configuré"

La base de données contient déjà des utilisateurs. Si c'est une réinstallation propre, supprimer `storage/invoices.sqlite` et relancer `setup.php`.

> ⚠️ Supprimer la base de données efface toutes les données. S'assurer d'avoir une sauvegarde avant.

---

## Contact ISSU DEV

Pour toute question sur l'installation, les licences ou les mises à jour, contacter ISSU DEV directement.

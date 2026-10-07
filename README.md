# Campus Life – Groupe RAMMSS

Plateforme où les étudiants proposent des idées pour améliorer le campus et votent dessus.
Une requête qui atteint **100 votes « Pour »** avec **au moins 60 % d'avis favorables** est transmise à l'administration.

Technos : PHP 8 (sans framework) + MySQL.

---

## Installation

### 1. Installer MAMP

Télécharger et installer **MAMP** (gratuit) : https://www.mamp.info
Lancer MAMP puis cliquer sur **Start** (Apache et MySQL doivent passer au vert).

> XAMPP marche aussi, voir l'étape 3 pour adapter la configuration.

### 2. Importer la base de données

1. Ouvrir **phpMyAdmin** depuis MAMP (bouton *WebStart* → *Tools* → *phpMyAdmin*, ou http://localhost/phpMyAdmin).
2. Onglet **Importer** → choisir le fichier `database/rammss.sql` → **Exécuter**.

Le script crée tout seul la base `rammss`, les tables et les données de démo.
Si le projet a déjà été importé, réimporter le fichier remet la base à zéro.

### 3. Vérifier la connexion MySQL

Ouvrir `config/config.php` et vérifier les identifiants :

| Logiciel          | `DB_PORT` | `DB_USER` | `DB_PASS` |
|-------------------|-----------|-----------|-----------|
| MAMP (Windows/Mac)| `8889`    | `root`    | `root`    |
| XAMPP             | `3306`    | `root`    | `''` (vide) |

Le port MySQL exact est affiché dans MAMP → *Preferences* → *Ports*.

### 4. Lancer le site

**Option A – dossier `htdocs` (le plus simple)**

Copier le dossier `RAMMSS-PHP` dans :
- Windows : `C:\MAMP\htdocs\`
- Mac : `/Applications/MAMP/htdocs/`
- XAMPP : `C:\xampp\htdocs\`

Puis ouvrir http://localhost/RAMMSS-PHP/ (sur Mac avec MAMP : http://localhost:8888/RAMMSS-PHP/).

**Option B – serveur PHP intégré (sans copier le dossier)**

Depuis un terminal, en remplaçant le chemin du projet :

```
C:\MAMP\bin\php\php8.3.1\php.exe -c C:\MAMP\conf\php8.3.1\php.ini -S localhost:8000 -t "CHEMIN\VERS\RAMMSS-PHP"
```

Puis ouvrir http://localhost:8000. Le `-c ...php.ini` est indispensable, sinon l'extension `mbstring` n'est pas chargée et les pages plantent.
Le numéro de version (`php8.3.1`) peut être différent : regarder dans `C:\MAMP\bin\php\`.

---

## Comptes de démo

| Rôle      | Email                        | Mot de passe        |
|-----------|------------------------------|---------------------|
| Étudiant  | `alban.elie@coda.school`     | `campus2026!secret` |
| Étudiant  | `lea.martin@coda.school`     | `campus2026!secret` |
| Étudiant  | `thomas.bernard@coda.school` | `campus2026!secret` |
| Admin     | `direction@coda.school`      | `admin2026!`        |

- **Nouvel étudiant** : inscription libre sur `register.php`.
- **Nouvel admin** : `admin-register.php`, avec un email `@coda.school` ou `@campus.fr` et la clé secrète `RAMMSS-ADMIN-2026` (modifiable dans `config/config.php`).

---

## Problèmes fréquents

| Message                                         | Solution |
|-------------------------------------------------|----------|
| « Connexion à la base de données impossible »   | MAMP n'est pas démarré, la base n'est pas importée, ou le port/mot de passe de `config/config.php` est faux (étape 3). |
| `Call to undefined function mb_strtoupper()`    | Serveur PHP lancé sans `-c ...php.ini` (étape 4, option B). |
| Page « Not Found »                              | Vérifier l'URL (pas de point ou d'espace en trop à la fin). |

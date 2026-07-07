# Cahier des charges — phpPgAdmin

## 1. Vision

Outil web d'administration pour **PostgreSQL**, dans l'esprit de phpMyAdmin (pour MySQL).
Permet d'explorer un serveur PostgreSQL depuis un navigateur : parcourir les bases,
schémas, tables et vues, consulter leur structure et leurs données, et exécuter des
requêtes en lecture.

## 2. Contraintes techniques

- **Langage** : PHP 8.4 « brut », orienté classes, **sans framework**.
- **Autoloading** : Composer, PSR-4 (`App\` → `app/src/`).
- **Serveur applicatif** : FrankenPHP (image `dunglas/frankenphp`).
- **Base de données** : PostgreSQL, via l'extension PHP `pdo_pgsql`.
- **Configuration** : 100 % par **variables d'environnement** (aucun secret en dur).
- **Conteneurisation** : Docker + docker-compose (service `app` + service `db`).

### Variables d'environnement de connexion

| Variable        | Rôle                                   | Défaut (compose) |
|-----------------|----------------------------------------|------------------|
| `PG_HOST`       | Hôte du serveur PostgreSQL             | `phppgadmin_db`  |
| `PG_PORT`       | Port                                   | `5432`           |
| `PG_USER`       | Utilisateur de connexion               | `app`            |
| `PG_PASSWORD`   | Mot de passe                           | `app`            |
| `PG_DEFAULT_DB` | Base utilisée pour la connexion initiale | `app`          |

Pour pointer vers une autre base, l'utilisateur modifie ces variables dans
`docker-compose.yml` (ou son `.env`), exactement comme `PMA_HOST` chez phpMyAdmin.

## 3. Périmètre v1 (MVP) — LECTURE SEULE

- **Lister les bases** du serveur.
- **Lister les schémas** d'une base (hors schémas système).
- **Lister tables et vues** d'un schéma.
- **Structure d'une table** : colonnes (type, nullable, défaut), clé primaire, index.
- **Données d'une table** : affichage paginé des lignes.
- **Console SQL** : exécution de requêtes **en lecture seule** (`SELECT` / `WITH` / `EXPLAIN`).

### Sécurité (lecture seule garantie)

Défense en profondeur dans `QueryController` :
1. Filtre : seules les requêtes commençant par `SELECT`, `WITH` ou `EXPLAIN` sont acceptées.
2. Exécution dans une transaction `SET TRANSACTION READ ONLY` puis `ROLLBACK` :
   PostgreSQL rejette toute écriture, même si le filtre était contourné.
3. `LIMIT` appliqué à l'affichage des résultats.
4. Échappement HTML systématique (`htmlspecialchars`) dans toutes les vues.
5. Identifiants SQL (schéma/table) toujours quotés via une fonction dédiée.

## 4. Architecture

MVC léger, sans framework :

- `app/public/index.php` — front controller (autoload → Router → dispatch).
- `app/src/Core/` — briques transverses : `Config`, `Database` (PDO), `Router`,
  `Request`, `Response`, `View`.
- `app/src/Service/PostgresInspector.php` — toute l'introspection PostgreSQL.
- `app/src/Controller/` — un contrôleur par section (Server, Schema, Table, Query).
- `app/templates/` — vues PHP + layout commun (sidebar de navigation).

### Routes

| Méthode   | Chemin                                         | Action                          |
|-----------|------------------------------------------------|---------------------------------|
| GET       | `/`                                            | Liste des bases                 |
| GET       | `/db/{db}`                                     | Schémas + tables/vues           |
| GET       | `/db/{db}/table/{schema}/{table}`              | Structure d'une table           |
| GET       | `/db/{db}/table/{schema}/{table}/data`         | Données paginées (`?page=`)     |
| GET, POST | `/db/{db}/query`                               | Console SQL (lecture seule)     |

### v2 — Écriture des données (fait)

- **CRUD lignes via formulaires sûrs** : ajout, édition, suppression (avec confirmation).
  CSRF, requêtes préparées, ciblage par clé primaire (refusé sans PK ou sur une vue), PRG + flash.
- **Console SQL en écriture** : case « Mode écriture » (opt-in, OFF par défaut) + CSRF ;
  OFF = lecture seule (garde + transaction `READ ONLY`) inchangée.
- Routes ajoutées : `…/row/new`, `…/row` (POST), `…/row/edit`, `…/row/update` (POST), `…/row/delete` (POST),
  `…/query` (POST avec `write=1`).

### v2.2 — Édition de la structure / DDL (fait)

- **Colonnes** : ajouter, éditer (renommer, type, NOT NULL, valeur par défaut), supprimer.
- **Table** : renommer, supprimer, **créer** (formulaire multi-colonnes avec PK).
- Formulaires sûrs : CSRF, exécution en **transaction** (rollback si échec), confirmations sur les suppressions,
  uniquement sur les **tables de base** (jamais les vues). Type saisi en champ libre + autocomplétion.
- Routes : `…/column/new`, `…/column` (POST), `…/column/edit`, `…/column/update` (POST), `…/column/drop` (POST),
  `…/rename` (POST), `…/drop` (POST), `/db/{db}/create-table` (GET+POST).

### v2.3 — Index, clés étrangères, onglet Action (fait)

- **Index** : créer (une ou plusieurs colonnes, UNIQUE, nom optionnel), supprimer (sauf index de contrainte PK/unique).
- **Clés étrangères** : ajouter (colonne locale → table/colonne référencée via menus, `ON DELETE`), supprimer.
- **Onglet « Action »** (tables de base) : renommer / supprimer la table, regroupés hors de l'onglet Structure.
- Routes : `…/index/new`, `…/index` (POST), `…/index/drop` (POST), `…/fk/new`, `…/fk` (POST), `…/fk/drop` (POST), `…/action`.

## 5. Hors périmètre (évolutions futures)

- Contraintes UNIQUE / CHECK via formulaire dédié ; clés étrangères multi-colonnes ; `USING` pour conversions de type.
- Gestion des rôles et utilisateurs PostgreSQL.
- Connexion multi-serveurs configurable depuis l'interface.
- Authentification de l'outil lui-même (login applicatif).
- Export SQL (au-delà du CSV déjà disponible).

## 6. Vérification (recette)

1. `docker compose up --build -d` : build de l'image FrankenPHP (avec `pdo_pgsql`) et démarrage de Postgres avec `doc/seed.sql`.
2. `http://localhost:8001` affiche la liste des bases.
3. Navigation base → schéma `public` → table `customers` → onglets Structure & Données.
4. Console SQL : un `SELECT` renvoie des lignes ; un `UPDATE`/`DROP` est refusé.
5. `docker compose logs app` ne montre aucune erreur PHP.

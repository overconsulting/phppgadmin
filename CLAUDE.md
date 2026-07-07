# CLAUDE.md — phpPgAdmin

Mémoire de projet pour Claude Code. Lue à chaque session. Garde-la à jour.

## Le projet en une phrase

Clone de phpMyAdmin pour **PostgreSQL**, en **PHP 8.4 brut orienté classes (sans framework)**,
servi par **FrankenPHP** dans Docker, connexion configurée par **variables d'environnement**.

Le cahier des charges complet est dans [doc/SPEC.md](doc/SPEC.md).

## Structure

- `app/` — toute l'application PHP (code, templates, public, composer).
- `app/src/Core/` — briques transverses (Config, Database, Router, Request, Response, View).
- `app/src/Service/` — logique métier (PostgresInspector : introspection PG).
- `app/src/Controller/` — un contrôleur par section.
- `app/templates/` — vues PHP + `layout.php`.
- `app/public/` — front controller `index.php` + assets statiques.
- `config/` — `Caddyfile` (FrankenPHP) et `php.ini`.
- `doc/` — `SPEC.md` (cahier des charges) et `seed.sql` (données de démo).
- Racine — `Dockerfile`, `docker-compose.yml`.

## Conventions de code

- Namespace racine `App\`, PSR-4 → `app/src/`. `declare(strict_types=1);` en tête de chaque fichier.
- Une classe par fichier, nommage PascalCase ; méthodes camelCase.
- Accès BDD **uniquement** via `App\Core\Database` (PDO). Toujours des **requêtes préparées**
  pour les valeurs. Les identifiants (schéma/table/colonne) passent par `Database::quoteIdentifier()`.
- Les vues échappent toujours la sortie avec `htmlspecialchars` (helper `e()` disponible).
- **Écriture via chemins contrôlés uniquement** (depuis v2) :
  - **Formulaires de ligne** (`RowController`) : INSERT/UPDATE/DELETE en **requêtes préparées**
    (`App\Service\PostgresWriter`), **CSRF** obligatoire (`App\Core\Csrf`), ciblage par **clé primaire**
    (refusé sans PK ou sur une vue), PRG + flash (`App\Core\Session`).
  - **Console SQL** : lecture seule par défaut (garde `App\Service\SqlReadGuard` + transaction `READ ONLY`) ;
    l'écriture nécessite la case **« Mode écriture »** + CSRF (exécution read/write committée).
  - Identifiants toujours via `Database::quoteIdentifier()` ; valeurs toujours en paramètres liés.

## Tests

- PHPUnit dans `app/` : `tests/Unit/` (logique pure) et `tests/Integration/` (vrai PostgreSQL, sauté si pas de DB).
- Lancer : `docker compose exec app vendor/bin/phpunit --testdox`.
- Couvre en priorité la garantie lecture seule (`SqlReadGuard` + transaction `READ ONLY`),
  la génération de requête/filtres (`PostgresInspector::selectSql`), `quoteIdentifier`, l'export CSV.
- **À maintenir vert** : tout nouveau chemin d'écriture (v2) ne doit pas casser ces tests.

## Commandes

```bash
# Démarrer (build + run en arrière-plan)
docker compose up --build -d

# Logs de l'app
docker compose logs -f app

# Shell dans le conteneur app
docker compose exec app sh

# Installer les dépendances Composer (si ajout)
docker compose exec app composer install

# Recharger le seed (réinitialise la base — supprime le volume)
docker compose down -v && docker compose up --build -d

# Arrêter
docker compose down

# Lancer les tests (PHPUnit, dans le conteneur)
docker compose exec app vendor/bin/phpunit --testdox
```

App accessible sur **http://localhost:8001**. Postgres exposé sur `localhost:5432`.

## Configuration de la connexion

Variables d'env dans `docker-compose.yml` (service `app`) :
`PG_HOST`, `PG_PORT`, `PG_USER`, `PG_PASSWORD`, `PG_DEFAULT_DB`.
Pour viser une autre base, on modifie ces valeurs — rien n'est codé en dur.

## Authentification (porte de l'interface)

`App\Core\Auth::guard()` s'exécute dans `public/index.php` **avant** le routeur : tant qu'on n'est
pas connecté, aucune requête n'atteint la base. Identifiants via env, distincts de la base :
- `AUTH_ENABLED` (défaut `true` ; `false`/`0`/`no`/`off` = porte ouverte, usage local — mis à `false` dans le compose de dev)
- `APP_USER` (défaut `admin`), `APP_PASSWORD` (**secret, jamais dans l'image**)
- Fermeture de sécurité : `AUTH_ENABLED=true` + `APP_PASSWORD` vide ⇒ accès refusé (500) avec message.
- Routes gérées par la porte (hors routeur) : `GET/POST /login`, `GET /logout`. Session via `App\Core\Session`,
  CSRF via `App\Core\Csrf`, `hash_equals` + `session_regenerate_id` (anti-fixation). Template `templates/auth/login.php`.

## Image publiable (Docker Hub)

`Dockerfile` multi-stage : `base` → `dev` (code monté en volume) → **`prod`** (code `COPY` dans l'image +
`composer install --no-dev`, `APP_ENV=prod`, `AUTH_ENABLED=true`). Publier :
`docker build --target prod -t overconsulting/phppgadmin:x.y.z .` puis `docker tag`/`docker push`.
Les secrets (`PG_PASSWORD`, `APP_PASSWORD`) sont fournis au `docker run -e`, jamais bakés.

## État / périmètre

- **v1 (fait)** : lecture seule — bases, schémas, tables/vues, structure, données paginées, console SQL SELECT.
- **v1.1 (fait)** : clés étrangères dans la structure ; tri + filtre par colonne dans les données
  (opérateurs : égal / contient / commence par / finit par) ; cadre « requête exécutée » au-dessus
  des données avec lien « Modifier » qui préremplit la console (`/query?sql=`) ;
  console SQL avec temps d'exécution et bouton EXPLAIN ; export CSV (données de table et résultat de requête).
  Sidebar : la base courante se déplie (`<details>`) en arborescence schéma → tables (zone scrollable),
  chaque table est un lien direct, la table courante est surlignée. `withNav()` charge `navTables`.
  Utilitaire `App\Core\Csv`, `Response::attachment()` pour les téléchargements.
  Note PHP 8.4 : `fputcsv()` exige le 5e paramètre `$escape` (on passe `''`).
- **v2 (fait)** : écriture des lignes — ajout/édition/suppression via formulaires sûrs
  (CSRF, requêtes préparées, ciblage PK, confirmation, flash) ; console SQL avec **mode écriture** opt-in.
  Composants : `Controller\RowController`, `Service\PostgresWriter`, `Core\Csrf`, `Core\Session`,
  `Database::execute()`, `Response::redirect()`, template `table/row_form.php`.
- **v2.2 (fait)** : édition de structure (DDL) — colonnes (ajouter/éditer/supprimer : nom, type, NOT NULL, défaut),
  table (renommer/supprimer), **création de table**. Formulaires sûrs (CSRF, transaction, confirmations),
  uniquement sur tables de base. Type saisi en champ libre avec autocomplétion (`<datalist>`).
  Composants : `Service\PostgresDdl`, `Controller\StructureController`, templates `table/column_form.php`,
  `table/create_table.php`, `table/_types_datalist.php`. Requête DDL exécutée affichée (cadre dans `layout.php`).
- **v2.3 (fait)** : index (créer multi-colonnes/UNIQUE ; supprimer — y compris les index de contrainte PK/UNIQUE,
  retirés via `DROP CONSTRAINT` ; « modifier » = supprimer puis recréer) et clés étrangères
  (ajouter avec table référencée en menu + ON DELETE, supprimer) dans l'onglet Structure ;
  nouvel onglet **« Action »** (renommer/supprimer la table). Builders `PostgresDdl::createIndex/dropIndex/
  addForeignKey/dropConstraint` ; `PostgresInspector::indexes()` (+`is_constraint`) et `foreignKeys()` (+`name`) ;
  templates `index_form.php`, `fk_form.php`, `action.php`, `_tabs.php` (onglets partagés).
- **v2.4 (fait)** : **auth applicative** — porte de login `App\Core\Auth` (voir §« Authentification »),
  et **stage Docker `prod`** publiable sur Docker Hub (voir §« Image publiable »).
- **v2.5 (fait)** : **gestion des bases** au niveau serveur. Création depuis l'accueil (bouton + formulaire
  dans `templates/server/databases.php` → `POST /create-database`). Page **« Opérations »** par base
  (lien navbar → `GET /db/{db}/operations`, template `server/operations.php`) : **renommer** (`POST /db/{db}/rename`)
  et **supprimer** (`POST /db/{db}/drop`, protégé par `confirm()`). Builders `PostgresDdl::createDatabase/renameDatabase/dropDatabase`.
  Points PG : CREATE/ALTER/DROP DATABASE **hors transaction** ; RENAME/DROP se font connecté à une **base de maintenance**
  (`ServerController::maintenanceDb()` : default_db, sinon `postgres`, sinon `template1` — jamais la cible). CSRF + flash + PRG.
  Page schéma (`schema/tables.php`) : **suppression multiple de tables** (cases à cocher `row-check` + tout-sélectionner,
  `POST /db/{db}/tables/drop` → `StructureController::dropTables` → `PostgresDdl::dropTables()` = un seul `DROP TABLE a, b, …`,
  en transaction ; cases seulement sur les tables de base, pas les vues) ; et un lien **« Opérations »** par ligne
  (tables de base) vers l'onglet d'opérations de la table.
  Vocabulaire **« Opérations »** partout : l'onglet table historiquement « Action » (renommer/supprimer la table) est
  **relibellé « Opérations »** dans `table/_tabs.php` (route inchangée : `/db/{db}/table/{schema}/{table}/action`).
  L'accès aux opérations de la **base** (renommer/supprimer la base) reste dans la **navbar** (« Opérations »).
- **Plus tard** : contraintes UNIQUE/CHECK via formulaire, FK multi-colonnes, `USING` pour conversions de type,
  gestion des rôles, multi-serveurs. Voir §5 de [doc/SPEC.md](doc/SPEC.md).

# phpPgAdmin

Une interface web légère pour explorer et administrer une base **PostgreSQL** :
bases, schémas, tables, données paginées, console SQL, et édition (lignes, structure, index, clés étrangères).

Écrit en **PHP 8.4 brut** (orienté classes, sans framework), servi par **[FrankenPHP](https://frankenphp.dev/)**.
Toute la connexion se configure par **variables d'environnement** — l'image est réutilisable telle quelle.

![License: MIT](https://img.shields.io/badge/license-MIT-green)

---

## Démarrage rapide

```bash
docker run -p 8080:8080 \
  -e PG_HOST=host.docker.internal \
  -e PG_DEFAULT_DB=ma_base \
  -e PG_USER=postgres \
  -e PG_PASSWORD=secret \
  -e APP_PASSWORD=change-moi \
  overconsulting/phppgadmin:latest
```

Puis ouvrir **http://localhost:8080**, se connecter avec `APP_USER` / `APP_PASSWORD`.

> En local, on peut désactiver la porte de login avec `-e AUTH_ENABLED=false`.

### Avec Docker Compose

```yaml
services:
  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ma_base
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app

  phppgadmin:
    image: overconsulting/phppgadmin:latest
    ports:
      - "8080:8080"
    environment:
      PG_HOST: db              # nom du service = adresse réseau
      PG_DEFAULT_DB: ma_base
      PG_USER: app
      PG_PASSWORD: app
      APP_PASSWORD: change-moi
    depends_on:
      - db
```

---

## Variables d'environnement

| Variable        | Rôle                                    | Défaut      |
|-----------------|-----------------------------------------|-------------|
| `PG_HOST`       | Serveur PostgreSQL                      | `127.0.0.1` |
| `PG_PORT`       | Port PostgreSQL                         | `5432`      |
| `PG_USER`       | Utilisateur PostgreSQL                  | `postgres`  |
| `PG_PASSWORD`   | Mot de passe PostgreSQL                 | *(vide)*    |
| `PG_DEFAULT_DB` | Base ouverte par défaut                 | `postgres`  |
| `APP_USER`      | Login de l'interface                    | `admin`     |
| `APP_PASSWORD`  | Mot de passe de l'interface             | *(vide)*    |
| `AUTH_ENABLED`  | Porte de login (`false` pour désactiver)| `true`      |

Les secrets (`PG_PASSWORD`, `APP_PASSWORD`) sont fournis **au lancement** — ils ne sont jamais inclus dans l'image.

---

## Authentification

phpPgAdmin **écrit** en base (INSERT/UPDATE/DELETE, DDL). Une instance exposée sans protection permettrait
à quiconque de modifier ou détruire les données. Une **porte de login** protège donc l'interface :

- Activée par défaut (`AUTH_ENABLED=true`). Fournir `APP_USER` / `APP_PASSWORD`.
- Si `AUTH_ENABLED=true` mais `APP_PASSWORD` est vide, l'accès est **refusé** (sécurité par défaut).
- Pour un usage local sans friction : `AUTH_ENABLED=false`.

---

## Construire l'image depuis les sources

Le `Dockerfile` est multi-stage. Le stage **`prod`** copie le code dans l'image (autonome, publiable) :

```bash
docker build --target prod -t overconsulting/phppgadmin:1.0.0 .
```

## Développement

```bash
docker compose up --build -d      # app sur http://localhost:8001
docker compose exec app composer install
docker compose exec app vendor/bin/phpunit --testdox
```

---

## Licence

[MIT](LICENSE) © 2026 Overconsulting

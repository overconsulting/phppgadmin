# ========= Base commune =========
FROM dunglas/frankenphp:1-php8.4-alpine AS base
WORKDIR /app

# Paquets système + extensions PHP nécessaires à PostgreSQL
RUN apk add --no-cache \
    icu-dev libpq-dev git bash \
 && docker-php-ext-configure intl \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pgsql opcache

# Composer (depuis l'image officielle)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Configuration PHP
COPY ./config/php.ini /usr/local/etc/php/conf.d/custom.ini

# Caddyfile pour FrankenPHP
COPY ./config/Caddyfile /etc/caddy/Caddyfile

# ========= Image Dev =========
FROM base AS dev
ENV APP_ENV=dev
# Le code est monté en volume (voir docker-compose.yml), rien à copier.
EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ========= Image Prod (publiable sur Docker Hub) =========
FROM base AS prod

# Valeurs par défaut documentées, surchargeables au `docker run -e ...`.
# Les secrets (PG_PASSWORD, APP_PASSWORD) ne sont JAMAIS dans l'image : fournis au lancement.
ENV APP_ENV=prod \
    AUTH_ENABLED=true \
    APP_USER=admin

# En prod, le code est COPIÉ dans l'image (pas de volume) : elle est autonome.
COPY app/ /app/

# Dépendances de production uniquement + autoloader optimisé.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

EXPOSE 8080
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

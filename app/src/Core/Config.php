<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Accès centralisé à la configuration, lue depuis les variables d'environnement.
 */
final class Config
{
    /**
     * Récupère une variable d'environnement, avec valeur de repli optionnelle.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Paramètres de connexion PostgreSQL issus des variables d'environnement.
     *
     * @return array{host:string, port:string, user:string, password:string, default_db:string}
     */
    public static function postgres(): array
    {
        return [
            'host'       => self::get('PG_HOST', '127.0.0.1'),
            'port'       => self::get('PG_PORT', '5432'),
            'user'       => self::get('PG_USER', 'postgres'),
            'password'   => self::get('PG_PASSWORD', ''),
            'default_db' => self::get('PG_DEFAULT_DB', 'postgres'),
        ];
    }

    /**
     * Paramètres de la porte d'authentification de l'interface.
     *
     * `enabled` est vrai par défaut : une instance exposée est protégée sauf choix explicite
     * de la désactiver (AUTH_ENABLED=false) pour un usage local sans friction.
     *
     * @return array{enabled:bool, user:string, password:string}
     */
    public static function auth(): array
    {
        $flag = strtolower((string) self::get('AUTH_ENABLED', 'true'));

        return [
            'enabled'  => !in_array($flag, ['false', '0', 'no', 'off', ''], true),
            'user'     => (string) self::get('APP_USER', 'admin'),
            'password' => (string) self::get('APP_PASSWORD', ''),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protection CSRF : jeton stocké en session, comparé en temps constant.
 *
 * Opère directement sur $_SESSION (la session doit être démarrée par le front controller).
 */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function isValid(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && !empty($_SESSION[self::KEY])
            && hash_equals((string) $_SESSION[self::KEY], $token);
    }
}

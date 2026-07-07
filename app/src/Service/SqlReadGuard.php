<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Garde « lecture seule » : décide si une requête SQL est autorisée dans la console.
 *
 * Première ligne de défense (la seconde étant la transaction `READ ONLY` côté PostgreSQL) :
 * seules les requêtes commençant par SELECT, WITH ou EXPLAIN sont acceptées, et les
 * requêtes multiples (présence d'un `;` interne) sont refusées.
 */
final class SqlReadGuard
{
    /** Préfixes autorisés (premier mot-clé de la requête). */
    public const PREFIXES = ['SELECT', 'WITH', 'EXPLAIN'];

    public static function isReadOnly(string $sql): bool
    {
        $normalized = ltrim($sql);

        // Refus des requêtes multiples : un `;` ailleurs qu'en toute fin.
        if (str_contains(rtrim($normalized, "; \t\n\r"), ';')) {
            return false;
        }

        $firstWord = strtoupper(strtok($normalized, " \t\n\r(") ?: '');

        return in_array($firstWord, self::PREFIXES, true);
    }
}

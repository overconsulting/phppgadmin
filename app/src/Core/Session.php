<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestion minimale de la session : démarrage et messages flash (PRG).
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Empile un message flash, affiché une seule fois au prochain rendu.
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Récupère et vide les messages flash.
     *
     * @return list<array{type:string, message:string}>
     */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flash;
    }

    /**
     * Mémorise la dernière requête d'écriture exécutée, pour l'afficher après redirection.
     */
    public static function setLastSql(string $sql): void
    {
        $_SESSION['_last_sql'] = $sql;
    }

    public static function takeLastSql(): ?string
    {
        $sql = $_SESSION['_last_sql'] ?? null;
        unset($_SESSION['_last_sql']);

        return is_string($sql) ? $sql : null;
    }
}

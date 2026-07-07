<?php

declare(strict_types=1);

namespace App\Core;

use App\Service\PostgresInspector;

/**
 * Contrôleur de base : expose la BDD, la vue et l'inspecteur PostgreSQL,
 * et fournit les données de navigation communes (sidebar).
 */
abstract class Controller
{
    protected PostgresInspector $inspector;

    public function __construct(
        protected Database $db,
        protected View $view,
    ) {
        $this->inspector = new PostgresInspector($db);
    }

    /**
     * Enrichit les données d'une vue avec ce dont le layout a besoin (liste des bases,
     * base courante). Tolère une erreur de connexion pour pouvoir afficher la page d'erreur.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function withNav(array $data, ?string $currentDb = null): array
    {
        try {
            $databases = $this->inspector->databases();
        } catch (\Throwable) {
            $databases = [];
        }

        // Pour la base courante, on charge l'arborescence schéma → tables/vues
        // afin que la sidebar permette de naviguer directement entre tables.
        $navTables = [];
        if ($currentDb !== null) {
            try {
                foreach ($this->inspector->schemas($currentDb) as $schema) {
                    $navTables[$schema] = $this->inspector->tables($currentDb, $schema);
                }
            } catch (\Throwable) {
                $navTables = [];
            }
        }

        return [
            'databases' => $databases,
            'currentDb' => $currentDb,
            'navTables' => $navTables,
            'csrf'      => Csrf::token(),
            'flashes'   => Session::takeFlash(),
            'lastSql'   => Session::takeLastSql(),
            ...$data,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Wrapper PDO pour PostgreSQL.
 *
 * PostgreSQL établit une connexion par base de données : pour explorer une base
 * précise, on (re)connecte sur le bon `dbname`. La base par défaut vient de PG_DEFAULT_DB.
 */
final class Database
{
    private ?PDO $pdo = null;
    private ?string $currentDb = null;

    /** @var array{host:string, port:string, user:string, password:string, default_db:string} */
    private array $cfg;

    public function __construct()
    {
        $this->cfg = Config::postgres();
    }

    /**
     * (Re)connecte sur la base demandée (ou la base par défaut) et renvoie le PDO.
     */
    public function connect(?string $dbname = null): PDO
    {
        $dbname ??= $this->cfg['default_db'];

        if ($this->pdo !== null && $this->currentDb === $dbname) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->cfg['host'],
            $this->cfg['port'],
            $dbname,
        );

        try {
            $this->pdo = new PDO($dsn, $this->cfg['user'], $this->cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Connexion PostgreSQL impossible (base "%s") : %s', $dbname, $e->getMessage()),
                previous: $e,
            );
        }

        $this->currentDb = $dbname;

        return $this->pdo;
    }

    /**
     * Exécute une requête préparée et renvoie toutes les lignes.
     *
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = [], ?string $dbname = null): array
    {
        $stmt = $this->connect($dbname)->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Exécute une requête préparée et renvoie la première ligne (ou null).
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = [], ?string $dbname = null): ?array
    {
        $stmt = $this->connect($dbname)->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Exécute une requête d'écriture préparée et renvoie le nombre de lignes affectées.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = [], ?string $dbname = null): int
    {
        $stmt = $this->connect($dbname)->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Quote un identifiant SQL (table, schéma, colonne) pour l'insérer sans risque
     * d'injection dans une requête. Les guillemets internes sont doublés.
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

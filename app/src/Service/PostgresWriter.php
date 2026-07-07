<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Constructeurs de requêtes d'écriture (INSERT / UPDATE / DELETE).
 *
 * Méthodes pures et testables : elles ne se connectent pas, renvoient `[sql, params]`.
 * Identifiants quotés via Database::quoteIdentifier() ; valeurs en paramètres liés.
 * Le ciblage UPDATE/DELETE exige une clé (WHERE non vide) → pas d'écriture de masse.
 */
final class PostgresWriter
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed> $values colonne => valeur (peut être null)
     * @return array{0:string, 1:array<string, mixed>}
     */
    public function buildInsert(string $schema, string $table, array $values): array
    {
        $ref = $this->ref($schema, $table);

        if ($values === []) {
            return [sprintf('INSERT INTO %s DEFAULT VALUES', $ref), []];
        }

        $cols    = [];
        $holders = [];
        $params  = [];
        $i       = 0;
        foreach ($values as $col => $val) {
            $ph        = 'v' . $i++;
            $cols[]    = $this->db->quoteIdentifier((string) $col);
            $holders[] = ':' . $ph;
            $params[$ph] = $val;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $ref,
            implode(', ', $cols),
            implode(', ', $holders),
        );

        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $values colonne => nouvelle valeur (SET)
     * @param array<string, mixed> $pk     colonne => valeur (WHERE) — non vide
     * @return array{0:string, 1:array<string, mixed>}
     */
    public function buildUpdate(string $schema, string $table, array $values, array $pk): array
    {
        if ($values === []) {
            throw new InvalidArgumentException('UPDATE sans colonne à modifier.');
        }
        if ($pk === []) {
            throw new InvalidArgumentException('UPDATE sans clé de ciblage (WHERE) refusé.');
        }

        $set    = [];
        $params = [];
        $i      = 0;
        foreach ($values as $col => $val) {
            $ph     = 'set' . $i++;
            $set[]  = $this->db->quoteIdentifier((string) $col) . ' = :' . $ph;
            $params[$ph] = $val;
        }

        [$where, $whereParams] = $this->whereFromKey($pk);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->ref($schema, $table),
            implode(', ', $set),
            $where,
        );

        return [$sql, [...$params, ...$whereParams]];
    }

    /**
     * @param array<string, mixed> $pk colonne => valeur (WHERE) — non vide
     * @return array{0:string, 1:array<string, mixed>}
     */
    public function buildDelete(string $schema, string $table, array $pk): array
    {
        if ($pk === []) {
            throw new InvalidArgumentException('DELETE sans clé de ciblage (WHERE) refusé.');
        }

        [$where, $params] = $this->whereFromKey($pk);

        return [sprintf('DELETE FROM %s WHERE %s', $this->ref($schema, $table), $where), $params];
    }

    /**
     * @param array<string, mixed> $pk
     * @return array{0:string, 1:array<string, mixed>}
     */
    private function whereFromKey(array $pk): array
    {
        $conds  = [];
        $params = [];
        $i      = 0;
        foreach ($pk as $col => $val) {
            $ph      = 'pk' . $i++;
            $conds[] = $this->db->quoteIdentifier((string) $col) . ' = :' . $ph;
            $params[$ph] = $val;
        }

        return [implode(' AND ', $conds), $params];
    }

    private function ref(string $schema, string $table): string
    {
        return $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);
    }
}

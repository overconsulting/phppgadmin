<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Constructeurs de requêtes DDL (ALTER/CREATE/DROP TABLE).
 *
 * Méthodes pures et testables : renvoient la chaîne SQL. Le DDL ne supporte pas les
 * paramètres liés → identifiants quotés via Database::quoteIdentifier(), types validés
 * par motif, expressions par défaut inlinées (`;` interdit pour empêcher l'enchaînement).
 */
final class PostgresDdl
{
    // Types : lettres, chiffres, espaces, parenthèses, crochets, virgule, point, _ et guillemets.
    private const TYPE_PATTERN = '/^[A-Za-z0-9 (),\[\]._\'"]+$/';

    public function __construct(private Database $db)
    {
    }

    public function addColumn(
        string $schema,
        string $table,
        string $name,
        string $type,
        bool $nullable,
        ?string $default,
    ): string {
        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($name),
            $this->type($type),
        );

        if (!$nullable) {
            $sql .= ' NOT NULL';
        }
        if ($default !== null && $default !== '') {
            $sql .= ' DEFAULT ' . $this->expr($default);
        }

        return $sql;
    }

    public function dropColumn(string $schema, string $table, string $name): string
    {
        return sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($name),
        );
    }

    public function renameColumn(string $schema, string $table, string $old, string $new): string
    {
        return sprintf(
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($old),
            $this->db->quoteIdentifier($new),
        );
    }

    public function setColumnType(string $schema, string $table, string $name, string $type): string
    {
        return sprintf(
            'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($name),
            $this->type($type),
        );
    }

    public function setNotNull(string $schema, string $table, string $name, bool $notNull): string
    {
        return sprintf(
            'ALTER TABLE %s ALTER COLUMN %s %s NOT NULL',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($name),
            $notNull ? 'SET' : 'DROP',
        );
    }

    public function setDefault(string $schema, string $table, string $name, ?string $default): string
    {
        $ref = $this->ref($schema, $table);
        $col = $this->db->quoteIdentifier($name);

        if ($default === null || $default === '') {
            return sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $ref, $col);
        }

        return sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $ref, $col, $this->expr($default));
    }

    public function renameTable(string $schema, string $table, string $newName): string
    {
        return sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->ref($schema, $table),
            $this->db->quoteIdentifier($newName),
        );
    }

    public function dropTable(string $schema, string $table): string
    {
        return sprintf('DROP TABLE %s', $this->ref($schema, $table));
    }

    /**
     * DROP TABLE de plusieurs tables en une seule commande (résout les dépendances entre elles).
     *
     * @param list<string> $names
     */
    public function dropTables(string $schema, array $names): string
    {
        $names = array_values(array_filter(
            array_map('trim', $names),
            static fn (string $n): bool => $n !== '',
        ));
        if ($names === []) {
            throw new InvalidArgumentException('Aucune table à supprimer.');
        }

        $refs = array_map(fn (string $n): string => $this->ref($schema, $n), $names);

        return 'DROP TABLE ' . implode(', ', $refs);
    }

    /**
     * @param list<array{name:string, type:string, nullable?:bool, default?:?string, pk?:bool}> $columns
     */
    public function createTable(string $schema, string $name, array $columns): string
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Une table doit avoir au moins une colonne.');
        }

        $defs = [];
        $pk   = [];
        foreach ($columns as $col) {
            $def = $this->db->quoteIdentifier($col['name']) . ' ' . $this->type($col['type']);

            if (($col['nullable'] ?? true) === false) {
                $def .= ' NOT NULL';
            }
            if (isset($col['default']) && $col['default'] !== null && $col['default'] !== '') {
                $def .= ' DEFAULT ' . $this->expr($col['default']);
            }

            $defs[] = $def;

            if (!empty($col['pk'])) {
                $pk[] = $this->db->quoteIdentifier($col['name']);
            }
        }

        if ($pk !== []) {
            $defs[] = 'PRIMARY KEY (' . implode(', ', $pk) . ')';
        }

        return sprintf('CREATE TABLE %s (%s)', $this->ref($schema, $name), implode(', ', $defs));
    }

    /**
     * CREATE DATABASE. À exécuter HORS transaction (contrainte PostgreSQL).
     */
    public function createDatabase(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Le nom de la base est requis.');
        }

        return 'CREATE DATABASE ' . $this->db->quoteIdentifier($name);
    }

    /**
     * ALTER DATABASE … RENAME TO …. À exécuter HORS transaction et connecté à une AUTRE base.
     */
    public function renameDatabase(string $old, string $new): string
    {
        $new = trim($new);
        if ($new === '') {
            throw new InvalidArgumentException('Le nouveau nom de la base est requis.');
        }

        return sprintf(
            'ALTER DATABASE %s RENAME TO %s',
            $this->db->quoteIdentifier($old),
            $this->db->quoteIdentifier($new),
        );
    }

    /**
     * DROP DATABASE. À exécuter HORS transaction et connecté à une AUTRE base.
     */
    public function dropDatabase(string $name): string
    {
        return 'DROP DATABASE ' . $this->db->quoteIdentifier($name);
    }

    /**
     * @param list<string> $columns
     */
    public function createIndex(string $schema, string $table, array $columns, bool $unique, ?string $name): string
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Un index doit porter sur au moins une colonne.');
        }

        $cols = implode(', ', array_map(fn (string $c): string => $this->db->quoteIdentifier($c), $columns));

        $sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ';
        if ($name !== null && $name !== '') {
            $sql .= $this->db->quoteIdentifier($name) . ' ';
        }
        $sql .= 'ON ' . $this->ref($schema, $table) . ' (' . $cols . ')';

        return $sql;
    }

    public function dropIndex(string $schema, string $name): string
    {
        return 'DROP INDEX ' . $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($name);
    }

    /** Actions référentielles autorisées pour ON DELETE. */
    public const FK_ACTIONS = ['NO ACTION', 'RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT'];

    public function addForeignKey(
        string $schema,
        string $table,
        string $column,
        string $refSchema,
        string $refTable,
        string $refColumn,
        ?string $onDelete,
        ?string $name,
    ): string {
        $sql = 'ALTER TABLE ' . $this->ref($schema, $table) . ' ADD ';
        if ($name !== null && $name !== '') {
            $sql .= 'CONSTRAINT ' . $this->db->quoteIdentifier($name) . ' ';
        }
        $sql .= 'FOREIGN KEY (' . $this->db->quoteIdentifier($column) . ') '
              . 'REFERENCES ' . $this->ref($refSchema, $refTable) . ' (' . $this->db->quoteIdentifier($refColumn) . ')';

        if ($onDelete !== null && $onDelete !== '') {
            $action = strtoupper(trim($onDelete));
            if (!in_array($action, self::FK_ACTIONS, true)) {
                throw new InvalidArgumentException('Action ON DELETE invalide : ' . $onDelete);
            }
            $sql .= ' ON DELETE ' . $action;
        }

        return $sql;
    }

    /** Suppression d'une contrainte (clé étrangère, unique, …). */
    public function dropConstraint(string $schema, string $table, string $name): string
    {
        return 'ALTER TABLE ' . $this->ref($schema, $table) . ' DROP CONSTRAINT ' . $this->db->quoteIdentifier($name);
    }

    /**
     * Valide et renvoie un type SQL (refuse l'injection / l'enchaînement de requêtes).
     */
    private function type(string $type): string
    {
        $type = trim($type);

        if ($type === '' || !preg_match(self::TYPE_PATTERN, $type)) {
            throw new InvalidArgumentException(sprintf('Type SQL invalide : "%s".', $type));
        }

        return $type;
    }

    /**
     * Valide une expression par défaut (inlinée telle quelle ; `;` interdit).
     */
    private function expr(string $expr): string
    {
        if (str_contains($expr, ';')) {
            throw new InvalidArgumentException('Expression par défaut invalide (caractère ";" interdit).');
        }

        return $expr;
    }

    private function ref(string $schema, string $table): string
    {
        return $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);
    }
}

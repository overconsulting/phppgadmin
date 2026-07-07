<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

/**
 * Introspection d'un serveur PostgreSQL : bases, schémas, tables/vues, colonnes,
 * clés primaires, index et lecture paginée des données.
 *
 * Toutes les requêtes sont en lecture seule et utilisent des requêtes préparées
 * pour les valeurs ; les identifiants sont quotés via Database::quoteIdentifier().
 */
final class PostgresInspector
{
    private const SYSTEM_SCHEMAS = "'pg_catalog', 'information_schema', 'pg_toast'";

    public function __construct(private Database $db)
    {
    }

    /**
     * Liste des bases de données du serveur (hors templates).
     *
     * @return list<string>
     */
    public function databases(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname',
        );

        return array_map(static fn (array $r): string => (string) $r['datname'], $rows);
    }

    /**
     * Liste des schémas applicatifs d'une base (exclut les schémas système).
     *
     * @return list<string>
     */
    public function schemas(string $database): array
    {
        $rows = $this->db->fetchAll(
            'SELECT schema_name FROM information_schema.schemata
             WHERE schema_name NOT IN (' . self::SYSTEM_SCHEMAS . ")
               AND schema_name NOT LIKE 'pg_%'
             ORDER BY schema_name",
            [],
            $database,
        );

        return array_map(static fn (array $r): string => (string) $r['schema_name'], $rows);
    }

    /**
     * Tables et vues d'un schéma.
     *
     * @return list<array{name:string, type:string}>
     */
    public function tables(string $database, string $schema): array
    {
        $rows = $this->db->fetchAll(
            "SELECT table_name, table_type
             FROM information_schema.tables
             WHERE table_schema = :schema
             ORDER BY table_type, table_name",
            ['schema' => $schema],
            $database,
        );

        return array_map(
            static fn (array $r): array => [
                'name' => (string) $r['table_name'],
                // 'BASE TABLE' => table, 'VIEW' => vue
                'type' => $r['table_type'] === 'VIEW' ? 'vue' : 'table',
            ],
            $rows,
        );
    }

    /**
     * Type d'un objet : « table », « vue », ou null s'il n'existe pas.
     */
    public function tableType(string $database, string $schema, string $table): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT table_type FROM information_schema.tables
             WHERE table_schema = :schema AND table_name = :table',
            ['schema' => $schema, 'table' => $table],
            $database,
        );

        if ($row === null) {
            return null;
        }

        return $row['table_type'] === 'VIEW' ? 'vue' : 'table';
    }

    /**
     * Colonnes d'une table/vue.
     *
     * @return list<array{name:string, type:string, nullable:bool, default:?string}>
     */
    public function columns(string $database, string $schema, string $table): array
    {
        $rows = $this->db->fetchAll(
            "SELECT column_name, data_type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table
             ORDER BY ordinal_position",
            ['schema' => $schema, 'table' => $table],
            $database,
        );

        return array_map(
            static fn (array $r): array => [
                'name'     => (string) $r['column_name'],
                'type'     => (string) $r['data_type'],
                'nullable' => $r['is_nullable'] === 'YES',
                'default'  => $r['column_default'] !== null ? (string) $r['column_default'] : null,
            ],
            $rows,
        );
    }

    /**
     * Noms des colonnes composant la clé primaire d'une table.
     *
     * @return list<string>
     */
    public function primaryKey(string $database, string $schema, string $table): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.attname
             FROM pg_index i
             JOIN pg_class c        ON c.oid = i.indrelid
             JOIN pg_namespace n    ON n.oid = c.relnamespace
             JOIN pg_attribute a    ON a.attrelid = c.oid AND a.attnum = ANY (i.indkey)
             WHERE i.indisprimary
               AND n.nspname = :schema
               AND c.relname = :table",
            ['schema' => $schema, 'table' => $table],
            $database,
        );

        return array_map(static fn (array $r): string => (string) $r['attname'], $rows);
    }

    /**
     * Index d'une table (nom + définition).
     *
     * @return list<array{name:string, definition:string, is_constraint:bool, constraint_name:?string}>
     */
    public function indexes(string $database, string $schema, string $table): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.relname                  AS name,
                    pg_get_indexdef(i.indexrelid) AS definition,
                    (con.oid IS NOT NULL)      AS is_constraint,
                    con.conname                AS constraint_name
             FROM pg_index i
             JOIN pg_class c        ON c.oid = i.indexrelid
             JOIN pg_class t        ON t.oid = i.indrelid
             JOIN pg_namespace n    ON n.oid = t.relnamespace
             LEFT JOIN pg_constraint con ON con.conindid = i.indexrelid
             WHERE n.nspname = :schema AND t.relname = :table
             ORDER BY c.relname',
            ['schema' => $schema, 'table' => $table],
            $database,
        );

        return array_map(
            static fn (array $r): array => [
                'name'            => (string) $r['name'],
                'definition'      => (string) $r['definition'],
                // PDO pgsql renvoie les booléens en 't'/'f'.
                'is_constraint'   => $r['is_constraint'] === true || $r['is_constraint'] === 't',
                'constraint_name' => $r['constraint_name'] !== null ? (string) $r['constraint_name'] : null,
            ],
            $rows,
        );
    }

    /**
     * Clés étrangères d'une table : colonne locale → table/colonne référencée.
     *
     * @return list<array{name:string, column:string, ref_schema:string, ref_table:string, ref_column:string}>
     */
    public function foreignKeys(string $database, string $schema, string $table): array
    {
        $rows = $this->db->fetchAll(
            "SELECT tc.constraint_name   AS name,
                    kcu.column_name      AS column,
                    ccu.table_schema     AS ref_schema,
                    ccu.table_name       AS ref_table,
                    ccu.column_name      AS ref_column
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = :schema AND tc.table_name = :table
             ORDER BY kcu.column_name",
            ['schema' => $schema, 'table' => $table],
            $database,
        );

        return array_map(
            static fn (array $r): array => [
                'name'       => (string) $r['name'],
                'column'     => (string) $r['column'],
                'ref_schema' => (string) $r['ref_schema'],
                'ref_table'  => (string) $r['ref_table'],
                'ref_column' => (string) $r['ref_column'],
            ],
            $rows,
        );
    }

    /**
     * Nombre de lignes d'une table, en tenant compte d'un filtre optionnel.
     *
     * $filterCol doit avoir été validé en amont (∈ colonnes de la table) ; il est
     * de toute façon quoté via quoteIdentifier(). $filterVal passe en requête préparée.
     */
    public function rowCount(
        string $database,
        string $schema,
        string $table,
        ?string $filterCol = null,
        ?string $filterVal = null,
        string $filterOp = 'contains',
    ): int {
        $ref = $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);
        [$where, $params] = $this->buildFilter($filterCol, $filterVal, $filterOp);
        $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM ' . $ref . $where, $params, $database);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Lit une page de lignes d'une table, avec tri et filtre optionnels.
     *
     * $sort et $filterCol doivent être des noms de colonnes valides (validés en amont) ;
     * ils sont quotés via quoteIdentifier(). $dir est normalisé à ASC/DESC.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(
        string $database,
        string $schema,
        string $table,
        int $limit,
        int $offset,
        ?string $sort = null,
        string $dir = 'asc',
        ?string $filterCol = null,
        ?string $filterVal = null,
        string $filterOp = 'contains',
    ): array {
        $ref = $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);
        [$where, $params] = $this->buildFilter($filterCol, $filterVal, $filterOp);

        // LIMIT/OFFSET sont des entiers (cast int en amont) — interpolation sûre.
        $sql = sprintf(
            'SELECT * FROM %s%s%s LIMIT %d OFFSET %d',
            $ref, $where, $this->orderClause($sort, $dir), $limit, $offset,
        );

        return $this->db->fetchAll($sql, $params, $database);
    }

    /**
     * Construit la requête SELECT effective, sous forme de chaîne exécutable
     * (valeur de filtre inlinée comme littéral) — pour l'afficher et la rejouer
     * dans la console SQL. Échappement standard PostgreSQL des littéraux.
     */
    public function selectSql(
        string $schema,
        string $table,
        int $limit,
        int $offset,
        ?string $sort = null,
        string $dir = 'asc',
        ?string $filterCol = null,
        ?string $filterVal = null,
        string $filterOp = 'contains',
    ): string {
        $ref = $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);

        $where = '';
        if ($this->filterActive($filterCol, $filterVal)) {
            $op      = $this->normalizeOp($filterOp);
            $pattern = $this->filterPattern((string) $filterVal, $op);
            $literal = "'" . str_replace("'", "''", $pattern) . "'";
            $where   = ' WHERE ' . $this->db->quoteIdentifier((string) $filterCol)
                     . '::text ' . $this->sqlOperator($op) . ' ' . $literal;
        }

        return sprintf(
            'SELECT * FROM %s%s%s LIMIT %d OFFSET %d',
            $ref, $where, $this->orderClause($sort, $dir), $limit, $offset,
        );
    }

    /**
     * Opérateurs de filtre disponibles → libellé affiché.
     */
    public const OPERATORS = [
        'eq'       => 'égal (=)',
        'contains' => 'contient (%v%)',
        'starts'   => 'commence par (v%)',
        'ends'     => 'finit par (%v)',
    ];

    /**
     * Construit la clause WHERE de filtre (requête préparée).
     *
     * @return array{0:string, 1:array<string, string>}
     */
    private function buildFilter(?string $filterCol, ?string $filterVal, string $filterOp): array
    {
        if (!$this->filterActive($filterCol, $filterVal)) {
            return ['', []];
        }

        $op    = $this->normalizeOp($filterOp);
        $where = ' WHERE ' . $this->db->quoteIdentifier((string) $filterCol)
               . '::text ' . $this->sqlOperator($op) . ' :filter';

        return [$where, ['filter' => $this->filterPattern((string) $filterVal, $op)]];
    }

    private function filterActive(?string $filterCol, ?string $filterVal): bool
    {
        return $filterCol !== null && $filterCol !== '' && $filterVal !== null && $filterVal !== '';
    }

    private function normalizeOp(string $op): string
    {
        return isset(self::OPERATORS[$op]) ? $op : 'contains';
    }

    /**
     * Opérateur SQL associé : « égal » → `=`, les autres → `ILIKE` (insensible à la casse).
     */
    private function sqlOperator(string $op): string
    {
        return $op === 'eq' ? '=' : 'ILIKE';
    }

    /**
     * Transforme la valeur saisie selon l'opérateur (ajout des jokers %).
     */
    private function filterPattern(string $value, string $op): string
    {
        return match ($op) {
            'eq'     => $value,
            'starts' => $value . '%',
            'ends'   => '%' . $value,
            default  => '%' . $value . '%',
        };
    }

    /**
     * Clause ORDER BY (vide si pas de tri). Direction normalisée ASC/DESC.
     */
    private function orderClause(?string $sort, string $dir): string
    {
        if ($sort === null || $sort === '') {
            return '';
        }

        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        return ' ORDER BY ' . $this->db->quoteIdentifier($sort) . ' ' . $direction;
    }
}

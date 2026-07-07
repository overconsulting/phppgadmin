<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Service\PostgresDdl;
use Throwable;

/**
 * Édition de la structure (DDL) : colonnes (ajout/édition/suppression),
 * table (renommer/supprimer) et création de table.
 *
 * Garde-fous : CSRF, exécution en transaction, DDL réservé aux tables de base.
 */
final class StructureController extends Controller
{
    /** Types proposés en autocomplétion (datalist). */
    public const COMMON_TYPES = [
        'integer', 'bigint', 'smallint', 'serial', 'bigserial', 'boolean',
        'text', 'varchar(255)', 'char(1)', 'numeric(10,2)', 'real', 'double precision',
        'date', 'time', 'timestamptz', 'timestamp', 'interval',
        'uuid', 'json', 'jsonb', 'bytea', 'inet', 'text[]',
    ];

    private PostgresDdl $ddl;

    public function __construct(Database $db, View $view)
    {
        parent::__construct($db, $view);
        $this->ddl = new PostgresDdl($db);
    }

    // --- Colonnes ----------------------------------------------------------

    /** @param array<string, string> $params */
    public function addColumnForm(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->ensureTable($database, $schema, $table)) !== null) {
            return $guard;
        }

        return $this->view->render('table/column_form', $this->withNav([
            'title'    => 'Ajouter une colonne — ' . $schema . '.' . $table,
            'database' => $database,
            'schema'   => $schema,
            'table'    => $table,
            'mode'     => 'add',
            'column'   => ['name' => '', 'type' => '', 'nullable' => true, 'default' => null],
            'types'    => self::COMMON_TYPES,
        ], $database));
    }

    /** @param array<string, string> $params */
    public function addColumn(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $name     = trim((string) $request->post('name', ''));
        $type     = trim((string) $request->post('type', ''));
        $nullable = $request->post('nullable') !== null;
        $default  = $this->nullableField($request->post('default'));

        if ($name === '' || $type === '') {
            Session::flash('error', 'Nom et type de colonne requis.');

            return Response::redirect($this->structureUrl($database, $schema, $table) . '/column/new');
        }

        return $this->runDdl($database, $schema, $table, [
            $this->ddl->addColumn($schema, $table, $name, $type, $nullable, $default),
        ], 'Colonne ajoutée.');
    }

    /** @param array<string, string> $params */
    public function editColumnForm(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->ensureTable($database, $schema, $table)) !== null) {
            return $guard;
        }

        $name   = (string) ($request->query('name') ?? '');
        $column = $this->findColumn($database, $schema, $table, $name);

        if ($column === null) {
            Session::flash('error', 'Colonne introuvable.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return $this->view->render('table/column_form', $this->withNav([
            'title'    => 'Éditer la colonne ' . $name . ' — ' . $schema . '.' . $table,
            'database' => $database,
            'schema'   => $schema,
            'table'    => $table,
            'mode'     => 'edit',
            'column'   => $column,
            'types'    => self::COMMON_TYPES,
        ], $database));
    }

    /** @param array<string, string> $params */
    public function editColumn(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $orig    = (string) $request->post('orig', '');
        $current = $this->findColumn($database, $schema, $table, $orig);

        if ($current === null) {
            Session::flash('error', 'Colonne introuvable.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        $newName  = trim((string) $request->post('name', '')) ?: $orig;
        $newType  = trim((string) $request->post('type', '')) ?: $current['type'];
        $nullable = $request->post('nullable') !== null;
        $default  = $this->nullableField($request->post('default'));

        // Diff → liste minimale de statements (rename d'abord pour le reste).
        $statements = [];
        $colRef     = $orig;

        if ($newName !== $orig) {
            $statements[] = $this->ddl->renameColumn($schema, $table, $orig, $newName);
            $colRef       = $newName;
        }
        if ($newType !== $current['type']) {
            $statements[] = $this->ddl->setColumnType($schema, $table, $colRef, $newType);
        }
        if ($nullable !== $current['nullable']) {
            $statements[] = $this->ddl->setNotNull($schema, $table, $colRef, !$nullable);
        }
        $currentDefault = $current['default'];
        if (($default ?? '') !== ($currentDefault ?? '')) {
            $statements[] = $this->ddl->setDefault($schema, $table, $colRef, $default);
        }

        if ($statements === []) {
            Session::flash('success', 'Aucune modification.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return $this->runDdl($database, $schema, $table, $statements, 'Colonne modifiée.');
    }

    /** @param array<string, string> $params */
    public function dropColumn(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $name = (string) $request->post('name', '');
        if ($this->findColumn($database, $schema, $table, $name) === null) {
            Session::flash('error', 'Colonne introuvable.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return $this->runDdl($database, $schema, $table, [
            $this->ddl->dropColumn($schema, $table, $name),
        ], 'Colonne supprimée.');
    }

    // --- Table -------------------------------------------------------------

    /** Onglet « Action » : renommer / supprimer la table. @param array<string, string> $params */
    public function tableActions(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->ensureTable($database, $schema, $table)) !== null) {
            return $guard;
        }

        return $this->view->render('table/action', $this->withNav([
            'title'    => 'Opérations — ' . $schema . '.' . $table,
            'database' => $database,
            'schema'   => $schema,
            'table'    => $table,
        ], $database));
    }

    /** @param array<string, string> $params */
    public function renameTable(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $newName = trim((string) $request->post('name', ''));
        if ($newName === '') {
            Session::flash('error', 'Nouveau nom de table requis.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        try {
            $sql = $this->ddl->renameTable($schema, $table, $newName);
            $this->execute($database, [$sql]);
            Session::setLastSql($sql);
            Session::flash('success', 'Table renommée en ' . $newName . '.');

            return Response::redirect($this->structureUrl($database, $schema, $newName));
        } catch (Throwable $e) {
            Session::flash('error', 'Renommage impossible : ' . $e->getMessage());

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }
    }

    /** @param array<string, string> $params */
    public function dropTable(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $dbUrl = '/db/' . rawurlencode($database);

        try {
            $sql = $this->ddl->dropTable($schema, $table);
            $this->execute($database, [$sql]);
            Session::setLastSql($sql);
            Session::flash('success', 'Table ' . $schema . '.' . $table . ' supprimée.');
        } catch (Throwable $e) {
            Session::flash('error', 'Suppression impossible : ' . $e->getMessage());

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return Response::redirect($dbUrl);
    }

    // --- Création de table -------------------------------------------------

    /** @param array<string, string> $params */
    public function createTableForm(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = (string) ($request->query('schema') ?? 'public');

        return $this->view->render('table/create_table', $this->withNav([
            'title'    => 'Créer une table — ' . $database,
            'database' => $database,
            'schema'   => $schema,
            'types'    => self::COMMON_TYPES,
        ], $database));
    }

    /** @param array<string, string> $params */
    public function createTable(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = trim((string) $request->post('schema', 'public')) ?: 'public';
        $name     = trim((string) $request->post('name', ''));

        $createUrl = '/db/' . rawurlencode($database) . '/create-table?schema=' . rawurlencode($schema);

        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($createUrl);
        }
        if ($name === '') {
            Session::flash('error', 'Nom de table requis.');

            return Response::redirect($createUrl);
        }

        $columns = $this->parseColumns($request->allPost()['cols'] ?? []);
        if ($columns === []) {
            Session::flash('error', 'Ajoute au moins une colonne (nom + type).');

            return Response::redirect($createUrl);
        }

        try {
            $sql = $this->ddl->createTable($schema, $name, $columns);
            $this->execute($database, [$sql]);
            Session::setLastSql($sql);
            Session::flash('success', 'Table ' . $schema . '.' . $name . ' créée.');

            return Response::redirect($this->structureUrl($database, $schema, $name));
        } catch (Throwable $e) {
            Session::flash('error', 'Création impossible : ' . $e->getMessage());

            return Response::redirect($createUrl);
        }
    }

    /**
     * Supprime plusieurs tables d'un schéma en une fois (cases à cochées sur la liste).
     *
     * @param array<string, string> $params
     */
    public function dropTables(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = trim((string) $request->post('schema', 'public')) ?: 'public';
        $dbUrl    = '/db/' . rawurlencode($database);

        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($dbUrl);
        }

        $selected = $request->allPost()['tables'] ?? [];
        $names    = is_array($selected)
            ? array_values(array_filter(
                array_map('strval', $selected),
                static fn (string $n): bool => trim($n) !== '',
            ))
            : [];

        if ($names === []) {
            Session::flash('error', 'Aucune table sélectionnée.');

            return Response::redirect($dbUrl);
        }

        try {
            $sql = $this->ddl->dropTables($schema, $names);
            $this->execute($database, [$sql]);
            Session::setLastSql($sql);
            Session::flash('success', count($names) . ' table(s) supprimée(s).');
        } catch (Throwable $e) {
            Session::flash('error', 'Suppression impossible : ' . $e->getMessage());
        }

        return Response::redirect($dbUrl);
    }

    // --- Index -------------------------------------------------------------

    /** @param array<string, string> $params */
    public function addIndexForm(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->ensureTable($database, $schema, $table)) !== null) {
            return $guard;
        }

        return $this->view->render('table/index_form', $this->withNav([
            'title'    => 'Ajouter un index — ' . $schema . '.' . $table,
            'database' => $database,
            'schema'   => $schema,
            'table'    => $table,
            'columns'  => $this->columnNames($database, $schema, $table),
        ], $database));
    }

    /** @param array<string, string> $params */
    public function addIndex(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $all      = $this->columnNames($database, $schema, $table);
        $selected = array_values(array_intersect($all, $this->arr($request->allPost(), 'columns')));
        $unique   = $request->post('unique') !== null;
        $name     = $this->nullableField($request->post('name'));

        if ($selected === []) {
            Session::flash('error', 'Sélectionne au moins une colonne pour l’index.');

            return Response::redirect($this->structureUrl($database, $schema, $table) . '/index/new');
        }

        return $this->runDdl($database, $schema, $table, [
            $this->ddl->createIndex($schema, $table, $selected, $unique, $name),
        ], 'Index créé.');
    }

    /** @param array<string, string> $params */
    public function dropIndex(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $name  = (string) $request->post('name', '');
        $index = $this->findIndex($database, $schema, $table, $name);

        if ($index === null) {
            Session::flash('error', 'Index introuvable.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        // Un index porté par une contrainte (PK / UNIQUE) se retire via DROP CONSTRAINT.
        $statement = $index['is_constraint'] && $index['constraint_name'] !== null
            ? $this->ddl->dropConstraint($schema, $table, $index['constraint_name'])
            : $this->ddl->dropIndex($schema, $name);

        return $this->runDdl($database, $schema, $table, [$statement], 'Index supprimé.');
    }

    // --- Clés étrangères ---------------------------------------------------

    /** @param array<string, string> $params */
    public function addFkForm(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->ensureTable($database, $schema, $table)) !== null) {
            return $guard;
        }

        // Carte table → colonnes (tables de base du schéma) pour peupler les menus.
        $refMap = [];
        foreach ($this->inspector->tables($database, $schema) as $t) {
            if ($t['type'] === 'table') {
                $refMap[$t['name']] = $this->columnNames($database, $schema, $t['name']);
            }
        }

        return $this->view->render('table/fk_form', $this->withNav([
            'title'      => 'Ajouter une clé étrangère — ' . $schema . '.' . $table,
            'database'   => $database,
            'schema'     => $schema,
            'table'      => $table,
            'columns'    => $this->columnNames($database, $schema, $table),
            'refMap'     => $refMap,
            'onDelete'   => PostgresDdl::FK_ACTIONS,
        ], $database));
    }

    /** @param array<string, string> $params */
    public function addFk(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $column    = (string) $request->post('column', '');
        $refTable  = (string) $request->post('ref_table', '');
        $refColumn = (string) $request->post('ref_column', '');
        $onDelete  = $this->nullableField($request->post('on_delete'));
        $name      = $this->nullableField($request->post('name'));

        $valid = in_array($column, $this->columnNames($database, $schema, $table), true)
            && $this->inspector->tableType($database, $schema, $refTable) === 'table'
            && in_array($refColumn, $this->columnNames($database, $schema, $refTable), true);

        if (!$valid) {
            Session::flash('error', 'Colonne ou table référencée invalide.');

            return Response::redirect($this->structureUrl($database, $schema, $table) . '/fk/new');
        }

        return $this->runDdl($database, $schema, $table, [
            $this->ddl->addForeignKey($schema, $table, $column, $schema, $refTable, $refColumn, $onDelete, $name),
        ], 'Clé étrangère ajoutée.');
    }

    /** @param array<string, string> $params */
    public function dropFk(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($guard = $this->guard($request, $database, $schema, $table)) !== null) {
            return $guard;
        }

        $name = (string) $request->post('name', '');
        if ($name === '') {
            Session::flash('error', 'Contrainte introuvable.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return $this->runDdl($database, $schema, $table, [
            $this->ddl->dropConstraint($schema, $table, $name),
        ], 'Clé étrangère supprimée.');
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * Extrait un sous-tableau d'une source ($_POST['columns']…).
     *
     * @param array<string, mixed> $source
     * @return array<int|string, mixed>
     */
    private function arr(array $source, string $key): array
    {
        $value = $source[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $database, string $schema, string $table): array
    {
        return array_map(
            static fn (array $c): string => $c['name'],
            $this->inspector->columns($database, $schema, $table),
        );
    }

    /**
     * @param array<string, string> $params
     * @return array{0:string, 1:string, 2:string}
     */
    private function target(array $params): array
    {
        return [$params['db'], $params['schema'], $params['table']];
    }

    private function structureUrl(string $database, string $schema, string $table): string
    {
        return sprintf(
            '/db/%s/table/%s/%s/structure',
            rawurlencode($database),
            rawurlencode($schema),
            rawurlencode($table),
        );
    }

    /**
     * Vérifie que l'objet est une table de base (sinon redirige). Pour les formulaires (GET).
     */
    private function ensureTable(string $database, string $schema, string $table): ?Response
    {
        if ($this->inspector->tableType($database, $schema, $table) === 'table') {
            return null;
        }

        Session::flash('error', 'Modification de structure impossible (objet introuvable ou vue).');

        return Response::redirect($this->structureUrl($database, $schema, $table));
    }

    /**
     * CSRF + table de base, pour les actions (POST). Renvoie une redirection si refus.
     */
    private function guard(Request $request, string $database, string $schema, string $table): ?Response
    {
        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($this->structureUrl($database, $schema, $table));
        }

        return $this->ensureTable($database, $schema, $table);
    }

    /**
     * Exécute des statements DDL en une transaction, puis enregistre le SQL et flashe le succès.
     *
     * @param list<string> $statements
     */
    private function runDdl(string $database, string $schema, string $table, array $statements, string $okMessage): Response
    {
        try {
            $this->execute($database, $statements);
            Session::setLastSql(implode(";\n", $statements));
            Session::flash('success', $okMessage);
        } catch (Throwable $e) {
            Session::flash('error', 'Opération impossible : ' . $e->getMessage());
        }

        return Response::redirect($this->structureUrl($database, $schema, $table));
    }

    /**
     * Exécute des statements DDL dans une transaction (rollback si échec).
     *
     * @param list<string> $statements
     */
    private function execute(string $database, array $statements): void
    {
        $pdo = $this->db->connect($database);
        $pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{name:string, definition:string, is_constraint:bool, constraint_name:?string}|null
     */
    private function findIndex(string $database, string $schema, string $table, string $name): ?array
    {
        foreach ($this->inspector->indexes($database, $schema, $table) as $idx) {
            if ($idx['name'] === $name) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * @return array{name:string, type:string, nullable:bool, default:?string}|null
     */
    private function findColumn(string $database, string $schema, string $table, string $name): ?array
    {
        foreach ($this->inspector->columns($database, $schema, $table) as $col) {
            if ($col['name'] === $name) {
                return $col;
            }
        }

        return null;
    }

    /**
     * Champ texte → null si vide.
     */
    private function nullableField(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalise les colonnes postées du formulaire de création.
     *
     * @param mixed $raw  $_POST['cols'] : liste de {name,type,nullable,default,pk}
     * @return list<array{name:string, type:string, nullable:bool, default:?string, pk:bool}>
     */
    private function parseColumns(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $columns = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $type = trim((string) ($row['type'] ?? ''));
            if ($name === '' || $type === '') {
                continue;
            }
            $columns[] = [
                'name'     => $name,
                'type'     => $type,
                'nullable' => !isset($row['notnull']),
                'default'  => $this->nullableField($row['default'] ?? null),
                'pk'       => isset($row['pk']),
            ];
        }

        return $columns;
    }
}

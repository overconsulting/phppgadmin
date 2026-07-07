<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Response;
use App\Service\PostgresInspector;

/**
 * Structure et données d'une table (ou vue).
 */
final class TableController extends Controller
{
    private const PER_PAGE = 50;
    private const EXPORT_MAX = 10000;

    /**
     * Onglet « Structure » : colonnes, clé primaire, index, clés étrangères.
     *
     * @param array<string, string> $params
     */
    public function structure(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = $params['schema'];
        $table    = $params['table'];

        return $this->view->render('table/structure', $this->withNav([
            'title'       => $schema . '.' . $table,
            'database'    => $database,
            'schema'      => $schema,
            'table'       => $table,
            'tab'         => 'structure',
            'columns'     => $this->inspector->columns($database, $schema, $table),
            'primaryKey'  => $this->inspector->primaryKey($database, $schema, $table),
            'indexes'     => $this->inspector->indexes($database, $schema, $table),
            'foreignKeys' => $this->inspector->foreignKeys($database, $schema, $table),
            'isTable'     => $this->inspector->tableType($database, $schema, $table) === 'table',
        ], $database));
    }

    /**
     * Onglet « Données » : lignes paginées, triables et filtrables.
     *
     * @param array<string, string> $params
     */
    public function data(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = $params['schema'];
        $table    = $params['table'];

        $headers   = $this->columnNames($database, $schema, $table);
        $sort      = $this->validColumn($request->query('sort'), $headers);
        $dir       = strtolower($request->query('dir') ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $filterCol = $this->validColumn($request->query('filter_col'), $headers);
        $filterOp  = $this->validOp($request->query('filter_op'));
        $filterVal = $request->query('filter_val');
        if ($filterCol === null) {
            $filterVal = null;
        }

        $total      = $this->inspector->rowCount($database, $schema, $table, $filterCol, $filterVal, $filterOp);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = max(1, min($totalPages, (int) ($request->query('page') ?? 1)));
        $offset     = ($page - 1) * self::PER_PAGE;

        $rows = $this->inspector->rows(
            $database, $schema, $table, self::PER_PAGE, $offset, $sort, $dir, $filterCol, $filterVal, $filterOp,
        );

        $executedSql = $this->inspector->selectSql(
            $schema, $table, self::PER_PAGE, $offset, $sort, $dir, $filterCol, $filterVal, $filterOp,
        );

        return $this->view->render('table/data', $this->withNav([
            'title'       => $schema . '.' . $table,
            'database'    => $database,
            'schema'      => $schema,
            'table'       => $table,
            'tab'         => 'data',
            'rows'        => $rows,
            'headers'     => $headers,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'sort'        => $sort,
            'dir'         => $dir,
            'filterCol'   => $filterCol,
            'filterVal'   => $filterVal,
            'filterOp'    => $filterOp,
            'operators'   => PostgresInspector::OPERATORS,
            'executedSql' => $executedSql,
            'primaryKey'  => $this->inspector->primaryKey($database, $schema, $table),
            'isTable'     => $this->inspector->tableType($database, $schema, $table) === 'table',
        ], $database));
    }

    /**
     * Export CSV des données de la table (filtre/tri courants appliqués, plafonné).
     *
     * @param array<string, string> $params
     */
    public function exportCsv(Request $request, array $params): Response
    {
        $database = $params['db'];
        $schema   = $params['schema'];
        $table    = $params['table'];

        $headers   = $this->columnNames($database, $schema, $table);
        $sort      = $this->validColumn($request->query('sort'), $headers);
        $dir       = strtolower($request->query('dir') ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $filterCol = $this->validColumn($request->query('filter_col'), $headers);
        $filterOp  = $this->validOp($request->query('filter_op'));
        $filterVal = $filterCol === null ? null : $request->query('filter_val');

        $rows = $this->inspector->rows(
            $database, $schema, $table, self::EXPORT_MAX, 0, $sort, $dir, $filterCol, $filterVal, $filterOp,
        );

        $csv      = Csv::fromRows($headers, $rows);
        $filename = sprintf('%s.%s.csv', $schema, $table);

        return Response::attachment($csv, $filename);
    }

    /**
     * Noms des colonnes d'une table/vue, dans l'ordre.
     *
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
     * Renvoie $value seulement si c'est une colonne connue, sinon null.
     *
     * @param list<string> $columns
     */
    private function validColumn(?string $value, array $columns): ?string
    {
        return $value !== null && in_array($value, $columns, true) ? $value : null;
    }

    /**
     * Normalise l'opérateur de filtre (valeur connue, sinon « contains »).
     */
    private function validOp(?string $value): string
    {
        return $value !== null && array_key_exists($value, PostgresInspector::OPERATORS) ? $value : 'contains';
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\Request;
use App\Core\Response;
use App\Service\SqlReadGuard;
use Throwable;

/**
 * Console SQL.
 *
 * Deux modes :
 *  - LECTURE SEULE (défaut) : défense en profondeur — la requête doit commencer par
 *    SELECT/WITH/EXPLAIN (SqlReadGuard) ET s'exécute dans une transaction `READ ONLY`.
 *  - ÉCRITURE (case « Mode écriture » + CSRF) : exécution read/write committée ;
 *    affiche le nombre de lignes affectées pour INSERT/UPDATE/DELETE/DDL.
 */
final class QueryController extends Controller
{
    private const MAX_ROWS = 200;
    private const EXPORT_MAX = 10000;

    /**
     * Affiche la console (formulaire éventuellement prérempli via ?sql=).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $sql = (string) ($request->query('sql') ?? '');

        return $this->renderConsole($params['db'], $sql);
    }

    /**
     * Exécute la requête soumise.
     *
     * @param array<string, string> $params
     */
    public function run(Request $request, array $params): Response
    {
        $database  = $params['db'];
        $sql       = trim((string) $request->post('sql', ''));
        $writeMode = $request->post('write') !== null;

        if ($sql === '') {
            return $this->renderConsole($database, $sql, error: 'Saisis une requête SQL.', writeMode: $writeMode);
        }

        if ($writeMode) {
            return $this->runWrite($request, $database, $sql);
        }

        return $this->runReadOnly($request, $database, $sql);
    }

    /**
     * Export CSV du résultat d'une requête (ré-exécutée en lecture seule).
     *
     * @param array<string, string> $params
     */
    public function export(Request $request, array $params): Response
    {
        $database = $params['db'];
        $sql      = trim((string) $request->post('sql', ''));

        if ($sql === '' || !SqlReadGuard::isReadOnly($sql)) {
            return $this->renderConsole(
                $database,
                $sql,
                error: 'Export impossible : requête vide ou non autorisée (lecture seule).',
            );
        }

        try {
            [$headers, $rows] = $this->executeReadOnly($database, $sql, self::EXPORT_MAX);
        } catch (Throwable $e) {
            return $this->renderConsole($database, $sql, error: $e->getMessage());
        }

        return Response::attachment(Csv::fromRows($headers, $rows), 'query.csv');
    }

    /**
     * Chemin lecture seule : garde de préfixe + bouton EXPLAIN + transaction READ ONLY.
     */
    private function runReadOnly(Request $request, string $database, string $sql): Response
    {
        // Bouton « EXPLAIN » : on préfixe la requête si ce n'est pas déjà fait.
        $effective = $sql;
        if ($request->post('explain') !== null && stripos(ltrim($sql), 'EXPLAIN') !== 0) {
            $effective = 'EXPLAIN ' . $sql;
        }

        if (!SqlReadGuard::isReadOnly($effective)) {
            return $this->renderConsole(
                $database,
                $sql,
                error: 'Requête refusée : seules les requêtes en lecture (SELECT, WITH, EXPLAIN) '
                     . 'sont autorisées. Coche « Mode écriture » pour les requêtes d’écriture.',
            );
        }

        try {
            [$headers, $rows, $elapsedMs] = $this->executeReadOnly($database, $effective, self::MAX_ROWS);
        } catch (Throwable $e) {
            return $this->renderConsole($database, $sql, error: $e->getMessage());
        }

        return $this->renderConsole(
            $database, $sql, headers: $headers, rows: $rows, count: count($rows), elapsedMs: $elapsedMs,
        );
    }

    /**
     * Chemin écriture : CSRF obligatoire, exécution read/write committée.
     */
    private function runWrite(Request $request, string $database, string $sql): Response
    {
        if (!Csrf::isValid($request->post('_csrf'))) {
            return $this->renderConsole($database, $sql, error: 'Jeton de sécurité invalide.', writeMode: true);
        }

        try {
            [$headers, $rows, $count, $affected, $elapsedMs] = $this->executeWrite($database, $sql);
        } catch (Throwable $e) {
            return $this->renderConsole($database, $sql, error: $e->getMessage(), writeMode: true);
        }

        return $this->renderConsole(
            $database, $sql,
            headers: $headers, rows: $rows, count: $count,
            elapsedMs: $elapsedMs, writeMode: true, affected: $affected,
        );
    }

    /**
     * Exécute la requête dans une transaction read-only, puis rollback.
     *
     * @return array{0: list<string>, 1: list<array<string, mixed>>, 2: float} headers, rows, durée (ms)
     */
    private function executeReadOnly(string $database, string $sql, int $limit): array
    {
        $pdo = $this->db->connect($database);

        $start = microtime(true);
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET TRANSACTION READ ONLY');
            $stmt = $pdo->query($sql);
            $rows = $stmt !== false ? $stmt->fetchAll() : [];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        $elapsedMs = (microtime(true) - $start) * 1000;

        $headers = $rows !== [] ? array_keys($rows[0]) : [];
        $rows    = array_slice($rows, 0, $limit);

        return [$headers, $rows, $elapsedMs];
    }

    /**
     * Exécute la requête en read/write et commit. Renvoie soit un jeu de résultats
     * (SELECT / RETURNING), soit le nombre de lignes affectées.
     *
     * @return array{0:list<string>, 1:list<array<string,mixed>>, 2:?int, 3:?int, 4:float}
     *         headers, rows, count, affected, durée (ms)
     */
    private function executeWrite(string $database, string $sql): array
    {
        $pdo = $this->db->connect($database);

        $start = microtime(true);
        $pdo->beginTransaction();
        try {
            $stmt        = $pdo->query($sql);
            $isResultSet = $stmt !== false && $stmt->columnCount() > 0;
            $rows        = $isResultSet ? $stmt->fetchAll() : [];
            $affected    = $isResultSet ? null : ($stmt !== false ? $stmt->rowCount() : 0);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $elapsedMs = (microtime(true) - $start) * 1000;

        $headers = $rows !== [] ? array_keys($rows[0]) : [];
        $count   = $rows !== [] ? count($rows) : null;
        $rows    = array_slice($rows, 0, self::MAX_ROWS);

        return [$headers, $rows, $count, $affected, $elapsedMs];
    }

    /**
     * @param list<string>               $headers
     * @param list<array<string, mixed>> $rows
     */
    private function renderConsole(
        string $database,
        string $sql,
        ?string $error = null,
        array $headers = [],
        array $rows = [],
        ?int $count = null,
        ?float $elapsedMs = null,
        bool $writeMode = false,
        ?int $affected = null,
    ): Response {
        return $this->view->render('query/console', $this->withNav([
            'title'     => 'Console SQL — ' . $database,
            'database'  => $database,
            'sql'       => $sql,
            'error'     => $error,
            'headers'   => $headers,
            'rows'      => $rows,
            'count'     => $count,
            'maxRows'   => self::MAX_ROWS,
            'elapsedMs' => $elapsedMs,
            'writeMode' => $writeMode,
            'affected'  => $affected,
        ], $database));
    }
}

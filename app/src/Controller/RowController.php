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
use App\Service\PostgresWriter;
use Throwable;

/**
 * Écriture d'une ligne : ajout (INSERT), édition (UPDATE), suppression (DELETE).
 *
 * Garde-fous : CSRF sur tout POST, requêtes préparées, ciblage par clé primaire
 * (édition/suppression refusées sans PK ou sur une vue), PRG + message flash.
 */
final class RowController extends Controller
{
    private PostgresWriter $writer;

    public function __construct(Database $db, View $view)
    {
        parent::__construct($db, $view);
        $this->writer = new PostgresWriter($db);
    }

    /**
     * Formulaire d'ajout.
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if ($this->inspector->tableType($database, $schema, $table) !== 'table') {
            Session::flash('error', 'Ajout impossible : objet introuvable ou non modifiable (vue).');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        }

        return $this->renderForm($database, $schema, $table, 'create', [], []);
    }

    /**
     * Traitement de l'ajout.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($bad = $this->guardCsrf($request, $database, $schema, $table)) !== null) {
            return $bad;
        }

        $columns = $this->inspector->columns($database, $schema, $table);
        $values  = $this->valuesFromPost($request->allPost(), $columns, [], true);

        try {
            [$sql, $bind] = $this->writer->buildInsert($schema, $table, $values);
            $this->db->execute($sql, $bind, $database);
            Session::setLastSql($this->interpolate($sql, $bind));
            Session::flash('success', 'Ligne ajoutée.');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        } catch (Throwable $e) {
            Session::flash('error', 'Ajout impossible : ' . $e->getMessage());

            return Response::redirect(sprintf(
                '/db/%s/table/%s/%s/row/new',
                rawurlencode($database),
                rawurlencode($schema),
                rawurlencode($table),
            ));
        }
    }

    /**
     * Formulaire d'édition (clé primaire en query string : ?pk[col]=valeur).
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        $pkCols = $this->inspector->primaryKey($database, $schema, $table);
        if ($pkCols === []) {
            Session::flash('error', 'Édition impossible : la table n’a pas de clé primaire.');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        }

        $pk  = $this->keyMap($pkCols, $this->arr($request->allQuery(), 'pk'));
        $row = $this->fetchRow($database, $schema, $table, $pk);

        if (count($pk) !== count($pkCols) || $row === null) {
            Session::flash('error', 'Ligne introuvable.');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        }

        $columns = $this->inspector->columns($database, $schema, $table);

        return $this->renderForm($database, $schema, $table, 'edit', $columns, $row, $pk);
    }

    /**
     * Traitement de l'édition.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($bad = $this->guardCsrf($request, $database, $schema, $table)) !== null) {
            return $bad;
        }

        $pkCols = $this->inspector->primaryKey($database, $schema, $table);
        $pk     = $this->keyMap($pkCols, $this->arr($request->allPost(), 'pk'));

        if ($pkCols === [] || count($pk) !== count($pkCols)) {
            Session::flash('error', 'Édition impossible : clé primaire manquante.');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        }

        $columns = $this->inspector->columns($database, $schema, $table);
        $values  = $this->valuesFromPost($request->allPost(), $columns, $pkCols, false);

        try {
            [$sql, $bind] = $this->writer->buildUpdate($schema, $table, $values, $pk);
            $this->db->execute($sql, $bind, $database);
            Session::setLastSql($this->interpolate($sql, $bind));
            Session::flash('success', 'Ligne modifiée.');
        } catch (Throwable $e) {
            Session::flash('error', 'Modification impossible : ' . $e->getMessage());
        }

        return Response::redirect($this->dataUrl($database, $schema, $table));
    }

    /**
     * Suppression d'une ligne (POST + CSRF + confirmation côté client).
     *
     * @param array<string, string> $params
     */
    public function delete(Request $request, array $params): Response
    {
        [$database, $schema, $table] = $this->target($params);

        if (($bad = $this->guardCsrf($request, $database, $schema, $table)) !== null) {
            return $bad;
        }

        $pkCols = $this->inspector->primaryKey($database, $schema, $table);
        $pk     = $this->keyMap($pkCols, $this->arr($request->allPost(), 'pk'));

        if ($pkCols === [] || count($pk) !== count($pkCols)) {
            Session::flash('error', 'Suppression impossible : clé primaire manquante.');

            return Response::redirect($this->dataUrl($database, $schema, $table));
        }

        try {
            [$sql, $bind] = $this->writer->buildDelete($schema, $table, $pk);
            $affected = $this->db->execute($sql, $bind, $database);
            Session::setLastSql($this->interpolate($sql, $bind));
            Session::flash('success', $affected > 0 ? 'Ligne supprimée.' : 'Aucune ligne supprimée.');
        } catch (Throwable $e) {
            Session::flash('error', 'Suppression impossible : ' . $e->getMessage());
        }

        return Response::redirect($this->dataUrl($database, $schema, $table));
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * @param array<string, string> $params
     * @return array{0:string, 1:string, 2:string}
     */
    private function target(array $params): array
    {
        return [$params['db'], $params['schema'], $params['table']];
    }

    private function dataUrl(string $database, string $schema, string $table): string
    {
        return sprintf(
            '/db/%s/table/%s/%s/data',
            rawurlencode($database),
            rawurlencode($schema),
            rawurlencode($table),
        );
    }

    /**
     * Vérifie le jeton CSRF ; renvoie une Response de redirection si invalide, sinon null.
     */
    private function guardCsrf(Request $request, string $database, string $schema, string $table): ?Response
    {
        if (Csrf::isValid($request->post('_csrf'))) {
            return null;
        }

        Session::flash('error', 'Jeton de sécurité invalide. Réessaie.');

        return Response::redirect($this->dataUrl($database, $schema, $table));
    }

    /**
     * Extrait un sous-tableau associatif d'une source (ex. $_POST['pk']).
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function arr(array $source, string $key): array
    {
        $value = $source[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Restreint $source aux colonnes de la clé donnée (sécurité du WHERE).
     *
     * @param list<string>         $keyCols
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function keyMap(array $keyCols, array $source): array
    {
        $map = [];
        foreach ($keyCols as $col) {
            if (array_key_exists($col, $source)) {
                $map[$col] = (string) $source[$col];
            }
        }

        return $map;
    }

    /**
     * Construit le tableau colonne => valeur à écrire à partir du POST.
     *
     * @param array<string, mixed>                                            $post
     * @param list<array{name:string, type:string, nullable:bool, default:?string}> $columns
     * @param list<string>                                                    $exclude colonnes à ignorer (ex. PK en update)
     * @return array<string, mixed>
     */
    private function valuesFromPost(array $post, array $columns, array $exclude, bool $allowDefault): array
    {
        $fields   = $this->arr($post, 'fields');
        $nulls    = $this->arr($post, 'null');
        $defaults = $this->arr($post, 'default');

        $values = [];
        foreach ($columns as $col) {
            $name = $col['name'];
            if (in_array($name, $exclude, true)) {
                continue;
            }
            if ($allowDefault && isset($defaults[$name])) {
                continue; // laisser jouer la valeur par défaut / SERIAL
            }
            if (isset($nulls[$name])) {
                $values[$name] = null;
                continue;
            }
            $value = array_key_exists($name, $fields) ? (string) $fields[$name] : '';

            // À l'ajout : un champ laissé vide dont la colonne a un défaut → on l'omet
            // pour laisser jouer ce défaut (ex. created_at = now(), SERIAL).
            if ($allowDefault && $value === '' && $col['default'] !== null) {
                continue;
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * Rend une requête lisible en injectant les valeurs liées (pour affichage seulement).
     *
     * @param array<string, mixed> $params
     */
    private function interpolate(string $sql, array $params): string
    {
        return (string) preg_replace_callback('/:(\w+)/', static function (array $m) use ($params): string {
            if (!array_key_exists($m[1], $params)) {
                return $m[0];
            }
            $value = $params[$m[1]];
            if ($value === null) {
                return 'NULL';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            return "'" . str_replace("'", "''", (string) $value) . "'";
        }, $sql);
    }

    /**
     * Lit une ligne ciblée par sa clé primaire.
     *
     * @param array<string, mixed> $pk
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $database, string $schema, string $table, array $pk): ?array
    {
        if ($pk === []) {
            return null;
        }

        $ref    = $this->db->quoteIdentifier($schema) . '.' . $this->db->quoteIdentifier($table);
        $conds  = [];
        $bind   = [];
        $i      = 0;
        foreach ($pk as $col => $val) {
            $ph      = 'k' . $i++;
            $conds[] = $this->db->quoteIdentifier((string) $col) . ' = :' . $ph;
            $bind[$ph] = $val;
        }

        $sql = 'SELECT * FROM ' . $ref . ' WHERE ' . implode(' AND ', $conds) . ' LIMIT 1';

        return $this->db->fetchOne($sql, $bind, $database);
    }

    /**
     * @param list<array{name:string, type:string, nullable:bool, default:?string}> $columns
     * @param array<string, mixed> $values
     * @param array<string, mixed> $pk
     */
    private function renderForm(
        string $database,
        string $schema,
        string $table,
        string $mode,
        array $columns,
        array $values,
        array $pk = [],
    ): Response {
        if ($columns === []) {
            $columns = $this->inspector->columns($database, $schema, $table);
        }

        return $this->view->render('table/row_form', $this->withNav([
            'title'      => ($mode === 'create' ? 'Ajouter' : 'Éditer') . ' — ' . $schema . '.' . $table,
            'database'   => $database,
            'schema'     => $schema,
            'table'      => $table,
            'mode'       => $mode,
            'columns'    => $columns,
            'values'     => $values,
            'primaryKey' => $this->inspector->primaryKey($database, $schema, $table),
            'pk'         => $pk,
        ], $database));
    }
}

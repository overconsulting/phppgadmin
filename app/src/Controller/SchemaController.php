<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Affiche les schémas d'une base et, pour chacun, ses tables et vues.
 */
final class SchemaController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function tables(Request $request, array $params): Response
    {
        $database = $params['db'];

        $schemas = [];
        foreach ($this->inspector->schemas($database) as $schema) {
            $schemas[$schema] = $this->inspector->tables($database, $schema);
        }

        return $this->view->render('schema/tables', $this->withNav([
            'title'    => $database,
            'database' => $database,
            'schemas'  => $schemas,
        ], $database));
    }
}

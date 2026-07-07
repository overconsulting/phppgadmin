<?php

declare(strict_types=1);

use App\Controller\QueryController;
use App\Controller\RowController;
use App\Controller\SchemaController;
use App\Controller\ServerController;
use App\Controller\StructureController;
use App\Controller\TableController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

require dirname(__DIR__) . '/vendor/autoload.php';

Session::start();

$db      = new Database();
$view    = new View();
$request = new Request();

// Porte d'authentification : tant qu'on n'est pas connecté, rien n'atteint la base.
$authResponse = Auth::guard($request, $view);
if ($authResponse !== null) {
    $authResponse->send();

    return;
}

$router = new Router($db, $view);

// --- Routes (voir doc/SPEC.md) ---
$router->add('GET',  '/',                                     [ServerController::class, 'databases']);
$router->add('POST', '/create-database',                      [ServerController::class, 'createDatabase']);
$router->add('GET',  '/db/{db}',                              [SchemaController::class, 'tables']);
$router->add('GET',  '/db/{db}/operations',                   [ServerController::class, 'operations']);
$router->add('POST', '/db/{db}/rename',                       [ServerController::class, 'renameDatabase']);
$router->add('POST', '/db/{db}/drop',                         [ServerController::class, 'dropDatabase']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}',           [TableController::class,  'data']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/data',      [TableController::class,  'data']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/structure', [TableController::class,  'structure']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/export',    [TableController::class,  'exportCsv']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/row/new',    [RowController::class,    'create']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/row',        [RowController::class,    'store']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/row/edit',   [RowController::class,    'edit']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/row/update', [RowController::class,    'update']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/row/delete', [RowController::class,    'delete']);
// --- DDL (structure) ---
$router->add('POST', '/db/{db}/tables/drop',                        [StructureController::class, 'dropTables']);
$router->add('GET',  '/db/{db}/create-table',                       [StructureController::class, 'createTableForm']);
$router->add('POST', '/db/{db}/create-table',                       [StructureController::class, 'createTable']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/column/new',    [StructureController::class, 'addColumnForm']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/column',        [StructureController::class, 'addColumn']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/column/edit',   [StructureController::class, 'editColumnForm']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/column/update', [StructureController::class, 'editColumn']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/column/drop',   [StructureController::class, 'dropColumn']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/action',        [StructureController::class, 'tableActions']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/rename',        [StructureController::class, 'renameTable']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/drop',          [StructureController::class, 'dropTable']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/index/new',     [StructureController::class, 'addIndexForm']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/index',         [StructureController::class, 'addIndex']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/index/drop',    [StructureController::class, 'dropIndex']);
$router->add('GET',  '/db/{db}/table/{schema}/{table}/fk/new',        [StructureController::class, 'addFkForm']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/fk',            [StructureController::class, 'addFk']);
$router->add('POST', '/db/{db}/table/{schema}/{table}/fk/drop',       [StructureController::class, 'dropFk']);
$router->add('GET',  '/db/{db}/query',                          [QueryController::class,  'show']);
$router->add('POST', '/db/{db}/query',                          [QueryController::class,  'run']);
$router->add('POST', '/db/{db}/query/export',                   [QueryController::class,  'export']);

try {
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    $response = $view->render('error', [
        'title'   => 'Erreur',
        'message' => $e->getMessage(),
    ], 500);
}

$response->send();

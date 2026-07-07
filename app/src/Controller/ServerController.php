<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
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
 * Accueil : liste des bases du serveur, et opérations au niveau base
 * (création, renommage, suppression).
 */
final class ServerController extends Controller
{
    private PostgresDdl $ddl;

    public function __construct(Database $db, View $view)
    {
        parent::__construct($db, $view);
        $this->ddl = new PostgresDdl($db);
    }

    /**
     * @param array<string, string> $params
     */
    public function databases(Request $request, array $params): Response
    {
        $databases = $this->inspector->databases();

        return $this->view->render('server/databases', $this->withNav([
            'title'     => 'Bases de données',
            'databases' => $databases,
        ]));
    }

    /**
     * Page « Opérations » d'une base : renommer / supprimer.
     *
     * @param array<string, string> $params
     */
    public function operations(Request $request, array $params): Response
    {
        $database = $params['db'];

        return $this->view->render('server/operations', $this->withNav([
            'title'    => 'Opérations — ' . $database,
            'database' => $database,
        ], $database));
    }

    /**
     * Crée une base de données. CREATE DATABASE ne peut PAS tourner dans une
     * transaction : on l'exécute directement (PDO est en autocommit par défaut).
     *
     * @param array<string, string> $params
     */
    public function createDatabase(Request $request, array $params): Response
    {
        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect('/');
        }

        $name = trim((string) $request->post('name', ''));
        if ($name === '') {
            Session::flash('error', 'Nom de la base requis.');

            return Response::redirect('/');
        }

        try {
            $sql = $this->ddl->createDatabase($name);
            $this->db->connect()->exec($sql);   // hors transaction
            Session::setLastSql($sql);
            Session::flash('success', 'Base « ' . $name . ' » créée.');
        } catch (Throwable $e) {
            Session::flash('error', 'Création impossible : ' . $e->getMessage());
        }

        return Response::redirect('/');
    }

    /**
     * Renomme une base. ALTER DATABASE … RENAME ne peut pas tourner en transaction,
     * ni pendant qu'on est connecté à la base cible → on passe par une base de maintenance.
     *
     * @param array<string, string> $params
     */
    public function renameDatabase(Request $request, array $params): Response
    {
        $database = $params['db'];
        $opsUrl   = '/db/' . rawurlencode($database) . '/operations';

        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($opsUrl);
        }

        $newName = trim((string) $request->post('name', ''));
        if ($newName === '') {
            Session::flash('error', 'Nouveau nom de base requis.');

            return Response::redirect($opsUrl);
        }
        if ($newName === $database) {
            return Response::redirect($opsUrl);   // rien à faire
        }

        try {
            $sql = $this->ddl->renameDatabase($database, $newName);
            $this->adminExec($database, $sql);
            Session::setLastSql($sql);
            Session::flash('success', 'Base renommée en « ' . $newName . ' ».');

            return Response::redirect('/db/' . rawurlencode($newName) . '/operations');
        } catch (Throwable $e) {
            Session::flash('error', 'Renommage impossible : ' . $e->getMessage());

            return Response::redirect($opsUrl);
        }
    }

    /**
     * Supprime une base. DROP DATABASE ne peut pas tourner en transaction,
     * ni pendant qu'on est connecté à la base cible → base de maintenance.
     *
     * @param array<string, string> $params
     */
    public function dropDatabase(Request $request, array $params): Response
    {
        $database = $params['db'];
        $opsUrl   = '/db/' . rawurlencode($database) . '/operations';

        if (!Csrf::isValid($request->post('_csrf'))) {
            Session::flash('error', 'Jeton de sécurité invalide.');

            return Response::redirect($opsUrl);
        }

        try {
            $sql = $this->ddl->dropDatabase($database);
            $this->adminExec($database, $sql);
            Session::setLastSql($sql);
            Session::flash('success', 'Base « ' . $database . ' » supprimée.');

            return Response::redirect('/');
        } catch (Throwable $e) {
            Session::flash('error', 'Suppression impossible : ' . $e->getMessage());

            return Response::redirect($opsUrl);
        }
    }

    /**
     * Exécute une commande d'administration de base (RENAME/DROP) HORS transaction,
     * en se connectant à une base DIFFÉRENTE de la cible.
     */
    private function adminExec(string $targetDb, string $sql): void
    {
        $this->db->connect($this->maintenanceDb($targetDb))->exec($sql);
    }

    /**
     * Choisit une base à laquelle se connecter pour opérer sur $target
     * (on ne peut ni renommer ni supprimer la base à laquelle on est connecté).
     */
    private function maintenanceDb(string $target): string
    {
        $candidates = [Config::postgres()['default_db'], 'postgres', 'template1'];

        foreach ($candidates as $db) {
            if ($db !== '' && $db !== $target) {
                return $db;
            }
        }

        return 'template1';
    }
}

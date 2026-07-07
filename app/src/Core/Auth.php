<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Porte d'authentification de l'interface.
 *
 * S'exécute dans le front controller AVANT le routeur : tant que l'utilisateur n'est pas
 * connecté, aucune requête n'atteint la base. Les identifiants viennent des variables
 * d'environnement (APP_USER / APP_PASSWORD), distinctes de celles de la base (PG_*).
 *
 * Trois cas selon la configuration (voir Config::auth()) :
 *  - AUTH_ENABLED=false           → porte ouverte (usage local sans friction).
 *  - AUTH_ENABLED=true, mdp vide  → fermeture de sécurité : accès refusé tant qu'APP_PASSWORD n'est pas défini.
 *  - AUTH_ENABLED=true, mdp défini → formulaire de login, session après succès.
 */
final class Auth
{
    private const SESSION_KEY = '_auth';

    /**
     * Intercepte la requête si nécessaire. Renvoie une Response (login, redirection, erreur)
     * à envoyer telle quelle, ou null pour laisser passer vers le routeur.
     */
    public static function guard(Request $request, View $view): ?Response
    {
        $cfg = Config::auth();

        // Mode local : pas de porte.
        if (!$cfg['enabled']) {
            return null;
        }

        // Déconnexion (fonctionne même si la config est incomplète).
        if ($request->path === '/logout') {
            self::forget();

            return Response::redirect('/login');
        }

        // Fermeture de sécurité : auth demandée mais aucun mot de passe fourni.
        if ($cfg['password'] === '') {
            return self::page($view, ['configError' => true], 500);
        }

        // Déjà connecté : on laisse passer (et on renvoie /login vers l'accueil).
        if (!empty($_SESSION[self::SESSION_KEY])) {
            return $request->path === '/login'
                ? Response::redirect('/')
                : null;
        }

        // Tentative de connexion.
        if ($request->path === '/login' && $request->isPost()) {
            return self::attempt($request, $view, $cfg);
        }

        // Non connecté : on affiche le formulaire (200 sur /login, 401 ailleurs).
        $status = $request->path === '/login' ? 200 : 401;

        return self::page($view, ['user' => $cfg['user']], $status);
    }

    /**
     * @param array{enabled:bool, user:string, password:string} $cfg
     */
    private static function attempt(Request $request, View $view, array $cfg): Response
    {
        if (!Csrf::isValid($request->post('_csrf'))) {
            return self::page($view, ['user' => $cfg['user'], 'error' => 'Session expirée, réessayez.'], 400);
        }

        $okUser = hash_equals($cfg['user'], (string) $request->post('user', ''));
        $okPass = hash_equals($cfg['password'], (string) $request->post('password', ''));

        if (!$okUser || !$okPass) {
            return self::page($view, ['user' => $cfg['user'], 'error' => 'Identifiants invalides.'], 401);
        }

        // Succès : on régénère l'ID de session (anti-fixation) et on marque la connexion.
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = true;

        return Response::redirect('/');
    }

    /**
     * Vrai si une session authentifiée est active (utilisé par le layout pour le lien Déconnexion).
     */
    public static function check(): bool
    {
        return Config::auth()['enabled'] && !empty($_SESSION[self::SESSION_KEY]);
    }

    private static function forget(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function page(View $view, array $data, int $status): Response
    {
        $html = $view->renderPartial('auth/login', ['csrf' => Csrf::token(), ...$data]);

        return Response::html($html, $status);
    }
}

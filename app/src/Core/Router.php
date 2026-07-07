<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : associe (méthode HTTP + motif de chemin) à un couple [Contrôleur, action].
 *
 * Les motifs acceptent des paramètres nommés entre accolades, ex. `/db/{db}/table/{schema}/{table}`.
 * Les contrôleurs sont instanciés avec (Database, View) et l'action reçoit (Request, array $params).
 */
final class Router
{
    /** @var list<array{method:string, regex:string, vars:list<string>, handler:array{0:class-string, 1:string}}> */
    private array $routes = [];

    public function __construct(
        private Database $db,
        private View $view,
    ) {
    }

    /**
     * @param array{0:class-string, 1:string} $handler
     */
    public function add(string $method, string $pattern, array $handler): void
    {
        $vars = [];
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}/i',
            static function (array $m) use (&$vars): string {
                $vars[] = $m[1];

                return '([^/]+)';
            },
            $pattern,
        );

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'vars'    => $vars,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($route['vars'] as $i => $name) {
                $params[$name] = rawurldecode($matches[$i + 1]);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class($this->db, $this->view);

            return $controller->$action($request, $params);
        }

        return $this->view->render('error', [
            'title'   => 'Introuvable',
            'message' => sprintf('Aucune route pour %s %s', $request->method, $request->path),
        ], 404);
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Moteur de rendu minimal : inclut un template PHP et l'insère dans le layout commun.
 */
final class View
{
    private string $templateDir;

    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = rtrim($templateDir ?? \dirname(__DIR__, 2) . '/templates', '/');
    }

    /**
     * Rend un template dans le layout et renvoie une Response HTML.
     *
     * @param array<string, mixed> $data Données passées au template (et au layout).
     */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $content = $this->renderPartial($template, $data);

        $html = $this->renderPartial('layout', [...$data, 'content' => $content]);

        return Response::html($html, $status);
    }

    /**
     * Rend un template seul et renvoie le HTML (sans layout).
     *
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Template introuvable : %s', $file));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}

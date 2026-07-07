<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Représente une réponse HTTP (statut, en-têtes, corps).
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    /**
     * Redirection HTTP (PRG après écriture).
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * Réponse de téléchargement (fichier en pièce jointe).
     */
    public static function attachment(
        string $body,
        string $filename,
        string $contentType = 'text/csv; charset=utf-8',
    ): self {
        return new self($body, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}

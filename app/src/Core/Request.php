<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Représente la requête HTTP entrante (méthode, chemin, paramètres).
 */
final class Request
{
    public readonly string $method;
    public readonly string $path;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path   = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }

    /**
     * Paramètre GET.
     */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Paramètre POST.
     */
    public function post(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Tous les champs POST (utile pour les formulaires à colonnes dynamiques).
     *
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return $_POST;
    }

    /**
     * Tous les paramètres GET.
     *
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $_GET;
    }
}

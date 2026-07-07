<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Échappe une valeur pour un affichage HTML sûr.
     */
    function e(int|float|string|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

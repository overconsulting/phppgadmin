<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sérialisation CSV de lignes tabulaires.
 */
final class Csv
{
    /**
     * Construit un CSV (en-têtes + lignes). Les valeurs non scalaires sont encodées en JSON.
     *
     * @param list<string>               $headers
     * @param list<array<string, mixed>> $rows
     */
    public static function fromRows(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        // PHP 8.4 : $escape doit être fourni explicitement ("" = comportement RFC 4180,
        // les guillemets sont échappés par doublement via l'enclosure).
        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $v = $row[$h] ?? null;
                $line[] = $v === null ? '' : (is_scalar($v) ? (string) $v : (string) json_encode($v));
            }
            fputcsv($handle, $line, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}

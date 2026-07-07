<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csv;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    public function testHeadersAndRows(): void
    {
        $csv = Csv::fromRows(['id', 'email'], [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
        ]);

        $lines = array_values(array_filter(explode("\n", trim($csv)), static fn ($l) => $l !== ''));
        $this->assertSame('id,email', $lines[0]);
        $this->assertSame('1,alice@example.com', $lines[1]);
        $this->assertSame('2,bob@example.com', $lines[2]);
    }

    public function testNullBecomesEmptyAndCommaIsQuoted(): void
    {
        $csv = Csv::fromRows(['name', 'note'], [
            ['name' => 'Bob, Jr', 'note' => null],
        ]);

        // La virgule force l'encadrement par des guillemets ; null devient vide.
        $this->assertStringContainsString('"Bob, Jr",', $csv);
    }

    public function testNonScalarIsJsonEncoded(): void
    {
        $csv = Csv::fromRows(['data'], [
            ['data' => ['a' => 1]],
        ]);

        $this->assertStringContainsString('{""a"":1}', $csv);
    }
}

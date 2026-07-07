<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Service\PostgresInspector;
use PHPUnit\Framework\TestCase;

/**
 * Teste la génération de la requête SELECT (selectSql) : tri, opérateurs de filtre,
 * et échappement des littéraux. Ces méthodes ne se connectent pas à la base.
 */
final class PostgresInspectorSqlTest extends TestCase
{
    private PostgresInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new PostgresInspector(new Database());
    }

    public function testPlainSelect(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0);
        $this->assertSame('SELECT * FROM "public"."customers" LIMIT 50 OFFSET 0', $sql);
    }

    public function testOrderByDescending(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, 'email', 'desc');
        $this->assertStringContainsString('ORDER BY "email" DESC', $sql);
    }

    public function testInvalidDirectionFallsBackToAsc(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, 'email', 'sideways');
        $this->assertStringContainsString('ORDER BY "email" ASC', $sql);
    }

    public function testOperatorContains(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'email', 'bob', 'contains');
        $this->assertStringContainsString('WHERE "email"::text ILIKE \'%bob%\'', $sql);
    }

    public function testOperatorEqualsUsesEqualSign(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'id', '5', 'eq');
        $this->assertStringContainsString('WHERE "id"::text = \'5\'', $sql);
    }

    public function testOperatorStartsWith(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'email', 'a', 'starts');
        $this->assertStringContainsString('ILIKE \'a%\'', $sql);
    }

    public function testOperatorEndsWith(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'email', 'com', 'ends');
        $this->assertStringContainsString('ILIKE \'%com\'', $sql);
    }

    public function testUnknownOperatorFallsBackToContains(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'email', 'bob', 'bogus');
        $this->assertStringContainsString('ILIKE \'%bob%\'', $sql);
    }

    public function testLiteralValueIsEscaped(): void
    {
        // Une apostrophe dans la valeur doit être doublée (échappement SQL standard).
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', 'name', "o'brien", 'contains');
        $this->assertStringContainsString("ILIKE '%o''brien%'", $sql);
    }

    public function testNoFilterProducesNoWhere(): void
    {
        $sql = $this->inspector->selectSql('public', 'customers', 50, 0, null, 'asc', null, null, 'contains');
        $this->assertStringNotContainsString('WHERE', $sql);
    }
}

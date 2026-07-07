<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\SqlReadGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlReadGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedQueries(): iterable
    {
        yield 'select simple'         => ['SELECT 1'];
        yield 'select minuscules'     => ['select * from customers'];
        yield 'espaces en tête'       => ['   SELECT id FROM t'];
        yield 'point-virgule final'   => ['SELECT 1;'];
        yield 'with (CTE)'            => ['WITH a AS (SELECT 1) SELECT * FROM a'];
        yield 'explain'               => ['EXPLAIN SELECT 1'];
        yield 'explain analyze select' => ['EXPLAIN ANALYZE SELECT 1'];
        yield 'retour ligne en tête'  => ["\n\tSELECT 1"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedQueries(): iterable
    {
        yield 'update'        => ['UPDATE customers SET email = 1'];
        yield 'insert'        => ['INSERT INTO customers (email) VALUES (1)'];
        yield 'delete'        => ['DELETE FROM customers'];
        yield 'drop'          => ['DROP TABLE customers'];
        yield 'truncate'      => ['TRUNCATE customers'];
        yield 'create'        => ['CREATE TABLE t (id int)'];
        yield 'alter'         => ['ALTER TABLE t ADD COLUMN x int'];
        yield 'requête vide'  => [''];
        yield 'multi-requête' => ['SELECT 1; DROP TABLE customers'];
        yield 'multi avec ; final' => ['SELECT 1; DELETE FROM t;'];
    }

    #[DataProvider('allowedQueries')]
    public function testAllows(string $sql): void
    {
        $this->assertTrue(SqlReadGuard::isReadOnly($sql));
    }

    #[DataProvider('refusedQueries')]
    public function testRefuses(string $sql): void
    {
        $this->assertFalse(SqlReadGuard::isReadOnly($sql));
    }

    /**
     * Cas documenté de la défense en profondeur : « EXPLAIN ANALYZE UPDATE … » passe
     * le garde de préfixe (premier mot = EXPLAIN), mais est bloqué par la transaction
     * READ ONLY côté PostgreSQL (voir le test d'intégration ReadOnlyTransactionTest).
     */
    public function testExplainAnalyzeWriteSlipsPastPrefixGuardButTxnCatchesIt(): void
    {
        $this->assertTrue(SqlReadGuard::isReadOnly('EXPLAIN ANALYZE UPDATE customers SET email = 1'));
    }
}

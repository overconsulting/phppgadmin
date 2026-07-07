<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Service\PostgresWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostgresWriterTest extends TestCase
{
    private PostgresWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new PostgresWriter(new Database());
    }

    public function testBuildInsert(): void
    {
        [$sql, $params] = $this->writer->buildInsert('public', 'customers', [
            'email'     => 'a@b.c',
            'full_name' => 'Alice',
        ]);

        $this->assertSame(
            'INSERT INTO "public"."customers" ("email", "full_name") VALUES (:v0, :v1)',
            $sql,
        );
        $this->assertSame(['v0' => 'a@b.c', 'v1' => 'Alice'], $params);
    }

    public function testBuildInsertWithNoValuesUsesDefaultValues(): void
    {
        [$sql, $params] = $this->writer->buildInsert('public', 'customers', []);

        $this->assertSame('INSERT INTO "public"."customers" DEFAULT VALUES', $sql);
        $this->assertSame([], $params);
    }

    public function testBuildUpdate(): void
    {
        [$sql, $params] = $this->writer->buildUpdate('public', 'customers', ['full_name' => 'Bob'], ['id' => 5]);

        $this->assertSame('UPDATE "public"."customers" SET "full_name" = :set0 WHERE "id" = :pk0', $sql);
        $this->assertSame(['set0' => 'Bob', 'pk0' => 5], $params);
    }

    public function testBuildUpdateWithCompositeKey(): void
    {
        [$sql, $params] = $this->writer->buildUpdate('s', 't', ['x' => 1], ['a' => 'A', 'b' => 'B']);

        $this->assertSame('UPDATE "s"."t" SET "x" = :set0 WHERE "a" = :pk0 AND "b" = :pk1', $sql);
        $this->assertSame(['set0' => 1, 'pk0' => 'A', 'pk1' => 'B'], $params);
    }

    public function testBuildUpdateHandlesNullValue(): void
    {
        [$sql, $params] = $this->writer->buildUpdate('public', 'customers', ['note' => null], ['id' => 1]);

        $this->assertStringContainsString('SET "note" = :set0', $sql);
        $this->assertNull($params['set0']);
    }

    public function testBuildDelete(): void
    {
        [$sql, $params] = $this->writer->buildDelete('public', 'customers', ['id' => 7]);

        $this->assertSame('DELETE FROM "public"."customers" WHERE "id" = :pk0', $sql);
        $this->assertSame(['pk0' => 7], $params);
    }

    public function testUpdateWithoutKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->writer->buildUpdate('public', 'customers', ['x' => 1], []);
    }

    public function testUpdateWithoutValuesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->writer->buildUpdate('public', 'customers', [], ['id' => 1]);
    }

    public function testDeleteWithoutKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->writer->buildDelete('public', 'customers', []);
    }

    public function testIdentifiersAreQuotedAgainstInjection(): void
    {
        [$sql] = $this->writer->buildDelete('public', 'weird"name', ['id' => 1]);
        $this->assertStringContainsString('"weird""name"', $sql);
    }
}

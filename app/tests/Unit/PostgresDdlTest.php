<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Service\PostgresDdl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostgresDdlTest extends TestCase
{
    private PostgresDdl $ddl;

    protected function setUp(): void
    {
        $this->ddl = new PostgresDdl(new Database());
    }

    public function testAddColumnNullable(): void
    {
        $sql = $this->ddl->addColumn('public', 'customers', 'note', 'text', true, null);
        $this->assertSame('ALTER TABLE "public"."customers" ADD COLUMN "note" text', $sql);
    }

    public function testAddColumnNotNullWithDefault(): void
    {
        $sql = $this->ddl->addColumn('public', 'customers', 'score', 'integer', false, '0');
        $this->assertSame('ALTER TABLE "public"."customers" ADD COLUMN "score" integer NOT NULL DEFAULT 0', $sql);
    }

    public function testDropColumn(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" DROP COLUMN "note"',
            $this->ddl->dropColumn('public', 'customers', 'note'),
        );
    }

    public function testRenameColumn(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" RENAME COLUMN "note" TO "comment"',
            $this->ddl->renameColumn('public', 'customers', 'note', 'comment'),
        );
    }

    public function testSetColumnType(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" ALTER COLUMN "score" TYPE bigint',
            $this->ddl->setColumnType('public', 'customers', 'score', 'bigint'),
        );
    }

    public function testSetNotNull(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" ALTER COLUMN "email" SET NOT NULL',
            $this->ddl->setNotNull('public', 'customers', 'email', true),
        );
        $this->assertSame(
            'ALTER TABLE "public"."customers" ALTER COLUMN "email" DROP NOT NULL',
            $this->ddl->setNotNull('public', 'customers', 'email', false),
        );
    }

    public function testSetAndDropDefault(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" ALTER COLUMN "created_at" SET DEFAULT now()',
            $this->ddl->setDefault('public', 'customers', 'created_at', 'now()'),
        );
        $this->assertSame(
            'ALTER TABLE "public"."customers" ALTER COLUMN "created_at" DROP DEFAULT',
            $this->ddl->setDefault('public', 'customers', 'created_at', null),
        );
    }

    public function testRenameTable(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."customers" RENAME TO "clients"',
            $this->ddl->renameTable('public', 'customers', 'clients'),
        );
    }

    public function testDropTable(): void
    {
        $this->assertSame('DROP TABLE "public"."customers"', $this->ddl->dropTable('public', 'customers'));
    }

    public function testCreateTableWithSinglePk(): void
    {
        $sql = $this->ddl->createTable('public', 'produits', [
            ['name' => 'id', 'type' => 'serial', 'nullable' => false, 'default' => null, 'pk' => true],
            ['name' => 'label', 'type' => 'text', 'nullable' => false, 'default' => null, 'pk' => false],
        ]);

        $this->assertSame(
            'CREATE TABLE "public"."produits" ("id" serial NOT NULL, "label" text NOT NULL, PRIMARY KEY ("id"))',
            $sql,
        );
    }

    public function testCreateTableWithCompositePk(): void
    {
        $sql = $this->ddl->createTable('public', 'liaison', [
            ['name' => 'a_id', 'type' => 'integer', 'nullable' => false, 'pk' => true],
            ['name' => 'b_id', 'type' => 'integer', 'nullable' => false, 'pk' => true],
        ]);

        $this->assertStringContainsString('PRIMARY KEY ("a_id", "b_id")', $sql);
    }

    public function testCreateTableRequiresAtLeastOneColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ddl->createTable('public', 'vide', []);
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ddl->addColumn('public', 'customers', 'x', 'int; DROP TABLE customers', true, null);
    }

    public function testDefaultWithSemicolonIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ddl->setDefault('public', 'customers', 'x', "1; DROP TABLE customers");
    }

    public function testIdentifierInjectionIsQuoted(): void
    {
        $sql = $this->ddl->dropColumn('public', 'weird"name', 'col');
        $this->assertStringContainsString('"weird""name"', $sql);
    }

    public function testCreateIndexSimple(): void
    {
        $this->assertSame(
            'CREATE INDEX ON "public"."customers" ("email")',
            $this->ddl->createIndex('public', 'customers', ['email'], false, null),
        );
    }

    public function testCreateIndexUniqueMultiColumnWithName(): void
    {
        $this->assertSame(
            'CREATE UNIQUE INDEX "idx_ab" ON "public"."t" ("a", "b")',
            $this->ddl->createIndex('public', 't', ['a', 'b'], true, 'idx_ab'),
        );
    }

    public function testCreateIndexRequiresColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ddl->createIndex('public', 't', [], false, null);
    }

    public function testDropIndex(): void
    {
        $this->assertSame(
            'DROP INDEX "public"."idx_customers_email"',
            $this->ddl->dropIndex('public', 'idx_customers_email'),
        );
    }

    public function testAddForeignKeyWithNameAndOnDelete(): void
    {
        $sql = $this->ddl->addForeignKey('public', 'orders', 'customer_id', 'public', 'customers', 'id', 'CASCADE', 'fk_orders_customer');
        $this->assertSame(
            'ALTER TABLE "public"."orders" ADD CONSTRAINT "fk_orders_customer" '
            . 'FOREIGN KEY ("customer_id") REFERENCES "public"."customers" ("id") ON DELETE CASCADE',
            $sql,
        );
    }

    public function testAddForeignKeyWithoutNameNorOnDelete(): void
    {
        $sql = $this->ddl->addForeignKey('public', 'orders', 'customer_id', 'public', 'customers', 'id', null, null);
        $this->assertSame(
            'ALTER TABLE "public"."orders" ADD FOREIGN KEY ("customer_id") REFERENCES "public"."customers" ("id")',
            $sql,
        );
    }

    public function testAddForeignKeyRejectsInvalidOnDelete(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ddl->addForeignKey('public', 'orders', 'customer_id', 'public', 'customers', 'id', 'EXPLODE', null);
    }

    public function testDropConstraint(): void
    {
        $this->assertSame(
            'ALTER TABLE "public"."orders" DROP CONSTRAINT "fk_orders_customer"',
            $this->ddl->dropConstraint('public', 'orders', 'fk_orders_customer'),
        );
    }
}

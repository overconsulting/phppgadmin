<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Service\PostgresDdl;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * DDL contre le vrai PostgreSQL, dans une transaction annulée en tearDown
 * (le DDL PostgreSQL est transactionnel) → le schéma n'est jamais modifié durablement.
 * Sauté si aucune base joignable.
 */
final class DdlTest extends TestCase
{
    private const T = 'phppgadmin_ddl_test';

    private Database $db;
    private PDO $pdo;
    private PostgresDdl $ddl;

    protected function setUp(): void
    {
        try {
            $this->db  = new Database();
            $this->pdo = $this->db->connect();
        } catch (Throwable $e) {
            $this->markTestSkipped('PostgreSQL non joignable : ' . $e->getMessage());
        }

        $this->ddl = new PostgresDdl($this->db);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testFullColumnLifecycle(): void
    {
        // CREATE TABLE
        $this->pdo->exec($this->ddl->createTable('public', self::T, [
            ['name' => 'id', 'type' => 'serial', 'nullable' => false, 'pk' => true],
            ['name' => 'label', 'type' => 'text', 'nullable' => false],
        ]));
        $this->assertTrue($this->tableExists(self::T));

        // ADD COLUMN
        $this->pdo->exec($this->ddl->addColumn('public', self::T, 'note', 'text', true, null));
        $this->assertTrue($this->columnExists(self::T, 'note'));

        // RENAME COLUMN
        $this->pdo->exec($this->ddl->renameColumn('public', self::T, 'note', 'comment'));
        $this->assertFalse($this->columnExists(self::T, 'note'));
        $this->assertTrue($this->columnExists(self::T, 'comment'));

        // ALTER TYPE
        $this->pdo->exec($this->ddl->setColumnType('public', self::T, 'label', 'varchar(100)'));
        $this->assertSame('character varying', $this->columnType(self::T, 'label'));

        // DROP COLUMN
        $this->pdo->exec($this->ddl->dropColumn('public', self::T, 'comment'));
        $this->assertFalse($this->columnExists(self::T, 'comment'));

        // DROP TABLE
        $this->pdo->exec($this->ddl->dropTable('public', self::T));
        $this->assertFalse($this->tableExists(self::T));
    }

    public function testForeignKeyAndIndexLifecycle(): void
    {
        $parent = 'phppgadmin_ddl_parent';
        $child  = 'phppgadmin_ddl_child';

        $this->pdo->exec($this->ddl->createTable('public', $parent, [
            ['name' => 'id', 'type' => 'serial', 'nullable' => false, 'pk' => true],
        ]));
        $this->pdo->exec($this->ddl->createTable('public', $child, [
            ['name' => 'id', 'type' => 'serial', 'nullable' => false, 'pk' => true],
            ['name' => 'parent_id', 'type' => 'integer', 'nullable' => true],
        ]));

        // ADD FOREIGN KEY
        $this->pdo->exec($this->ddl->addForeignKey('public', $child, 'parent_id', 'public', $parent, 'id', 'CASCADE', 'fk_test'));
        $this->assertTrue($this->constraintExists($child, 'fk_test'));

        // DROP FOREIGN KEY
        $this->pdo->exec($this->ddl->dropConstraint('public', $child, 'fk_test'));
        $this->assertFalse($this->constraintExists($child, 'fk_test'));

        // CREATE INDEX
        $this->pdo->exec($this->ddl->createIndex('public', $child, ['parent_id'], false, 'idx_test'));
        $this->assertTrue($this->indexExists($child, 'idx_test'));

        // DROP INDEX
        $this->pdo->exec($this->ddl->dropIndex('public', 'idx_test'));
        $this->assertFalse($this->indexExists($child, 'idx_test'));
    }

    private function constraintExists(string $table, string $name): bool
    {
        return $this->db->fetchOne(
            "SELECT 1 FROM information_schema.table_constraints
             WHERE table_schema = 'public' AND table_name = :t AND constraint_name = :n",
            ['t' => $table, 'n' => $name],
        ) !== null;
    }

    private function indexExists(string $table, string $name): bool
    {
        return $this->db->fetchOne(
            "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = :t AND indexname = :n",
            ['t' => $table, 'n' => $name],
        ) !== null;
    }

    private function tableExists(string $table): bool
    {
        return $this->db->fetchOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t",
            ['t' => $table],
        ) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->fetchOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t AND column_name = :c",
            ['t' => $table, 'c' => $column],
        ) !== null;
    }

    private function columnType(string $table, string $column): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT data_type FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t AND column_name = :c",
            ['t' => $table, 'c' => $column],
        );

        return $row['data_type'] ?? null;
    }
}

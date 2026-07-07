<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Service\PostgresWriter;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * CRUD de ligne contre le vrai PostgreSQL, entièrement dans une transaction annulée
 * en tearDown : INSERT → relecture → UPDATE → relecture → DELETE → absente.
 * Le seed n'est jamais modifié. Sauté si aucune base joignable.
 */
final class RowCrudTest extends TestCase
{
    private Database $db;
    private PDO $pdo;
    private PostgresWriter $writer;

    protected function setUp(): void
    {
        try {
            $this->db  = new Database();
            $this->pdo = $this->db->connect();
        } catch (Throwable $e) {
            $this->markTestSkipped('PostgreSQL non joignable : ' . $e->getMessage());
        }

        $this->writer = new PostgresWriter($this->db);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testInsertUpdateDelete(): void
    {
        $email = 'crud-test@phppgadmin.local';

        // INSERT
        [$sql, $params] = $this->writer->buildInsert('public', 'customers', [
            'email'     => $email,
            'full_name' => 'CRUD Test',
        ]);
        $this->assertSame(1, $this->db->execute($sql, $params));

        $id = (int) ($this->db->fetchOne(
            'SELECT id FROM public.customers WHERE email = :e',
            ['e' => $email],
        )['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        // UPDATE
        [$sql, $params] = $this->writer->buildUpdate('public', 'customers', ['full_name' => 'CRUD Modifié'], ['id' => $id]);
        $this->assertSame(1, $this->db->execute($sql, $params));

        $row = $this->db->fetchOne('SELECT full_name FROM public.customers WHERE id = :id', ['id' => $id]);
        $this->assertSame('CRUD Modifié', $row['full_name']);

        // DELETE
        [$sql, $params] = $this->writer->buildDelete('public', 'customers', ['id' => $id]);
        $this->assertSame(1, $this->db->execute($sql, $params));

        $this->assertNull($this->db->fetchOne('SELECT id FROM public.customers WHERE id = :id', ['id' => $id]));
    }
}

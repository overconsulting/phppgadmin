<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Vérifie, contre le vrai PostgreSQL, la seconde ligne de défense : une transaction
 * `READ ONLY` rejette toute écriture (même si le garde de préfixe avait été contourné).
 *
 * Sauté automatiquement si aucune base n'est joignable (ex. exécution hors conteneur).
 */
final class ReadOnlyTransactionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new Database())->connect();
        } catch (Throwable $e) {
            $this->markTestSkipped('PostgreSQL non joignable : ' . $e->getMessage());
        }
    }

    public function testSelectWorksInReadOnlyTransaction(): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $value = $this->pdo->query('SELECT 1 AS n')->fetch(PDO::FETCH_ASSOC);
        $this->pdo->rollBack();

        $this->assertSame(1, (int) $value['n']);
    }

    public function testWriteIsRejectedInReadOnlyTransaction(): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->exec('SET TRANSACTION READ ONLY');

        $caught = null;
        try {
            // Écriture (DDL) refusée par la transaction READ ONLY, sans dépendre du seed.
            $this->pdo->exec('CREATE TEMP TABLE phppgadmin_should_fail (id int)');
        } catch (PDOException $e) {
            $caught = $e;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $this->assertNotNull($caught, 'Une écriture aurait dû être rejetée.');
        $this->assertStringContainsString('read-only transaction', $caught->getMessage());
    }
}

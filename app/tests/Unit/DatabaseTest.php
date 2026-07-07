<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testQuoteIdentifierWrapsInDoubleQuotes(): void
    {
        $db = new Database();
        $this->assertSame('"customers"', $db->quoteIdentifier('customers'));
    }

    public function testQuoteIdentifierDoublesInnerQuotes(): void
    {
        // Protection contre l'injection via un nom d'identifiant contenant un guillemet.
        $db = new Database();
        $this->assertSame('"a""b"', $db->quoteIdentifier('a"b'));
    }
}

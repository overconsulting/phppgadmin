<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Teste la logique CSRF directement sur $_SESSION (pas besoin de session_start en CLI).
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsGeneratedAndStable(): void
    {
        $token = Csrf::token();
        $this->assertNotSame('', $token);
        $this->assertSame($token, Csrf::token(), 'Le même jeton doit être renvoyé dans la session.');
    }

    public function testValidTokenAccepted(): void
    {
        $token = Csrf::token();
        $this->assertTrue(Csrf::isValid($token));
    }

    public function testWrongTokenRejected(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::isValid('mauvais-jeton'));
    }

    public function testEmptyOrNullRejected(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::isValid(''));
        $this->assertFalse(Csrf::isValid(null));
    }

    public function testRejectedWhenNoTokenInSession(): void
    {
        // Aucun jeton généré : toute valeur doit être refusée.
        $this->assertFalse(Csrf::isValid('quoi-que-ce-soit'));
    }
}

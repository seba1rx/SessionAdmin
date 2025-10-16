<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\SessionAdminServer;

#[CoversClass(SessionAdminServer::class)]
final class SessionAdminServerTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure session is available
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Reset globals before each test
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testConstructorInitializesSessionKey(): void
    {
        $server = new SessionAdminServer();
        $key = $server->getSessionKey();

        $this->assertArrayHasKey($key, $_SESSION);
        $this->assertIsArray($_SESSION[$key]);
        $this->assertEmpty($_SESSION[$key]);
    }

    public function testSetAndGetDataForTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab123';
        $server = new SessionAdminServer();

        $server->set('username', 'seba');
        $result = $server->get('username');

        $this->assertSame('seba', $result);
        $key = $server->getSessionKey();
        $this->assertArrayHasKey('tab123', $_SESSION[$key]);
        $this->assertArrayHasKey('_data', $_SESSION[$key]['tab123']);
        $this->assertArrayHasKey('_active', $_SESSION[$key]['tab123']);
        $this->assertArrayHasKey('_last_active', $_SESSION[$key]['tab123']);
        $this->assertTrue($_SESSION[$key]['tab123']['_active']);
    }

    public function testSetDoesNothingWithoutTabId(): void
    {
        $server = new SessionAdminServer();
        $server->set('key', 'value'); // No cookie set

        $key = $server->getSessionKey();
        $this->assertEmpty($_SESSION[$key]);
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab1';
        $server = new SessionAdminServer();

        $value = $server->get('nonexistent', 'default');
        $this->assertSame('default', $value);
    }

    public function testDestroyTabSessionRemovesData(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabX';
        $server = new SessionAdminServer();
        $server->set('token', 'abc123');

        $key = $server->getSessionKey();
        $this->assertArrayHasKey('tabX', $_SESSION[$key]);

        $server->destroyTabSession('tabX');
        $this->assertArrayNotHasKey('tabX', $_SESSION[$key]);
    }

    public function testMarkInactiveTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabY';
        $server = new SessionAdminServer();
        $server->set('key', 'value');

        $key = $server->getSessionKey();
        $this->assertTrue($_SESSION[$key]['tabY']['_active']);

        $server->markInactiveTab('tabY');
        $this->assertFalse($_SESSION[$key]['tabY']['_active']);
    }

    public function testDebugReturnsExpectedStructure(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabZ';
        $server = new SessionAdminServer();
        $server->set('foo', 'bar');
        $server->markInactiveTab('tabZ');

        $debug = $server->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('tabZ', $debug);
        $this->assertArrayHasKey('active', $debug['tabZ']);
        $this->assertArrayHasKey('last_active', $debug['tabZ']);
        $this->assertArrayHasKey('keys', $debug['tabZ']);
        $this->assertArrayHasKey('size', $debug['tabZ']);
        $this->assertIsArray($debug['tabZ']['keys']);
        $this->assertContains('foo', $debug['tabZ']['keys']);
    }

    public function testGetSessionKeyReturnsConstant(): void
    {
        $server = new SessionAdminServer();
        $this->assertSame('__sessionadmin_tabs', $server->getSessionKey());
    }
}

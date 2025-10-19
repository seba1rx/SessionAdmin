<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\TabManager;

#[CoversClass(TabManager::class)]
final class TabManagerTest extends TestCase
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
        $tabManager = new TabManager();
        $key = $tabManager->getSessionKey();

        $this->assertArrayHasKey($key, $_SESSION);
        $this->assertIsArray($_SESSION[$key]);
        $this->assertEmpty($_SESSION[$key]);
    }

    public function testSetAndGetDataForTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab123';
        $tabManager = new TabManager();

        $tabManager->set('username', 'seba');
        $result = $tabManager->get('username');

        $this->assertSame('seba', $result);
        $key = $tabManager->getSessionKey();
        $this->assertArrayHasKey('tab123', $_SESSION[$key]);
        $this->assertArrayHasKey('data', $_SESSION[$key]['tab123']);
        $this->assertArrayHasKey('is_active', $_SESSION[$key]['tab123']);
        $this->assertArrayHasKey('last_active', $_SESSION[$key]['tab123']);
        $this->assertTrue($_SESSION[$key]['tab123']['is_active']);
    }

    public function testSetDoesNothingWithoutTabId(): void
    {
        $tabManager = new TabManager();
        $tabManager->set('key', 'value'); // No cookie set

        $key = $tabManager->getSessionKey();
        $this->assertEmpty($_SESSION[$key]);
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab1';
        $tabManager = new TabManager();

        $value = $tabManager->get('nonexistent', 'default');
        $this->assertSame('default', $value);
    }

    public function testDestroyTabSessionRemovesData(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabX';
        $tabManager = new TabManager();
        $tabManager->set('token', 'abc123');

        $key = $tabManager->getSessionKey();
        $this->assertArrayHasKey('tabX', $_SESSION[$key]);

        $tabManager->destroyTabSession('tabX');
        $this->assertArrayNotHasKey('tabX', $_SESSION[$key]);
    }

    public function testMarkInactiveTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabY';
        $tabManager = new TabManager();
        $tabManager->set('key', 'value');

        $key = $tabManager->getSessionKey();
        $this->assertTrue($_SESSION[$key]['tabY']['is_active']);

        $tabManager->markInactiveTab('tabY');
        $this->assertFalse($_SESSION[$key]['tabY']['is_active']);
    }

    public function testDebugReturnsExpectedStructure(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'tabZ';
        $tabManager = new TabManager();
        $tabManager->set('foo', 'bar');
        $tabManager->markInactiveTab('tabZ');

        $debug = $tabManager->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('tabZ', $debug);
        $this->assertArrayHasKey('is_active', $debug['tabZ']);
        $this->assertArrayHasKey('last_active', $debug['tabZ']);
        $this->assertArrayHasKey('keys', $debug['tabZ']);
        $this->assertArrayHasKey('size', $debug['tabZ']);
        $this->assertIsArray($debug['tabZ']['keys']);
        $this->assertContains('foo', $debug['tabZ']['keys']);
    }

    public function testGetSessionKeyReturnsConstant(): void
    {
        $tabManager = new TabManager();
        $this->assertSame('tabs', $tabManager->getSessionKey());
    }
}

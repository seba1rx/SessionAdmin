<?php
/**
 * @coversDefaultClass Seba1rx\SessionAdminServer\SessionAdminServer
 */

use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\SessionAdminServer;

/**
 * Tests for SessionAdminServer
 *
 * These tests validate tab-level session isolation and data integrity.
 */
final class SessionAdminServerTest extends TestCase
{
    protected function setUp(): void
    {
        // Simulate a clean environment for each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $_SESSION = [];
        $_COOKIE = [];
        $_COOKIE['SESSIONADMIN_TABID'] = 'test-tab-uuid';

        session_start();
    }

    /**
     * @test
     * @covers ::__construct
     * @covers ::set
     * @covers ::get
     */
    public function it_stores_and_retrieves_session_data_per_tab(): void
    {
        $admin = new SessionAdminServer();
        $admin->set('foo', 'bar');

        $this->assertSame('bar', $admin->get('foo'));
    }

    /**
     * @test
     * @covers ::get
     */
    public function it_returns_default_value_if_key_not_found(): void
    {
        $admin = new SessionAdminServer();
        $this->assertSame('default', $admin->get('missing', 'default'));
    }

    /**
     * @test
     * @covers ::destroyTabSession
     */
    public function it_can_destroy_specific_tab_session(): void
    {
        $admin = new SessionAdminServer();
        $admin->set('test', 'value');

        $this->assertNotEmpty($_SESSION[$admin->getSessionKey()]);

        $admin->destroyTabSession('test-tab-uuid');
        $this->assertEmpty($_SESSION[$admin->getSessionKey()]);
    }

    /**
     * @test
     * @covers ::markInactiveTab
     */
    public function it_marks_tab_as_inactive(): void
    {
        $admin = new SessionAdminServer();
        $admin->set('foo', 'bar');

        $admin->markInactiveTab('test-tab-uuid');

        $tabData = $_SESSION[$admin->getSessionKey()]['test-tab-uuid'] ?? [];
        $this->assertFalse($tabData['_active']);
    }

    /**
     * @test
     * @covers ::debug
     */
    public function it_returns_debug_data_with_expected_structure(): void
    {
        $admin = new SessionAdminServer();
        $admin->set('key1', 'value1');

        $debug = $admin->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('test-tab-uuid', $debug);
        $this->assertArrayHasKey('active', $debug['test-tab-uuid']);
        $this->assertArrayHasKey('keys', $debug['test-tab-uuid']);
    }

    /**
     * @test
     * @covers ::getSessionKey
     */
    public function it_returns_expected_session_key(): void
    {
        $admin = new SessionAdminServer();
        $this->assertSame('__sessionadmin_tabs', $admin->getSessionKey());
    }

    /**
     * @test
     */
    public function it_handles_multiple_tab_sessions_isolated(): void
    {
        // Tab 1
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab-1';
        $tab1 = new SessionAdminServer();
        $tab1->set('user', 'alice');

        // Tab 2
        $_COOKIE['SESSIONADMIN_TABID'] = 'tab-2';
        $tab2 = new SessionAdminServer();
        $tab2->set('user', 'bob');

        // Check isolation
        $this->assertSame('alice', $tab1->get('user'));
        $this->assertSame('bob', $tab2->get('user'));
    }
}

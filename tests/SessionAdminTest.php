<?php

/**
 * PHPUnit test suite for Seba1rx\SessionAdmin\SessionAdmin
 *
 * Tests cover:
 * - Session lifecycle
 * - Security / IP handling
 * - Utility methods
 * - Cookie handling (mocked)
 * - Configuration integrity
 */

namespace Seba1rx\SessionAdmin {
    // 🧩 Mock native PHP setcookie() within this namespace
    function setcookie($name, $value = "", $expire = 0, $path = "", $domain = "", $secure = false, $httponly = false, $options = [])
    {
        global $__cookie_mock;
        $__cookie_mock[$name] = [
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'options' => $options,
        ];
        return true;
    }
}

namespace {

use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\SessionAdmin;
use Seba1rx\SessionAdmin\SessionAdminServer;

/**
 * Concrete implementation of abstract SessionAdmin for testing
 */
class SessionAdminConcrete extends SessionAdmin
{
    public function __construct(array $config = [])
    {
        if (isset($config['sessionLifetime'])) {
            $this->sessionLifetime = $config['sessionLifetime'];
        }
        if (isset($config['allowedURLs'])) {
            $this->allowedUrls = $config['allowedURLs'];
        }
        if (isset($config['keys'])) {
            $this->keys = $config['keys'];
        }
    }
}

/**
 * PHPUnit Test Class
 */
class SessionAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        $_COOKIE = [];
        $_SERVER = [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'PHPUnit',
            'SERVER_NAME' => 'localhost',
        ];
    }

    /* ───────────────────────────────
       🧩 SESSION LIFECYCLE TESTS
       ─────────────────────────────── */

    public function testActivateSessionCreatesGuestSession(): void
    {
        $admin = new SessionAdminConcrete();
        $admin->activateSession();

        $this->assertArrayHasKey('isUser', $_SESSION);
        $this->assertFalse($_SESSION['isUser']);
        $this->assertSame('you are a guest', $_SESSION['msg']);
        $this->assertArrayHasKey('allowedUrl', $_SESSION);
    }

    public function testCreateUserSessionSetsExpectedSessionData(): void
    {
        $admin = new SessionAdminConcrete();
        session_start();
        $admin->createUserSession(42);

        $this->assertTrue($_SESSION['isUser']);
        $this->assertSame('you are a user', $_SESSION['msg']);
        $this->assertSame(42, $_SESSION['id_user']);
        $this->assertArrayHasKey('uniqueId', $_SESSION);
    }

    /* ───────────────────────────────
       🔒 SECURITY & IP TESTS
       ─────────────────────────────── */

    public function testRequestIsHijackingAttemptReturnsFalseWhenSessionFresh(): void
    {
        $admin = new SessionAdminConcrete();
        session_start();
        $_SESSION = [];

        $method = $this->getPrivateMethod($admin, 'requestIsHijackingAttempt');
        $result = $method->invoke($admin);

        $this->assertFalse($result);
    }

    public function testGetIpPrefixExtractsCorrectPrefix(): void
    {
        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'getIpPrefix');
        $result = $method->invoke($admin, '192.168.1.100', 3);
        $this->assertSame('192.168.1', $result);
    }

    /* ───────────────────────────────
       🧮 UTILITY METHOD TESTS
       ─────────────────────────────── */

    public function testGetSubStrAfterLastReturnsCorrectSegment(): void
    {
        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'getSubStrAfterLast');
        $result = $method->invoke($admin, 'folder/subfolder/file.php', '/');
        $this->assertSame('file.php', $result);
    }

    public function testStrrevposFindsReversePosition(): void
    {
        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'strrevpos');
        $result = $method->invoke($admin, 'abc/def/ghi', '/');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /* ───────────────────────────────
       🍪 COOKIE HANDLING TESTS
       ─────────────────────────────── */

    public function testSetCookieStoresValuesWithExpectedAttributes(): void
    {
        global $__cookie_mock;
        $__cookie_mock = [];

        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'setCookie');
        $method->invoke($admin, 'session_token', 'abc123', 3600);

        $this->assertArrayHasKey('session_token', $__cookie_mock);
        $cookie = $__cookie_mock['session_token'];
        $this->assertSame('abc123', $cookie['value']);
        $this->assertSame(3600, $cookie['expire']);
        $this->assertTrue($cookie['httponly']);
    }

    public function testGetCookieValueReturnsExistingCookie(): void
    {
        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'getCookieValue');
        $_COOKIE['testcookie'] = 'cookie_value';
        $result = $method->invoke($admin, 'testcookie');
        $this->assertSame('cookie_value', $result);
    }

    public function testGetCookieValueReturnsNullWhenMissing(): void
    {
        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'getCookieValue');
        unset($_COOKIE['missingcookie']);
        $result = $method->invoke($admin, 'missingcookie');
        $this->assertNull($result);
    }

    public function testDeleteCookieRemovesCookieCorrectly(): void
    {
        global $__cookie_mock;
        $__cookie_mock = [];

        $admin = new SessionAdminConcrete();
        $method = $this->getPrivateMethod($admin, 'deleteCookie');
        $_COOKIE['expired'] = 'dead';
        $method->invoke($admin, 'expired');

        $this->assertArrayHasKey('expired', $__cookie_mock);
        $cookie = $__cookie_mock['expired'];
        $this->assertLessThan(time(), $cookie['expire']);
    }

    /* ───────────────────────────────
       ⚙️ CONFIGURATION INTEGRITY TESTS
       ─────────────────────────────── */

    public function testConstructorAppliesConfigurationArray(): void
    {
        $config = [
            'sessionLifetime' => 999,
            'allowedURLs' => ['index.php', 'contact.php'],
            'keys' => ['foo' => 'bar', 'lang' => 'en'],
        ];

        $admin = new SessionAdminConcrete($config);

        $this->assertSame(999, $admin->sessionLifetime);
        $this->assertSame(['index.php', 'contact.php'], $admin->allowedUrls);
        $this->assertSame(['foo' => 'bar', 'lang' => 'en'], $admin->keys);
    }

    public function testActivateSessionLoadsConfiguredKeys(): void
    {
        $config = [
            'sessionLifetime' => 800,
            'keys' => ['theme' => 'dark', 'region' => 'us'],
        ];

        $admin = new SessionAdminConcrete($config);
        $_SESSION = [];
        $admin->activateSession();

        $this->assertArrayHasKey('theme', $_SESSION);
        $this->assertArrayHasKey('region', $_SESSION);
        $this->assertSame('dark', $_SESSION['theme']);
        $this->assertSame('us', $_SESSION['region']);
    }

    public function testActivateSessionWithAuthorizationEnabled(): void
    {
        $config = ['allowedURLs' => ['index.php']];
        $admin = new SessionAdminConcrete($config);
        $admin->useAuthorization = true;
        $admin->app_isSpa = false;

        $_SERVER['PHP_SELF'] = '/index.php';
        $_SESSION = [];

        $admin->activateSession();

        $this->assertArrayHasKey('urlIsAllowedToLoad', $_SESSION);
        $this->assertFalse($_SESSION['urlIsAllowedToLoad']);
    }

    public function testConfigurationDefaultsWhenNoValuesProvided(): void
    {
        $admin = new SessionAdminConcrete();

        $this->assertSame(2400, $admin->sessionLifetime);
        $this->assertSame('SESSION', $admin->sessionName);
        $this->assertSame('/', $admin->path);
        $this->assertNull($admin->domain);
        $this->assertNull($admin->secure);
    }

    /* ───────────────────────────────
       🧩 HELPER METHOD
       ─────────────────────────────── */

    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $method = new \ReflectionMethod($object, $methodName);
        $method->setAccessible(true);
        return $method;
    }
}

}

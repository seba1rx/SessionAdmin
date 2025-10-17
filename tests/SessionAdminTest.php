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
namespace Seba1rx\SessionAdmin{

    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;
    use Seba1rx\SessionAdmin\SessionAdmin;
    use Seba1rx\SessionAdmin\SessionAdminServer;

    /**
     * Concrete implementation of abstract SessionAdmin for testing
     */
    class SessionAdminConcrete extends SessionAdmin
    {
        public array $cookies = [];

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

        protected function mockSetCookie(
            string $name,
            string $value,
            int|string|array $expiresOrOptions,
            string $path = "",
            string $domain = "",
            bool $secure = false,
            bool $httponly = false
        ): bool {
            $this->cookies[$name] = compact(
                'name',
                'value',
                'expiresOrOptions',
                'path',
                'domain',
                'secure',
                'httponly'
            );
            return true;
        }

        protected function mockGetCookie(string $name, mixed $default = null): mixed
        {
            return $this->cookies[$name]['value'] ?? $default;
        }
    }

    #[CoversClass(SessionAdmin::class)]
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
            // session_start();
            $admin->activateSession();
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
            $admin = new SessionAdminConcrete();
            $method = \ReflectionMethod::createFromMethodName(
                $admin::class . '::setCookie'
            );
            $method->invoke($admin, 'session_token', 'abc123', 3600, "/", "", false, true);

            $this->assertArrayHasKey('session_token', $admin->cookies);
            $cookie = $admin->cookies['session_token'];
            $this->assertSame('abc123', $cookie['value']);
            $this->assertSame(3600, $cookie['expiresOrOptions']);
            $this->assertEmpty($cookie['domain']);
            $this->assertFalse($cookie['secure']);
            $this->assertTrue($cookie['httponly']);
        }

        public function testGetCookieValueReturnsExistingCookie(): void
        {
            $admin = new SessionAdminConcrete();

            // prepare reflection method "setCookie"
            $set_method = \ReflectionMethod::createFromMethodName(
                $admin::class . '::setCookie'
            );
            // lets set a cookie in order to try to get it later
            $set_method->invoke($admin, 'testcookie', 'cookie_value', 3600, "/", "", false, true);

            // prepare reflection method "getCookie"
            $get_method = \ReflectionMethod::createFromMethodName(
                $admin::class . '::getCookie'
            );

            // lets get the cookie named testcookie
            $result = $get_method->invoke($admin, 'testcookie');
            $this->assertSame('cookie_value', $result);
        }

        public function testGetCookieValueReturnsNullWhenMissing(): void
        {
            $admin = new SessionAdminConcrete();

            // prepare reflection method "getCookie"
            $get_method = \ReflectionMethod::createFromMethodName(
                $admin::class . '::getCookie'
            );

            // trying to get a cookie that does not exist
            $result = $get_method->invoke($admin, 'missingCookie');

            $this->assertNull($result);

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

            // Create a ReflectionClass instance
            $reflection = new \ReflectionClass($admin);

            // Get the protected property
            $sessionLifetime_property = $reflection->getProperty('sessionLifetime');
            $allowedUrls_property = $reflection->getProperty('allowedUrls');
            $keys_property = $reflection->getProperty('keys');

            // Bypass visibility (protected/private)
            $sessionLifetime_property->setAccessible(true);
            $allowedUrls_property->setAccessible(true);
            $keys_property->setAccessible(true);

            // Read the value
            $sessionLifetime_value = $sessionLifetime_property->getValue($admin);
            $allowedUrls_value = $allowedUrls_property->getValue($admin);
            $keys_value = $keys_property->getValue($admin);

            $this->assertSame(999, $sessionLifetime_value);
            $this->assertSame(['index.php', 'contact.php'], $allowedUrls_value);
            $this->assertSame(['foo' => 'bar', 'lang' => 'en'], $keys_value);
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

            //$_SESSION['urlIsAllowedToLoad'] is set to false by default unles $_SERVER['PHP_SELF'] is indeed in "allowedURLs"
            $this->assertArrayHasKey('urlIsAllowedToLoad', $_SESSION);
            $this->assertTrue($_SESSION['urlIsAllowedToLoad']);
        }

        public function testConfigurationDefaultsWhenNoValuesProvided(): void
        {
            $admin = new SessionAdminConcrete();

            // we will need to access some protected properties, so Reflexion is needed

            // Create a ReflectionClass instance
            $reflection = new \ReflectionClass($admin);

            // Get the protected property
            $sessionLifetime_property = $reflection->getProperty('sessionLifetime');
            $sessionName_property = $reflection->getProperty('sessionName');
            $path_property = $reflection->getProperty('path');
            $domain_property = $reflection->getProperty('domain');
            $secure_property = $reflection->getProperty('secure');

            // Bypass visibility (protected/private)
            $sessionLifetime_property->setAccessible(true);
            $sessionName_property->setAccessible(true);
            $path_property->setAccessible(true);
            $domain_property->setAccessible(true);
            $secure_property->setAccessible(true);

            // Read the value
            $sessionLifetime_value = $sessionLifetime_property->getValue($admin);
            $sessionName_value = $sessionName_property->getValue($admin);
            $path_value = $path_property->getValue($admin);
            $domain_value = $domain_property->getValue($admin);
            $secure_value = $secure_property->getValue($admin);

            $this->assertSame(2400, $sessionLifetime_value);
            $this->assertSame('SESSION', $sessionName_value);
            $this->assertSame('/', $path_value);
            $this->assertNull($domain_value);
            $this->assertNull($secure_value);
        }

        /* ───────────────────────────────
       🧩 HELPER METHOD
       ─────────────────────────────── */

        private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
        {
            $method = new \ReflectionMethod($object::class, $methodName);
            $method->setAccessible(true);
            return $method;
        }
    }
}

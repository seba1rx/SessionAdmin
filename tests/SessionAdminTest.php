<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\SessionAdmin;


#[CoversClass(SessionAdmin::class)]
class SessionAdminTest extends TestCase
{

    protected function getSessionAdminConcrete(array $config = []): SessionAdmin
    {
        // Since SessionAdmin requires an implementation to define a constructor, lets define one here
        return new class('SessionAdminConcrete', $config) extends SessionAdmin {

            public array $cookies = [];

            public function __construct(string $name, array $config = [])
            {
                if (isset($config['sessionLifetime'])) {
                    $this->sessionLifetime = $config['sessionLifetime'];
                }
                if (isset($config['allowedURLs'])) {
                    // echo "## assigning allowedURLs: " . json_encode($config['allowedURLs']);
                    $this->allowedUrls = $config['allowedURLs'];
                }
                if (isset($config['keys'])) {
                    $this->keys = $config['keys'];
                }
            }

            // in order to test the setCookie and getCookie methods,
            // lets define mock methods not to set headers
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
        };
    }

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
    SESSION LIFECYCLE TESTS
    ─────────────────────────────── */

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::activateSession
     * @return void
     */
    public function testActivateSessionCreatesGuestSession(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $admin->activateSession();

        // echo json_encode($_SESSION);

        $this->assertArrayHasKey('isUser', $_SESSION);
        $this->assertFalse($_SESSION['isUser']);
        $this->assertSame('you are a guest', $_SESSION['msg']);
        $this->assertArrayHasKey('allowedUrl', $_SESSION);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::activateSession
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::createUserSesion
     * @return void
     */
    public function testCreateUserSessionSetsExpectedSessionData(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        session_start();
        // $admin->activateSession();
        $admin->createUserSession(42);

        $this->assertTrue($_SESSION['isUser']);
        $this->assertSame('you are a user', $_SESSION['msg']);
        $this->assertSame(42, $_SESSION['id_user']);
        $this->assertArrayHasKey('uniqueId', $_SESSION);
    }

    /* ───────────────────────────────
    SECURITY & IP TESTS
    ─────────────────────────────── */

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::requestIsHijackingAttempt
     * @return void
     */
    public function testRequestIsHijackingAttemptReturnsFalseWhenSessionFresh(): void
    {
        $admin = $this->getSessionAdminConcrete();
        // $admin->activateSession();
        session_start();
        $_SESSION = [];

        $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        $setIsRunningTests->invoke($admin, true);

        $method = $this->getPrivateMethod($admin, 'requestIsHijackingAttempt');
        $result = $method->invoke($admin);

        $this->assertFalse($result);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::getIpPrefix
     * @return void
     */
    public function testGetIpPrefixExtractsCorrectPrefix(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $method = $this->getPrivateMethod($admin, 'getIpPrefix');
        $result = $method->invoke($admin, '192.168.1.100', 3);
        $this->assertSame('192.168.1', $result);
    }

    /* ───────────────────────────────
    UTILITY METHOD TESTS
    ─────────────────────────────── */

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::getSubStrAfterLast
     * @return void
     */
    public function testGetSubStrAfterLastReturnsCorrectSegment(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $getSubStrAfterLast = $this->getPrivateMethod($admin, 'getSubStrAfterLast');
        $result = $getSubStrAfterLast->invoke($admin, 'folder/subfolder/file.php', '/');
        $this->assertSame('file.php', $result);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::strrevpos
     * @return void
     */
    public function testStrrevposFindsReversePosition(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $method = $this->getPrivateMethod($admin, 'strrevpos');
        $result = $method->invoke($admin, 'abc/def/ghi', '/');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /* ───────────────────────────────
    COOKIE HANDLING TESTS
    ─────────────────────────────── */

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::setCookie
     * @return void
     */
    public function testSetCookieStoresValuesWithExpectedAttributes(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $method = new \ReflectionMethod($admin, 'setCookie');
        $method->invoke($admin, 'session_token', 'abc123', 3600, "/", "", false, true);

        $this->assertArrayHasKey('session_token', $admin->cookies);
        $cookie = $admin->cookies['session_token'];
        $this->assertSame('abc123', $cookie['value']);
        $this->assertSame(3600, $cookie['expiresOrOptions']);
        $this->assertEmpty($cookie['domain']);
        $this->assertFalse($cookie['secure']);
        $this->assertTrue($cookie['httponly']);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::setCookie
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::getCookie
     * @return void
     */
    public function testGetCookieValueReturnsExistingCookie(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        // prepare reflection method "setCookie"
        $set_cookie = new \ReflectionMethod($admin, 'setCookie');

        // lets set a cookie in order to try to get it later
        $set_cookie->invoke($admin, 'testcookie', 'cookie_value', 3600, "/", "", false, true);

        // prepare reflection method "getCookie"
        $get_cookie = new \ReflectionMethod($admin, 'getCookie');

        // lets get the cookie named testcookie
        $result = $get_cookie->invoke($admin, 'testcookie');
        $this->assertSame('cookie_value', $result);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::getCookie
     * @return void
     */
    public function testGetCookieValueReturnsNullWhenMissing(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        // prepare reflection method "getCookie"
        $get_cookie = new \ReflectionMethod($admin, 'getCookie');

        // trying to get a cookie that does not exist
        $result = $get_cookie->invoke($admin, 'missingCookie');

        $this->assertNull($result);

    }

    /* ───────────────────────────────
    CONFIGURATION INTEGRITY TESTS
    ─────────────────────────────── */

    /**
     * @return void
     */
    public function testConstructorAppliesConfigurationArray(): void
    {
        $config = [
            'sessionLifetime' => 999,
            'allowedURLs' => ['index.php', 'contact.php'],
            'keys' => ['foo' => 'bar', 'lang' => 'en'],
        ];

        $admin = $this->getSessionAdminConcrete($config);

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

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

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::activateSession
     * @return void
     */
    public function testActivateSessionLoadsConfiguredKeys(): void
    {
        $config = [
            'sessionLifetime' => 800,
            'keys' => ['theme' => 'dark', 'region' => 'us'],
        ];

        $admin = $this->getSessionAdminConcrete($config);
        $_SESSION = [];
        $admin->activateSession();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $this->assertArrayHasKey('theme', $_SESSION);
        $this->assertArrayHasKey('region', $_SESSION);
        $this->assertSame('dark', $_SESSION['theme']);
        $this->assertSame('us', $_SESSION['region']);
    }

    /**
     * @covers \Seba1rx\SessionAdmin\SessionAdmin::activateSession
     * @return void
     */
    public function testActivateSessionWithAuthorizationEnabled(): void
    {
        $config = ['allowedURLs' => ['index.php']];
        $admin = $this->getSessionAdminConcrete($config);

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

        $admin->useAuthorization = true;
        $admin->app_isSpa = false;

        $_SERVER['PHP_SELF'] = '/index.php';
        $_SESSION = [];

        $admin->activateSession();

        //$_SESSION['urlIsAllowedToLoad'] is set to false by default unles $_SERVER['PHP_SELF'] is indeed in "allowedURLs"
        $this->assertArrayHasKey('urlIsAllowedToLoad', $_SESSION);
        $this->assertTrue($_SESSION['urlIsAllowedToLoad']);
    }

    /**
     * @return void
     */
    public function testConfigurationDefaultsWhenNoValuesProvided(): void
    {
        $admin = $this->getSessionAdminConcrete();

        // $setIsRunningTests = $this->getPrivateMethod($admin, 'setIsRunningTests');
        // $setIsRunningTests->invoke($admin, true);

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
    HELPER METHOD
    ─────────────────────────────── */

    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $method = new \ReflectionMethod($object::class, $methodName);
        $method->setAccessible(true);
        return $method;
    }
}
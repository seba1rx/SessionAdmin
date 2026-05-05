<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\Session;
use Seba1rx\SessionAdmin\SessionAdmin;
use Seba1rx\SessionAdmin\TabManager;
use Seba1rx\SessionAdmin\Contracts\SessionInterface;
use Seba1rx\SessionAdmin\Exceptions\SessionAdminException;

#[CoversClass(SessionAdmin::class)]
#[CoversClass(Session::class)]
#[UsesClass(TabManager::class)]
class SessionAdminTest extends TestCase
{
    // ── Fixture factory ──────────────────────────────────────────────────────

    /**
     * Returns a concrete anonymous subclass of SessionAdmin ready for testing.
     *
     * The subclass:
     *  - accepts a $config array (sessionLifetime, allowedURLs, keys)
     *  - overrides setCookie / getCookie so no real HTTP headers are sent
     *  - overrides redirectToIndex() so tests are not killed by exit()
     *
     * @param array $config
     * @return SessionAdmin
     */
    protected function getSessionAdminConcrete(array $config = []): SessionAdmin
    {
        return new class($config) extends SessionAdmin {

            /** Stores cookies intercepted by the setCookie override. */
            public array $cookies = [];

            /** Set to true whenever redirectToIndex() is called. */
            public bool $redirectCalled = false;

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

            /** Intercepts setcookie() so tests don't emit headers. */
            protected function setCookie(
                string $name,
                string $value = "",
                int|string|array $expiresOrOptions = 0,
                string $path = "",
                string $domain = "",
                bool $secure = false,
                bool $httponly = false
            ): bool {
                $this->cookies[$name] = compact(
                    'name', 'value', 'expiresOrOptions', 'path', 'domain', 'secure', 'httponly'
                );
                return true;
            }

            /** Reads from the in-memory cookie store instead of $_COOKIE. */
            protected function getCookie(string $name, mixed $default = null): mixed
            {
                return $this->cookies[$name]['value'] ?? $default;
            }

            /** Prevents exit() from killing the test process on redirects. */
            protected function redirectToIndex(): void
            {
                $this->redirectCalled = true;
            }
        };
    }

    // ── setUp ────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Prevent the next session_start() from reopening the same session file.
        // session_write_close() retains the session ID in PHP memory; setting a new
        // random ID here forces a fresh session on the next session_start() call.
        session_id(bin2hex(random_bytes(16)));

        $_SESSION = [];
        $_COOKIE  = [];
        $_SERVER  = [
            'REMOTE_ADDR'      => '192.168.1.100',
            'HTTP_USER_AGENT'  => 'PHPUnit',
            'SERVER_NAME'      => 'localhost',
        ];
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    /** Expose any protected/private method via Reflection. */
    private function method(object $object, string $name): \ReflectionMethod
    {
        return new \ReflectionMethod($object::class, $name);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SESSION LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

    public function testActivateSessionCreatesGuestSession(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();

        $sa = $_SESSION['sessionadmin'];
        $this->assertFalse($sa['isUser']);
        $this->assertSame('you are a guest', $sa['msg']);
        $this->assertSame('SPA', $sa['appType']);                          // default is SPA
        $this->assertArrayNotHasKey('allowedUrl', $sa);                   // MPA-only keys absent
        $this->assertArrayNotHasKey('urlIsAllowedToLoad', $sa);
    }

    public function testGuestSessionInSpaDoesNotHaveAuthorizationKeys(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->appIsSpa = true;
        $admin->activateSession();

        $sa = $_SESSION['sessionadmin'];
        $this->assertSame('SPA', $sa['appType']);
        $this->assertArrayNotHasKey('allowedUrl', $sa);
        $this->assertArrayNotHasKey('urlIsAllowedToLoad', $sa);
    }

    public function testGuestSessionInMpaHasAuthorizationKeys(): void
    {
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->appIsSpa = false;
        $admin->activateSession();

        $sa = $_SESSION['sessionadmin'];
        $this->assertSame('MPA', $sa['appType']);
        $this->assertArrayHasKey('allowedUrl', $sa);
        $this->assertArrayHasKey('urlIsAllowedToLoad', $sa);
        $this->assertFalse($sa['urlIsAllowedToLoad']);
    }

    public function testAppTypeIsUpdatedOnEveryActivation(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->appIsSpa = true;
        $admin->activateSession();
        $this->assertSame('SPA', $_SESSION['sessionadmin']['appType']);

        // Simulate a subsequent request where app switches mode (edge case)
        session_write_close();
        session_id(bin2hex(random_bytes(16)));
        $_SESSION = [];

        $admin2 = $this->getSessionAdminConcrete();
        $admin2->appIsSpa = false;
        $admin2->activateSession();
        $this->assertSame('MPA', $_SESSION['sessionadmin']['appType']);
    }

    public function testActivateSessionGeneratesUniqueId(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();

        $this->assertArrayHasKey('uniqueId', $_SESSION['sessionadmin']);
        $this->assertNotEmpty($_SESSION['sessionadmin']['uniqueId']);
        $this->assertIsString($_SESSION['sessionadmin']['uniqueId']);
    }

    public function testActivateSessionInjectsConfiguredKeys(): void
    {
        $admin = $this->getSessionAdminConcrete([
            'keys' => ['theme' => 'dark', 'region' => 'us'],
        ]);
        $admin->activateSession();

        // Custom keys land at the root of $_SESSION, not under 'sessionadmin'
        $this->assertSame('dark', $_SESSION['theme']);
        $this->assertSame('us', $_SESSION['region']);
    }

    public function testActivateSessionInitializesTabManager(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->useTabIndexation = true;
        $admin->activateSession();

        $this->assertInstanceOf(TabManager::class, $admin->tabManager);
    }

    public function testActivateSessionSkipsTabManagerWhenDisabled(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->useTabIndexation = false;
        $admin->activateSession();

        $this->assertFalse(isset($admin->tabManager));
    }

    public function testCreateUserSessionSetsExpectedData(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();
        $admin->createUserSession(42);

        $sa = $_SESSION['sessionadmin'];
        $this->assertTrue($sa['isUser']);
        $this->assertSame('you are a user', $sa['msg']);
        $this->assertSame(42, $sa['id_user']);
        $this->assertArrayHasKey('uniqueId', $sa);
        // createUserSession() calls setSessionTimeStamps() — verify timestamps are present
        $this->assertArrayHasKey('time_atRequest', $sa);
        $this->assertArrayHasKey('time_sinceLastRequest', $sa);
        $this->assertIsInt($sa['time_atRequest']);
        $this->assertGreaterThanOrEqual(0, $sa['time_sinceLastRequest']);
    }

    public function testCreateUserSessionAcceptsStringId(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();
        $admin->createUserSession('usr-abc');

        $this->assertSame('usr-abc', $_SESSION['sessionadmin']['id_user']);
    }

    public function testCreateUserSessionDoesNotOverrideUrlIsAllowedToLoad(): void
    {
        // urlIsAllowedToLoad only exists in MPA mode; test in that context.
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->appIsSpa         = false;
        $admin->useAuthorization = true;
        $_SERVER['SCRIPT_NAME']  = '/index.php';
        $admin->activateSession();

        // Simulate the URL already being marked allowed (e.g. on the login page itself)
        $this->assertTrue($_SESSION['sessionadmin']['urlIsAllowedToLoad']);

        $admin->createUserSession(1);

        // createUserSession() must not reset urlIsAllowedToLoad — that is checkIfUrlIsAllowed()'s job
        $this->assertTrue($_SESSION['sessionadmin']['urlIsAllowedToLoad']);
    }

    public function testTerminateResetsToGuestSession(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->appIsSpa = true; // SPA mode: no redirect on terminate()
        $admin->activateSession();
        $admin->createUserSession(1);

        $this->assertTrue($_SESSION['sessionadmin']['isUser']);

        $admin->terminate();

        $this->assertFalse($_SESSION['sessionadmin']['isUser']);
        $this->assertSame('you are a guest', $_SESSION['sessionadmin']['msg']);
    }

    public function testTerminateCallsRedirectInMpaMode(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->appIsSpa          = false;
        $admin->terminateRedirects = true;
        $admin->activateSession();
        $admin->createUserSession(1);

        $admin->terminate();

        $this->assertTrue($admin->redirectCalled);
    }

    public function testTerminateDoesNotRedirectWhenTerminateRedirectsIsFalse(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->appIsSpa           = false;
        $admin->terminateRedirects = false;
        $admin->activateSession();
        $admin->createUserSession(1);

        $admin->terminate();

        $this->assertFalse($admin->redirectCalled);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // URL AUTHORIZATION
    // ═══════════════════════════════════════════════════════════════════════

    public function testActivateSessionAllowsListedUrl(): void
    {
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->useAuthorization  = true;
        $admin->appIsSpa          = false;
        $_SERVER['SCRIPT_NAME']   = '/index.php';

        $admin->activateSession();

        $this->assertTrue($_SESSION['sessionadmin']['urlIsAllowedToLoad']);
        $this->assertFalse($admin->redirectCalled);
    }

    public function testActivateSessionBlocksUnlistedUrlAndCallsRedirect(): void
    {
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->useAuthorization  = true;
        $admin->appIsSpa          = false;
        $_SERVER['SCRIPT_NAME']   = '/secret.php';

        $admin->activateSession();

        $this->assertFalse($_SESSION['sessionadmin']['urlIsAllowedToLoad']);
        $this->assertTrue($admin->redirectCalled);
    }

    public function testActivateSessionIgnoresAuthorizationInSpaMode(): void
    {
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->useAuthorization  = true;
        $admin->appIsSpa          = true; // SPA: authorization is never checked
        $_SERVER['SCRIPT_NAME']   = '/anything.php';

        $admin->activateSession();

        // SPA mode skips URL checks entirely — no redirect and no MPA keys in session
        $this->assertFalse($admin->redirectCalled);
        $this->assertArrayNotHasKey('urlIsAllowedToLoad', $_SESSION['sessionadmin']);
    }

    public function testActivateSessionIgnoresUrlsInIgnoreList(): void
    {
        $admin = $this->getSessionAdminConcrete(['allowedURLs' => ['index.php']]);
        $admin->useAuthorization      = true;
        $admin->appIsSpa              = false;
        $admin->ignoreInAuthorization = ['public.php'];
        $_SERVER['SCRIPT_NAME']       = '/public.php';

        $admin->activateSession();

        // checkIfUrlIsAllowed() returns early for ignored files; no redirect is triggered
        $this->assertFalse($admin->redirectCalled);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STATE CHECKS
    // ═══════════════════════════════════════════════════════════════════════

    public function testCurrentStateIsUserReturnsFalseForGuest(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();

        $result = $this->method($admin, 'currentStateIsUser')->invoke($admin);

        $this->assertFalse($result);
    }

    public function testCurrentStateIsUserReturnsTrueAfterLogin(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();
        $admin->createUserSession(1);

        $result = $this->method($admin, 'currentStateIsUser')->invoke($admin);

        $this->assertTrue($result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SESSION TIMESTAMPS & OBSOLESCENCE
    // ═══════════════════════════════════════════════════════════════════════

    public function testSetSessionTimeStampsSetsExpectedKeys(): void
    {
        session_start();
        $_SESSION['sessionadmin'] = [];

        $admin = $this->getSessionAdminConcrete();
        $this->method($admin, 'setSessionTimeStamps')->invoke($admin);

        $sa = $_SESSION['sessionadmin'];
        $this->assertArrayHasKey('time_atRequest', $sa);
        $this->assertArrayHasKey('time_sinceLastRequest', $sa);
        $this->assertIsInt($sa['time_atRequest']);
        // On first call previous == now, so elapsed is 0 or 1 second
        $this->assertGreaterThanOrEqual(0, $sa['time_sinceLastRequest']);
    }

    public function testRequestIsObsoleteReturnsTrueWhenExpired(): void
    {
        session_start();
        $_SESSION['sessionadmin']['time_sinceLastRequest'] = 99999;

        $admin = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsObsolete')->invoke($admin);

        $this->assertTrue($result);
    }

    public function testRequestIsObsoleteReturnsFalseForFreshSession(): void
    {
        session_start();
        $_SESSION['sessionadmin']['time_sinceLastRequest'] = 1;

        $admin = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsObsolete')->invoke($admin);

        $this->assertFalse($result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HIJACKING DETECTION
    // ═══════════════════════════════════════════════════════════════════════

    public function testRequestIsHijackingAttemptReturnsFalseOnFreshSession(): void
    {
        session_start();
        $_SESSION = [];

        // Fresh session: ipPrefix/userAgent keys are absent → not a hijacking, just record them
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);

        $this->assertFalse($result);
        // After the call the values must have been stored
        $this->assertArrayHasKey('ipPrefix', $_SESSION['sessionadmin']);
        $this->assertArrayHasKey('userAgent', $_SESSION['sessionadmin']);
    }

    public function testRequestIsHijackingAttemptReturnsFalseForSameIpAndAgent(): void
    {
        session_start();
        // Seed stored values that match the test environment
        $_SESSION['sessionadmin']['ipPrefix']  = '192.168'; // matches REMOTE_ADDR 192.168.1.100
        $_SESSION['sessionadmin']['userAgent'] = 'PHPUnit';

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);

        $this->assertFalse($result);
    }

    public function testRequestIsHijackingAttemptReturnsTrueOnIpChange(): void
    {
        session_start();
        $_SESSION['sessionadmin']['ipPrefix']  = '192.168'; // original prefix
        $_SESSION['sessionadmin']['userAgent'] = 'PHPUnit';

        // Simulate request from a completely different IP
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5'; // prefix '10.0' → mismatch

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);

        $this->assertTrue($result);
    }

    public function testRequestIsHijackingAttemptReturnsTrueOnUserAgentChange(): void
    {
        session_start();
        $_SESSION['sessionadmin']['ipPrefix']  = '192.168'; // same IP
        $_SESSION['sessionadmin']['userAgent'] = 'PHPUnit';

        $_SERVER['HTTP_USER_AGENT'] = 'EvilBrowser/1.0';

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);

        $this->assertTrue($result);
    }

    public function testRequestIsHijackingAttemptThrowsOnInvalidOctetRange(): void
    {
        session_start();
        $_SESSION = [];

        $admin                 = $this->getSessionAdminConcrete();
        $admin->ipOctetsToCheck = 1; // below the valid 2-4 range

        $this->expectException(SessionAdminException::class);
        $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);
    }

    public function testRequestIsHijackingAttemptThrowsWhenOctetsTooHigh(): void
    {
        session_start();
        $_SESSION = [];

        $admin                 = $this->getSessionAdminConcrete();
        $admin->ipOctetsToCheck = 5; // above the valid 2-4 range

        $this->expectException(SessionAdminException::class);
        $this->method($admin, 'requestIsHijackingAttempt')->invoke($admin);
    }

    public function testHijackingAttemptCausesGuestSessionReset(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $admin->activateSession();
        $admin->createUserSession(99);

        $this->assertTrue($_SESSION['sessionadmin']['isUser']);

        // requestIsHijackingAttempt() stores the fingerprint on its first call.
        // Seed it here to represent the fingerprint stored on the previous (normal) request.
        $_SESSION['sessionadmin']['ipPrefix']  = '192.168';
        $_SESSION['sessionadmin']['userAgent'] = 'PHPUnit';

        // Simulate the next request arriving from a different IP
        $_SERVER['REMOTE_ADDR'] = '10.0.0.99';

        $this->method($admin, 'checkTime')->invoke($admin);

        $this->assertFalse($_SESSION['sessionadmin']['isUser']);
        $this->assertSame('you are a guest', $_SESSION['sessionadmin']['msg']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // IP ADDRESS HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    public function testGetIpAddressNoProxiesReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpAddressNoProxies')->invoke($admin);

        $this->assertSame('10.0.0.5', $result);
    }

    public function testGetIpAddressProxyAwarePrefersForwardedHeader(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpAddressProxyAware')->invoke($admin);

        $this->assertSame('203.0.113.50', $result);
    }

    public function testGetIpAddressProxyAwareFallsBackToRemoteAddr(): void
    {
        // No proxy headers → REMOTE_ADDR is the only source
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpAddressProxyAware')->invoke($admin);

        $this->assertSame('192.168.1.100', $result);
    }

    public function testGetIpAddressProxyAwareHandlesCommaSeparatedList(): void
    {
        // First IP in the list is the client
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 10.0.0.1, 10.0.0.2';

        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpAddressProxyAware')->invoke($admin);

        $this->assertSame('198.51.100.1', $result);
    }

    public function testGetIpPrefixExtracts2Octets(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpPrefix')->invoke($admin, '192.168.1.100', 2);

        $this->assertSame('192.168', $result);
    }

    public function testGetIpPrefixExtracts3Octets(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpPrefix')->invoke($admin, '192.168.1.100', 3);

        $this->assertSame('192.168.1', $result);
    }

    public function testGetIpPrefixExtracts4Octets(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpPrefix')->invoke($admin, '192.168.1.100', 4);

        $this->assertSame('192.168.1.100', $result);
    }

    public function testGetIpPrefixHandlesIPv6(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getIpPrefix')->invoke($admin, '2001:db8::1', 2);

        $this->assertSame('2001:db8', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STRING UTILITIES
    // ═══════════════════════════════════════════════════════════════════════

    public function testGetSubStrAfterLastReturnsSegmentAfterLastSlash(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getSubStrAfterLast')
            ->invoke($admin, 'folder/subfolder/file.php', '/');

        $this->assertSame('file.php', $result);
    }

    public function testGetSubStrAfterLastReturnsSegmentAfterLastDot(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getSubStrAfterLast')
            ->invoke($admin, 'a.b.c.extension', '.');

        $this->assertSame('extension', $result);
    }

    public function testGetSubStrAfterLastReturnsOriginalStringWhenNeedleAbsent(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getSubStrAfterLast')
            ->invoke($admin, 'nodots', '/');

        $this->assertSame('nodots', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COOKIE WRAPPER
    // ═══════════════════════════════════════════════════════════════════════

    public function testSetCookieStoresAllAttributes(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $this->method($admin, 'setCookie')
            ->invoke($admin, 'session_token', 'abc123', 3600, '/', '', false, true);

        $this->assertArrayHasKey('session_token', $admin->cookies);
        $c = $admin->cookies['session_token'];
        $this->assertSame('abc123', $c['value']);
        $this->assertSame(3600, $c['expiresOrOptions']);
        $this->assertSame('/', $c['path']);
        $this->assertEmpty($c['domain']);
        $this->assertFalse($c['secure']);
        $this->assertTrue($c['httponly']);
    }

    public function testGetCookieReturnsValuePreviouslySet(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $this->method($admin, 'setCookie')->invoke($admin, 'tok', 'val123', 3600, '/', '', false, true);

        $result = $this->method($admin, 'getCookie')->invoke($admin, 'tok');

        $this->assertSame('val123', $result);
    }

    public function testGetCookieReturnsNullForMissingCookie(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getCookie')->invoke($admin, 'missing');

        $this->assertNull($result);
    }

    public function testGetCookieReturnsCustomDefaultForMissingCookie(): void
    {
        $admin  = $this->getSessionAdminConcrete();
        $result = $this->method($admin, 'getCookie')->invoke($admin, 'missing', 'fallback');

        $this->assertSame('fallback', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════

    public function testConstructorAppliesAllConfigValues(): void
    {
        $config = [
            'sessionLifetime' => 999,
            'allowedURLs'     => ['index.php', 'contact.php'],
            'keys'            => ['foo' => 'bar', 'lang' => 'en'],
        ];

        $admin = $this->getSessionAdminConcrete($config);
        $rc    = new \ReflectionClass($admin);

        $this->assertSame(999, $rc->getProperty('sessionLifetime')->getValue($admin));
        $this->assertSame(['index.php', 'contact.php'], $rc->getProperty('allowedUrls')->getValue($admin));
        $this->assertSame(['foo' => 'bar', 'lang' => 'en'], $rc->getProperty('keys')->getValue($admin));
    }

    public function testDefaultConfigurationValues(): void
    {
        $admin = $this->getSessionAdminConcrete();
        $rc    = new \ReflectionClass($admin);

        $this->assertSame(2400, $rc->getProperty('sessionLifetime')->getValue($admin));
        $this->assertSame('SESSION', $rc->getProperty('sessionName')->getValue($admin));
        $this->assertSame('/', $rc->getProperty('path')->getValue($admin));
        $this->assertNull($rc->getProperty('domain')->getValue($admin));
        $this->assertNull($rc->getProperty('secure')->getValue($admin));
    }

    public function testPublicFlagDefaults(): void
    {
        $admin = $this->getSessionAdminConcrete();

        $this->assertTrue($admin->appIsSpa);
        $this->assertFalse($admin->useAuthorization);
        $this->assertTrue($admin->useTabIndexation);
        $this->assertTrue($admin->proxyAwareIpDetection);
        $this->assertSame(2, $admin->ipOctetsToCheck);
        $this->assertSame([], $admin->ignoreInAuthorization);
        $this->assertTrue($admin->terminateRedirects);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONTRACTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SessionAdmin must implement SessionInterface so consumers can type-hint
     * the contract instead of the concrete class.
     */
    public function testSessionAdminImplementsSessionInterface(): void
    {
        $admin = $this->getSessionAdminConcrete();

        $this->assertInstanceOf(SessionInterface::class, $admin);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CUSTOM SESSION HANDLER
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * setSessionHandler() must register the given handler so PHP calls it
     * when the session is started via activateSession().
     */
    public function testSetSessionHandlerIsCalledDuringActivateSession(): void
    {
        $opened = false;

        $handler = new class($opened) implements \SessionHandlerInterface {
            /** @param bool $opened Passed by reference; set to true on open(). */
            public function __construct(private bool &$opened) {}

            /** @inheritDoc */
            public function open(string $path, string $name): bool
            {
                $this->opened = true;
                return true;
            }

            /** @inheritDoc */
            public function close(): bool { return true; }

            /** @inheritDoc */
            public function read(string $id): string|false { return ''; }

            /** @inheritDoc */
            public function write(string $id, string $data): bool { return true; }

            /** @inheritDoc */
            public function destroy(string $id): bool { return true; }

            /** @inheritDoc */
            public function gc(int $max_lifetime): int|false { return 0; }
        };

        $admin = $this->getSessionAdminConcrete();
        $admin->setSessionHandler($handler);
        $admin->activateSession();

        $this->assertTrue($opened, 'Custom session handler open() was not called during activateSession()');

        // Restore the native handler so subsequent tests are not affected.
        session_write_close();
        session_set_save_handler(new \SessionHandler());
    }

    /**
     * setSessionHandler() must accept any SessionHandlerInterface without
     * throwing, even when called before activateSession().
     */
    public function testSetSessionHandlerAcceptsValidHandlerWithoutThrowing(): void
    {
        $handler = new class implements \SessionHandlerInterface {
            /** @inheritDoc */
            public function open(string $path, string $name): bool { return true; }

            /** @inheritDoc */
            public function close(): bool { return true; }

            /** @inheritDoc */
            public function read(string $id): string|false { return ''; }

            /** @inheritDoc */
            public function write(string $id, string $data): bool { return true; }

            /** @inheritDoc */
            public function destroy(string $id): bool { return true; }

            /** @inheritDoc */
            public function gc(int $max_lifetime): int|false { return 0; }
        };

        $admin = $this->getSessionAdminConcrete();

        // Must not throw
        $admin->setSessionHandler($handler);

        $this->assertTrue(true);

        // Restore native handler.
        session_set_save_handler(new \SessionHandler());
    }
}

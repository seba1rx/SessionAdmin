<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Seba1rx\SessionAdmin\TabManager;
use Seba1rx\SessionAdmin\Contracts\TabStorageInterface;

#[CoversClass(TabManager::class)]
final class TabManagerTest extends TestCase
{
    // ── Valid UUIDv4 fixtures ─────────────────────────────────────────────
    // Format: 8-4-4-4-12 hex, version digit = 4, variant nibble in [89ab]

    private const TAB_A = '550e8400-e29b-41d4-a716-446655440000';
    private const TAB_B = '6ba7b814-9dad-41d1-a0b4-00c04fd430c8';
    private const TAB_C = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    private const TAB_D = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const TAB_E = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    // ── setUp ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_COOKIE  = [];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONSTRUCTOR
    // ═══════════════════════════════════════════════════════════════════════

    public function testConstructorCreatesEmptyTabsBucket(): void
    {
        $tm  = new TabManager();
        $key = $tm->getSessionKey();

        $this->assertArrayHasKey($key, $_SESSION);
        $this->assertIsArray($_SESSION[$key]);
        $this->assertEmpty($_SESSION[$key]);
    }

    public function testConstructorDoesNotOverwriteExistingTabData(): void
    {
        // Pre-seed a tab entry before constructing a second instance
        $_SESSION['tabs'][self::TAB_A] = ['data' => ['x' => 1], 'is_active' => true, 'last_active' => time()];

        new TabManager(); // second construction must not wipe the bucket

        $this->assertArrayHasKey(self::TAB_A, $_SESSION['tabs']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SESSION KEY
    // ═══════════════════════════════════════════════════════════════════════

    public function testGetSessionKeyReturnsExpectedValue(): void
    {
        $this->assertSame('tabs', (new TabManager())->getSessionKey());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // indexNewTab
    // ═══════════════════════════════════════════════════════════════════════

    public function testIndexNewTabCreatesCorrectStructure(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);

        $entry = $_SESSION['tabs'][self::TAB_A];
        $this->assertIsArray($entry['data']);
        $this->assertTrue($entry['is_active']);
        $this->assertIsInt($entry['last_active']);
    }

    public function testIndexNewTabDoesNotOverwriteExistingEntry(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);

        // Manually add data and index again — data must survive
        $_SESSION['tabs'][self::TAB_A]['data']['key'] = 'value';
        $tm->indexNewTab(self::TAB_A);

        $this->assertSame('value', $_SESSION['tabs'][self::TAB_A]['data']['key']);
    }

    public function testIndexingMultipleTabsDoesNotDestroyOthers(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);
        $tm->indexNewTab(self::TAB_B);
        $tm->indexNewTab(self::TAB_C);

        $this->assertArrayHasKey(self::TAB_A, $_SESSION['tabs']);
        $this->assertArrayHasKey(self::TAB_B, $_SESSION['tabs']);
        $this->assertArrayHasKey(self::TAB_C, $_SESSION['tabs']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // set / get
    // ═══════════════════════════════════════════════════════════════════════

    public function testSetAndGetRoundTripForCurrentTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();

        $tm->set('username', 'seba');

        $this->assertSame('seba', $tm->get('username'));
    }

    public function testSetCreatesTabEntryIfNotPreviouslyIndexed(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();
        $tm->set('x', 42);

        $entry = $_SESSION['tabs'][self::TAB_A];
        $this->assertArrayHasKey('data', $entry);
        $this->assertArrayHasKey('is_active', $entry);
        $this->assertArrayHasKey('last_active', $entry);
        $this->assertTrue($entry['is_active']);
    }

    public function testSetUpdatesLastActiveTimestamp(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();

        $before = time();
        $tm->set('k', 'v');
        $after = time();

        $ts = $_SESSION['tabs'][self::TAB_A]['last_active'];
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function testSetDoesNothingWhenCookieIsAbsent(): void
    {
        $tm = new TabManager();
        $tm->set('k', 'v');

        $this->assertEmpty($_SESSION['tabs']);
    }

    public function testSetDoesNothingWhenCookieContainsInvalidUuid(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'not-a-valid-uuid';
        $tm = new TabManager();
        $tm->set('k', 'v');

        $this->assertEmpty($_SESSION['tabs']);
    }

    public function testGetReturnsNullDefaultWhenKeyAbsent(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();

        $this->assertNull($tm->get('missing'));
    }

    public function testGetReturnsCustomDefaultWhenKeyAbsent(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();

        $this->assertSame('fallback', $tm->get('missing', 'fallback'));
    }

    public function testGetReturnsNullDefaultWhenNoCookiePresent(): void
    {
        $tm = new TabManager();

        $this->assertNull($tm->get('anything'));
    }

    public function testGetReturnsCustomDefaultWhenNoCookiePresent(): void
    {
        $tm = new TabManager();

        $this->assertSame(0, $tm->get('counter', 0));
    }

    public function testGetIgnoresInvalidUuidCookie(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = 'bad-uuid';
        $tm = new TabManager();

        $this->assertSame('x', $tm->get('key', 'x'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // destroyTabSession
    // ═══════════════════════════════════════════════════════════════════════

    public function testDestroyTabSessionRemovesAllTabData(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_C;
        $tm = new TabManager();
        $tm->set('token', 'abc123');

        $this->assertArrayHasKey(self::TAB_C, $_SESSION['tabs']);

        $tm->destroyTabSession(self::TAB_C);

        $this->assertArrayNotHasKey(self::TAB_C, $_SESSION['tabs']);
    }

    public function testDestroyTabSessionOnNonExistentTabDoesNotThrow(): void
    {
        $tm = new TabManager();
        $tm->destroyTabSession('non-existent-id');

        // Verify nothing was added either
        $this->assertEmpty($_SESSION['tabs']);
    }

    public function testDestroyTabSessionOnlyRemovesTargetTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();
        $tm->set('a', 1);
        $tm->indexNewTab(self::TAB_B);

        $tm->destroyTabSession(self::TAB_A);

        $this->assertArrayNotHasKey(self::TAB_A, $_SESSION['tabs']);
        $this->assertArrayHasKey(self::TAB_B, $_SESSION['tabs']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // markInactiveTab
    // ═══════════════════════════════════════════════════════════════════════

    public function testMarkInactiveTabSetsIsActiveFalse(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_D;
        $tm = new TabManager();
        $tm->set('k', 'v');

        $this->assertTrue($_SESSION['tabs'][self::TAB_D]['is_active']);

        $tm->markInactiveTab(self::TAB_D);

        $this->assertFalse($_SESSION['tabs'][self::TAB_D]['is_active']);
    }

    public function testMarkInactiveTabOnNonExistentTabDoesNotThrow(): void
    {
        $tm = new TabManager();
        $tm->markInactiveTab('ghost-tab');

        $this->assertEmpty($_SESSION['tabs']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // cleanupInactiveTabs
    // ═══════════════════════════════════════════════════════════════════════

    public function testCleanupInactiveTabsRemovesOldInactiveTabs(): void
    {
        $tm = new TabManager();
        // Manually seed an old inactive tab (last_active = 2 hours ago)
        $_SESSION['tabs'][self::TAB_A] = [
            'data'        => ['x' => 1],
            'is_active'   => false,
            'last_active' => time() - 7200,
        ];

        $removed = $tm->cleanupInactiveTabs(3600); // threshold: 1 hour

        $this->assertSame(1, $removed);
        $this->assertArrayNotHasKey(self::TAB_A, $_SESSION['tabs']);
    }

    public function testCleanupInactiveTabsDoesNotRemoveRecentInactiveTabs(): void
    {
        $tm = new TabManager();
        // Inactive tab but last_active only 10 minutes ago
        $_SESSION['tabs'][self::TAB_A] = [
            'data'        => [],
            'is_active'   => false,
            'last_active' => time() - 600,
        ];

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::TAB_A, $_SESSION['tabs']);
    }

    public function testCleanupInactiveTabsNeverRemovesActiveTabs(): void
    {
        $tm = new TabManager();
        // Active tab — even if it's "old", it must not be removed
        $_SESSION['tabs'][self::TAB_A] = [
            'data'        => [],
            'is_active'   => true,
            'last_active' => time() - 86400,
        ];

        $removed = $tm->cleanupInactiveTabs(60);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::TAB_A, $_SESSION['tabs']);
    }

    public function testCleanupInactiveTabsReturnsCountOfRemovedTabs(): void
    {
        $tm    = new TabManager();
        $stale = time() - 7200;

        $_SESSION['tabs'][self::TAB_A] = ['data' => [], 'is_active' => false, 'last_active' => $stale];
        $_SESSION['tabs'][self::TAB_B] = ['data' => [], 'is_active' => false, 'last_active' => $stale];
        $_SESSION['tabs'][self::TAB_C] = ['data' => [], 'is_active' => true,  'last_active' => $stale]; // active → skip
        $_SESSION['tabs'][self::TAB_D] = ['data' => [], 'is_active' => false, 'last_active' => time()]; // recent → skip

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(2, $removed);
        $this->assertArrayNotHasKey(self::TAB_A, $_SESSION['tabs']);
        $this->assertArrayNotHasKey(self::TAB_B, $_SESSION['tabs']);
        $this->assertArrayHasKey(self::TAB_C, $_SESSION['tabs']);
        $this->assertArrayHasKey(self::TAB_D, $_SESSION['tabs']);
    }

    public function testCleanupInactiveTabsOnEmptyBucketReturnsZero(): void
    {
        $tm = new TabManager();

        $this->assertSame(0, $tm->cleanupInactiveTabs(3600));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // debug
    // ═══════════════════════════════════════════════════════════════════════

    public function testDebugReturnsExpectedStructureForSingleTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_E;
        $tm = new TabManager();
        $tm->set('foo', 'bar');
        $tm->markInactiveTab(self::TAB_E);

        $debug = $tm->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey(self::TAB_E, $debug);

        $entry = $debug[self::TAB_E];
        $this->assertFalse($entry['is_active']);
        $this->assertIsString($entry['last_active']); // formatted date string
        $this->assertIsArray($entry['keys']);
        $this->assertContains('foo', $entry['keys']);
        $this->assertIsInt($entry['size']);
        $this->assertGreaterThan(0, $entry['size']);
    }

    public function testDebugReturnsAllTrackedTabs(): void
    {
        // Set data for two different tabs
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tmA = new TabManager();
        $tmA->set('color', 'red');

        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_B;
        $tmB = new TabManager();
        $tmB->set('color', 'blue');

        // debug() reads the shared $_SESSION['tabs'] bucket
        $debug = $tmA->debug();

        $this->assertCount(2, $debug);
        $this->assertArrayHasKey(self::TAB_A, $debug);
        $this->assertArrayHasKey(self::TAB_B, $debug);
    }

    public function testDebugReturnsEmptyArrayWhenNoTabs(): void
    {
        $tm = new TabManager();

        $this->assertSame([], $tm->debug());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // generateUuid
    // ═══════════════════════════════════════════════════════════════════════

    public function testGenerateUuidProducesValidV4Uuid(): void
    {
        $tm   = new TabManager();
        $uuid = $tm->generateUuid();

        $this->assertTrue($tm->isValidUuid($uuid));
    }

    public function testGenerateUuidMatchesExpectedFormat(): void
    {
        $tm   = new TabManager();
        $uuid = $tm->generateUuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateUuidProducesUniqueValues(): void
    {
        $tm    = new TabManager();
        $uuids = array_map($tm->generateUuid(...), range(1, 20));

        $this->assertCount(20, array_unique($uuids));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // isValidUuid
    // ═══════════════════════════════════════════════════════════════════════

    public function testIsValidUuidReturnsTrueForAllFixtures(): void
    {
        $tm = new TabManager();

        $this->assertTrue($tm->isValidUuid(self::TAB_A));
        $this->assertTrue($tm->isValidUuid(self::TAB_B));
        $this->assertTrue($tm->isValidUuid(self::TAB_C));
        $this->assertTrue($tm->isValidUuid(self::TAB_D));
        $this->assertTrue($tm->isValidUuid(self::TAB_E));
    }

    public function testIsValidUuidIsCaseInsensitive(): void
    {
        $tm = new TabManager();

        $this->assertTrue($tm->isValidUuid(strtoupper(self::TAB_A)));
        $this->assertTrue($tm->isValidUuid(strtolower(self::TAB_A)));
    }

    public function testIsValidUuidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse((new TabManager())->isValidUuid(''));
    }

    public function testIsValidUuidReturnsFalseForArbitraryString(): void
    {
        $tm = new TabManager();

        $this->assertFalse($tm->isValidUuid('not-a-uuid'));
        $this->assertFalse($tm->isValidUuid('12345678-1234-1234-1234-123456789012')); // version 1, not 4
        $this->assertFalse($tm->isValidUuid('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx')); // non-hex chars
    }

    public function testIsValidUuidRejectV1WhenOnlyV4Required(): void
    {
        $v1 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; // version field = '1'
        $tm  = new TabManager();

        $this->assertFalse($tm->isValidUuid($v1, true));  // v4-only mode → rejected
        $this->assertTrue($tm->isValidUuid($v1, false));  // any-version mode → accepted
    }

    // ═══════════════════════════════════════════════════════════════════════
    // isTabIndexed
    // ═══════════════════════════════════════════════════════════════════════

    public function testIsTabIndexedReturnsFalseWhenNoCookiePresent(): void
    {
        $tm = new TabManager();

        $this->assertFalse($tm->isTabIndexed());
    }

    public function testIsTabIndexedReturnsFalseForUnindexedTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();

        $this->assertFalse($tm->isTabIndexed());
    }

    public function testIsTabIndexedReturnsTrueAfterIndexing(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);

        $this->assertTrue($tm->isTabIndexed());
    }

    public function testIsTabIndexedResolvesExplicitTabId(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_B);

        $this->assertTrue($tm->isTabIndexed(self::TAB_B));
        $this->assertFalse($tm->isTabIndexed(self::TAB_C));
    }

    public function testIsTabIndexedReturnsFalseAfterDestroy(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);
        $tm->destroyTabSession(self::TAB_A);

        $this->assertFalse($tm->isTabIndexed());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // constructor autoIndex
    // ═══════════════════════════════════════════════════════════════════════

    public function testConstructorAutoIndexIndexesCurrentTab(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager(autoIndex: true);

        $this->assertTrue($tm->isTabIndexed());
        $this->assertArrayHasKey(self::TAB_A, $_SESSION['tabs']);
    }

    public function testConstructorAutoIndexDoesNothingWithoutCookie(): void
    {
        new TabManager(autoIndex: true);

        $this->assertEmpty($_SESSION['tabs']);
    }

    public function testConstructorAutoIndexFalseDoesNotAutoIndex(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager(autoIndex: false);

        $this->assertFalse($tm->isTabIndexed());
    }

    public function testConstructorAutoIndexDoesNotOverwriteExistingEntry(): void
    {
        $_COOKIE['SESSIONADMIN_TABID'] = self::TAB_A;
        $tm = new TabManager();
        $tm->indexNewTab(self::TAB_A);
        $_SESSION['tabs'][self::TAB_A]['data']['preserve'] = 'me';

        new TabManager(autoIndex: true); // second construction must not wipe existing data

        $this->assertSame('me', $_SESSION['tabs'][self::TAB_A]['data']['preserve']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONTRACTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * TabManager must implement TabStorageInterface so consumers can type-hint
     * the contract instead of the concrete class.
     */
    public function testTabManagerImplementsTabStorageInterface(): void
    {
        $this->assertInstanceOf(TabStorageInterface::class, new TabManager());
    }
}

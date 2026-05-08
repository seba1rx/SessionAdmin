<?php

namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\Contracts\TabStorageInterface;

/**
 * Provides per-tab session isolation using a browser cookie and a JS client.
 *
 * Data is stored under $_SESSION[$sessionKey][$tabId] where $tabId is a UUIDv4
 * read from the SESSIONADMIN_TABID cookie set by seba1rx_sessionAdmin.js.
 *
 * Implements TabStorageInterface so consumers can type-hint the contract
 * without depending on the concrete class.
 */
class TabManager implements TabStorageInterface
{
    /**
     * Key used as the root index in $_SESSION for all tab data.
     *
     * @var string
     */
    protected string $sessionKey = 'tabs';

    /**
     * Initialises the tab storage bucket in $_SESSION if not already present.
     *
     * @param bool $autoIndex When true, the tab identified by the SESSIONADMIN_TABID
     *                        cookie is automatically indexed and touched on every request.
     *                        "Touch" means last_active is refreshed and is_active is set
     *                        to true, so the cleanup TTL reflects "last seen" rather than
     *                        "last data write". This also re-activates a tab that was
     *                        temporarily marked inactive by a beforeunload beacon that
     *                        fired during a page refresh.
     */
    public function __construct(bool $autoIndex = false)
    {
        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        if ($autoIndex) {
            $tabId = $this->getTabId();
            if ($tabId !== null) {
                $this->indexNewTab($tabId);
                $this->touchTab($tabId);
            }
        }
    }

    /**
     * Creates the session entry for the given tab ID if it does not already exist.
     *
     * @param string $tabId UUIDv4 identifying the browser tab.
     * @return void
     */
    public function indexNewTab(string $tabId): void
    {
        if (!isset($_SESSION[$this->sessionKey][$tabId])) {
            $_SESSION[$this->sessionKey][$tabId] = [
                'data'        => [],
                'is_active'   => true,
                'last_active' => time(),
            ];
        }
    }

    /**
     * Returns the current tab ID from the SESSIONADMIN_TABID cookie.
     *
     * Returns null if the cookie is absent or does not contain a valid UUIDv4.
     *
     * @return string|null
     */
    protected function getTabId(): ?string
    {
        $tabId = $_COOKIE['SESSIONADMIN_TABID'] ?? null;
        if ($tabId === null) {
            return null;
        }
        return $this->isValidUuid($tabId) ? $tabId : null;
    }

    /**
     * Returns true if the given tab ID has an entry in the session store.
     *
     * When $tabId is null the method resolves the current tab ID from the
     * SESSIONADMIN_TABID cookie. Returns false when no valid UUID is found.
     *
     * @param string|null $tabId UUIDv4 to check; null resolves to the current cookie.
     * @return bool
     */
    public function isTabIndexed(?string $tabId = null): bool
    {
        $id = $tabId ?? $this->getTabId();
        if ($id === null) {
            return false;
        }
        return isset($_SESSION[$this->sessionKey][$id]);
    }

    /**
     * Stores a key/value pair in the current tab's session data.
     *
     * Auto-initialises the tab entry if not yet indexed.
     * Does nothing when no valid tab ID cookie is present.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $tabId = $this->getTabId();
        if (!$tabId) {
            return;
        }

        $this->indexNewTab($tabId);

        $_SESSION[$this->sessionKey][$tabId]['data'][$key]    = $value;
        $_SESSION[$this->sessionKey][$tabId]['is_active']      = true;
        $_SESSION[$this->sessionKey][$tabId]['last_active']    = time();
    }

    /**
     * Retrieves a value from the current tab's session data.
     *
     * @param string $key
     * @param mixed  $default Returned when the key is absent or no valid tab ID is found.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tabId = $this->getTabId();
        if (!$tabId) {
            return $default;
        }
        return $_SESSION[$this->sessionKey][$tabId]['data'][$key] ?? $default;
    }

    /**
     * Removes all session data for the given tab.
     *
     * @param string $tabId
     * @return void
     */
    public function destroyTabSession(string $tabId): void
    {
        unset($_SESSION[$this->sessionKey][$tabId]);
    }

    /**
     * Marks a tab as inactive and records the close time.
     *
     * Called when the browser fires the beforeunload event. Updates last_active
     * to the current time so that cleanupInactiveTabs() measures the threshold
     * from the moment the tab was closed, not from its last data-write.
     *
     * @param string $tabId
     * @return void
     */
    public function markInactiveTab(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey][$tabId])) {
            $_SESSION[$this->sessionKey][$tabId]['is_active']   = false;
            $_SESSION[$this->sessionKey][$tabId]['last_active'] = time();
        }
    }

    /**
     * Marks a tab as active and refreshes its last_active timestamp.
     *
     * Used internally by the constructor when $autoIndex is true so that
     * last_active tracks "last seen" rather than "last data write". Can also
     * be called directly to keep a tab alive during long-running operations.
     *
     * @param string $tabId
     * @return void
     */
    public function touchTab(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey][$tabId])) {
            $_SESSION[$this->sessionKey][$tabId]['is_active']   = true;
            $_SESSION[$this->sessionKey][$tabId]['last_active'] = time();
        }
    }

    /**
     * Removes tab entries that have been inactive for longer than the given threshold.
     *
     * Useful for preventing session bloat in apps where users frequently open new tabs.
     * Only tabs with is_active === false AND last_active older than $olderThanSeconds are removed.
     * Active tabs are never touched.
     *
     * @param int $olderThanSeconds Remove inactive tabs not seen for at least this many seconds.
     * @return int Number of tabs removed.
     */
    public function cleanupInactiveTabs(int $olderThanSeconds): int
    {
        $cutoff  = time() - $olderThanSeconds;
        $removed = 0;

        foreach ($_SESSION[$this->sessionKey] ?? [] as $tabId => $data) {
            if (
                !($data['is_active'] ?? true) &&
                ($data['last_active'] ?? PHP_INT_MAX) < $cutoff
            ) {
                unset($_SESSION[$this->sessionKey][$tabId]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Generates a RFC 4122 compliant UUIDv4.
     *
     * @return string Lowercase UUID, e.g. '6ff19a11-97cb-4060-b68f-3b81836ec5f0'
     * @throws \Exception If random_bytes() fails to generate sufficient entropy.
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);

        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40);
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80);

        return \sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    /**
     * Validates whether a string is a well-formed UUID.
     *
     * @param string $uuid
     * @param bool   $onlyV4 When true (default), only UUIDv4 is accepted.
     * @return bool
     */
    public function isValidUuid(string $uuid, bool $onlyV4 = true): bool
    {
        $pattern = $onlyV4
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
            : '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return (bool) preg_match($pattern, $uuid);
    }
}

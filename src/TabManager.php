<?php
namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\Exceptions\SessionAdminException;

/**
 * SessionAdminServer
 * Provides per-tab session isolation using a browser cookie and JS client
 */
class TabManager
{
    protected string $sessionKey = 'tabs';

    public function __construct()
    {
        error_log("## tab manager: construct");
        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    /**
     * Creates the tab id index in the tabs index in the $_SESSION array
     *
     * @param string $tabId
     * @return void
     */
    public function indexNewTab(string $tabId): void
    {
        error_log("## tab manager: adding new tab index: {$tabId}");
        if (!isset($_SESSION[$this->sessionKey][$tabId])) {
            $_SESSION[$this->sessionKey]['sessionadmin'] = [];
            $_SESSION[$this->sessionKey]['sessionadmin'][$tabId] = [];
            // $tabId = $this->getTabId();
            // if (!$tabId) return;

            $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['data'] = [];
            $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['is_active'] = true;
            $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['last_active'] = time();

            error_log("## tab manager: items: " . json_encode($_SESSION[$this->sessionKey]));
        }
    }

    /**
     * Get current tab ID from cookie
     *
     * @return string|null
     */
    protected function getTabId(): ?string
    {
        return $_COOKIE['SESSIONADMIN_TABID'] ?? null;
    }

    /**
     * Set session data for this tab
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $tabId = $this->getTabId();
        if (!$tabId) return;

        $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['data'][$key] = $value;
        $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['is_active'] = true;
        $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['last_active'] = time();
    }

    /**
     * Get session data for this tab
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tabId = $this->getTabId();
        return $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['data'][$key] ?? $default;
    }

    /**
     * Destroy all session data for a given tab
     *
     * @param string $tabId
     * @return void
     */
    public function destroyTabSession(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey]['sessionadmin'][$tabId])) {
            unset($_SESSION[$this->sessionKey]['sessionadmin'][$tabId]);
        }
    }

    /**
     * Mark a tab as inactive (used on beforeunload)
     *
     * @param string $tabId
     * @return void
     */
    public function markInactiveTab(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey]['sessionadmin'][$tabId])) {
            $_SESSION[$this->sessionKey]['sessionadmin'][$tabId]['is_active'] = false;
        }
    }

    /**
     * Return all tab session data for debugging
     *
     * @return array
     */
    public function debug(): array
    {
        $result = [];
        foreach ($_SESSION[$this->sessionKey]['sessionadmin'] ?? [] as $tabId => $data) {
            $result[$tabId] = [
                'is_active' => $data['is_active'] ?? false,
                'last_active' => date('Y-m-d H:i:s', $data['last_active'] ?? 0),
                'keys' => isset($data['data']) ? array_keys($data['data']) : [],
                'size' => isset($data['data']) ? strlen(json_encode($data['data'])) : 0,
            ];
        }

        return $result;
    }

    /**
     * Gets the key used to index the tabs
     *
     * @return string
     */
    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    /**
     * Generates a UUID v4 (format: 8-4-4-4-12, example: 6ff19a11-97cb-4060-b68f-3b81836ec5f0)
     *
     * @return string UUID v4 lowercase
     * @throws SessionAdminException
     */
    function uuid_v4(): string {
        $data = random_bytes(16);

        // Adjust the version to  0100 (v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Adjust the variation to 10xx (RFC 4122)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Format as hexadecimal string
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    /**
     * Validates if a string is indeed a UUID (v1..v5)
     *
     * @param string $uuid
     * @param bool $onlyV4
     * @return bool
     */
    function is_valid_uuid(string $uuid, bool $onlyV4 = true): bool {
        $pattern = $onlyV4
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
            : '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return (bool) preg_match($pattern, $uuid);
    }

}
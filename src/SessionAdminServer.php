<?php
namespace Seba1rx\SessionAdmin;

/**
 * SessionAdminServer
 * Provides per-tab session isolation using a browser cookie and JS client
 */
class SessionAdminServer
{
    protected string $sessionKey = '__sessionadmin_tabs';

    public function __construct()
    {
        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    /**
     * Get current tab ID from cookie
     */
    protected function getTabId(): ?string
    {
        error_log("## cookies: " . json_encode($_COOKIE));
        return $_COOKIE['SESSIONADMIN_TABID'] ?? null;
    }

    /**
     * Set session data for this tab
     */
    public function set(string $key, $value): void
    {
        $tabId = $this->getTabId();
        error_log("## tabId pre");
        if (!$tabId) return;

        error_log("## tabId {$tabId} setting {$key} => {$value}");
        $_SESSION[$this->sessionKey][$tabId]['_data'][$key] = $value;
        $_SESSION[$this->sessionKey][$tabId]['_active'] = true;
        $_SESSION[$this->sessionKey][$tabId]['_last_active'] = time();
    }

    /**
     * Get session data for this tab
     */
    public function get(string $key, $default = null)
    {
        $tabId = $this->getTabId();
        return $_SESSION[$this->sessionKey][$tabId]['_data'][$key] ?? $default;
    }

    /**
     * Destroy all session data for a given tab
     */
    public function destroyTabSession(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey][$tabId])) {
            unset($_SESSION[$this->sessionKey][$tabId]);
        }
    }

    /**
     * Mark a tab as inactive (used on beforeunload)
     */
    public function markInactiveTab(string $tabId): void
    {
        if (isset($_SESSION[$this->sessionKey][$tabId])) {
            $_SESSION[$this->sessionKey][$tabId]['_active'] = false;
        }
    }

    /**
     * Return all tab session data for debugging
     */
    public function debug(): array
    {
        $result = [];

        foreach ($_SESSION[$this->sessionKey] ?? [] as $tabId => $data) {
            $result[$tabId] = [
                'active' => $data['_active'] ?? false,
                'last_active' => date('Y-m-d H:i:s', $data['_last_active'] ?? 0),
                'keys' => isset($data['_data']) ? array_keys($data['_data']) : [],
                'size' => isset($data['_data']) ? strlen(json_encode($data['_data'])) : 0,
            ];
        }

        return $result;
    }

    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }
}

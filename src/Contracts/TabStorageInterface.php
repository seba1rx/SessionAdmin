<?php

declare(strict_types=1);

namespace Seba1rx\SessionAdmin\Contracts;

/**
 * Contract for per-browser-tab scoped session storage.
 *
 * Type-hint against this interface to decouple application code from the
 * concrete TabManager implementation — useful in service classes, middleware,
 * and unit tests that need to mock tab-scoped persistence.
 *
 * The "current tab" is resolved from the SESSIONADMIN_TABID cookie set by
 * the seba1rx_sessionAdmin.js client. Methods that resolve the current tab
 * silently do nothing (set) or return the default (get) when no valid tab
 * UUID is present in the cookie.
 */
interface TabStorageInterface
{
    /**
     * Stores a key/value pair in the current tab's scoped session data.
     *
     * Auto-initialises the tab entry if not yet indexed. Does nothing when
     * no valid SESSIONADMIN_TABID cookie is present.
     *
     * @param string $key
     * @param mixed  $value Any serialisable value.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retrieves a value from the current tab's scoped session data.
     *
     * Returns $default when the key is absent or when no valid tab ID cookie
     * is present.
     *
     * @param string $key
     * @param mixed  $default Value returned when the key or tab is not found.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Returns true if the current (or given) tab has an entry in the session store.
     *
     * When $tabId is null the method resolves the current tab from the
     * SESSIONADMIN_TABID cookie. Returns false when no valid UUID is found.
     *
     * @param string|null $tabId UUIDv4 to check; null resolves to the current cookie.
     * @return bool
     */
    public function isTabIndexed(?string $tabId = null): bool;

    /**
     * Removes inactive tab entries that have not been seen for longer than the threshold.
     *
     * Only tabs with is_active === false AND last_active older than $olderThanSeconds
     * are removed. Active tabs are never touched regardless of their age.
     *
     * @param int $olderThanSeconds Minimum inactivity age in seconds before removal.
     * @return int Number of tab entries removed.
     */
    public function cleanupInactiveTabs(int $olderThanSeconds): int;
}

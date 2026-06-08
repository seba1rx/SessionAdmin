<?php

declare(strict_types=1);

namespace Seba1rx\SessionAdmin\Contracts;

/**
 * Contract for a per-browser-tab session handler.
 *
 * Implementations isolate session data under a unique UUID assigned to each
 * browser tab by the JS client. SessionAdmin accepts any conforming handler
 * via setTabHandler() and never depends on the concrete class.
 *
 * All methods that resolve "the current tab" do so by reading the tab ID from
 * the request (typically an HTTP header set by the JS client, with a cookie as
 * fallback). The resolution strategy is implementation-defined.
 */
interface TabHandlerInterface
{
    /**
     * Registers a tab entry in the session store.
     *
     * Idempotent: calling this more than once for the same $tabId must not
     * overwrite existing data or alter the tab's state.
     *
     * @param string $tabId A validated UUID v4 identifying the browser tab.
     * @return void
     */
    public function indexNewTab(string $tabId): void;

    /**
     * Refreshes the tab's last-active timestamp and marks it as active.
     *
     * Used by the JS heartbeat to keep the tab alive in the session without
     * modifying stored data. If the tab is not yet registered, implementations
     * may create it (idempotent registration).
     *
     * @param string $tabId A validated UUID v4 identifying the browser tab.
     * @return void
     */
    public function touchTab(string $tabId): void;

    /**
     * Stores a value in the current tab's data slot.
     *
     * The current tab is resolved from the request (header then cookie).
     * Must be a no-op when the tab is not registered, to prevent implicit
     * session slot creation from arbitrary header or cookie values.
     *
     * @param string $key   The key to store the value under.
     * @param mixed  $value The value to store.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retrieves a value from the current tab's data slot.
     *
     * The current tab is resolved from the request (header then cookie).
     * Returns $default when the key is absent or the tab is not registered.
     *
     * @param string $key     The key to look up.
     * @param mixed  $default Returned when the key or tab is not found.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Returns whether a tab entry exists in the session store.
     *
     * When called without an argument the current tab is resolved from the
     * request (header then cookie). Returns false if no ID can be resolved.
     * A tab is considered indexed regardless of its is_active status.
     *
     * @param string|null $tabId Specific UUID to check, or null to use the current tab.
     * @return bool
     */
    public function isTabIndexed(?string $tabId = null): bool;

    /**
     * Marks a tab as inactive without deleting its data.
     *
     * Typically called when the JS client fires the beforeunload event. The
     * tab entry is preserved so it can be reactivated (e.g. on bfcache restore)
     * or cleaned up later by cleanupInactiveTabs().
     *
     * @param string $tabId A validated UUID v4 identifying the browser tab.
     * @return void
     */
    public function markInactiveTab(string $tabId): void;

    /**
     * Permanently removes all session data for a tab.
     *
     * Unlike markInactiveTab(), this is irreversible. Should be a no-op when
     * the tab is not registered.
     *
     * @param string $tabId A validated UUID v4 identifying the browser tab.
     * @return void
     */
    public function destroyTabSession(string $tabId): void;

    /**
     * Removes inactive tabs whose last-active timestamp is older than the given threshold.
     *
     * Only tabs with is_active === false AND last_active older than
     * (time() - $olderThanSeconds) are removed. Active tabs are never
     * removed regardless of age. Values <= 0 must be treated as a no-op.
     *
     * @param int $olderThanSeconds Remove inactive tabs older than this many seconds.
     * @return int Number of tab entries removed.
     */
    public function cleanupInactiveTabs(int $olderThanSeconds): int;
}

/**
 * SessionAdminClient
 *
 * Ensures every browser tab has a stable UUID and keeps the server
 * informed about tab lifecycle events (open / close).
 *
 * Include in your HTML:
 *   <script src="/assets/seba1rx_sessionAdmin.js"></script>
 *
 * Optional window flags (set BEFORE the script loads):
 *   window.SESSIONADMIN_AUTO_DESTROY = true;  // notify server on tab close (beforeunload)
 *   window.SESSIONADMIN_DEBUG        = true;  // enable console logging
 *
 * The tab UUID is available in PHP on every request as:
 *   $_COOKIE['SESSIONADMIN_TABID']
 */
const SessionAdminClient = {

    tab: {
        /** @type {string|null} Current tab UUID */
        id: null,

        /** @type {boolean} True when the UUID was generated this session (vs. restored from storage) */
        isNew: false,

        /**
         * Generates a cryptographically random UUIDv4 using the Web Crypto API.
         * crypto.randomUUID() is available in all modern browsers (Chrome 92+, Firefox 95+, Safari 15.4+).
         *
         * @returns {string}
         */
        generateUuid: () => crypto.randomUUID(),

        /**
         * Resolves the tab UUID using sessionStorage and the navigation type:
         *  - 'reload'      — plain refresh: preserve the existing UUID (same tab, same identity)
         *  - anything else — fresh open, duplicate tab, or back/forward: generate a new UUID
         *
         * Using the PerformanceNavigationTiming type fixes the duplicate-tab case: browsers
         * copy sessionStorage when duplicating a tab, but the navigation type is 'navigate',
         * so a fresh UUID is generated instead of reusing the copied one.
         *
         * Sets tab.isNew = true whenever a new UUID is generated.
         *
         * Note: 'unique-tab-id' is the browser-side storage key (internal to this script).
         * The server-facing name is the cookie 'SESSIONADMIN_TABID', set separately in init().
         *
         * window.name is intentionally NOT used as a fallback: it persists across same-tab
         * navigations to different origins, making it readable/writable by other sites.
         */
        assignTabUuid: () => {
            const STORAGE_KEY = 'unique-tab-id';
            const stored  = window.sessionStorage.getItem(STORAGE_KEY);
            const navType = window.performance?.getEntriesByType?.('navigation')?.[0]?.type;
            const isNew   = !stored || navType !== 'reload';

            const uid = isNew ? SessionAdminClient.tab.generateUuid() : stored;

            window.sessionStorage.setItem(STORAGE_KEY, uid);
            SessionAdminClient.tab.id    = uid;
            SessionAdminClient.tab.isNew = isNew;
        },
    },

    cookie: {
        /**
         * @param {string} name
         * @param {string} value
         * @param {number} [days=1]
         */
        set: (name, value, days = 1) => {
            const expires = new Date(Date.now() + days * 864e5);
            document.cookie =
                `${encodeURIComponent(name)}=${encodeURIComponent(value)}` +
                `; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
        },

        /** @returns {string|null} */
        get: (name) => {
            const m = document.cookie.match(
                new RegExp('(^| )' + encodeURIComponent(name) + '=([^;]+)')
            );
            return m ? decodeURIComponent(m[2]) : null;
        },
    },

    /**
     * Sends a JSON payload via sendBeacon with the correct Content-Type.
     * Beacon fires-and-forgets — the response is intentionally ignored.
     *
     * @param {string} url
     * @param {object} data
     */
    _beacon: (url, data) => {
        try {
            navigator.sendBeacon(
                url,
                new Blob([JSON.stringify(data)], { type: 'application/json' })
            );
        } catch (e) {
            console.warn('[SessionAdminClient] Beacon failed for', url, e);
        }
    },

    /** Notifies the server that this tab has been opened / is active. */
    notifyNewTab: () => {
        SessionAdminClient._beacon('/sessionadmin/new-tab', {
            tab_id: SessionAdminClient.tab.id,
        });
    },

    /** Notifies the server that this tab is closing (beforeunload). */
    notifyTabClosed: () => {
        SessionAdminClient._beacon('/sessionadmin/tab-close', {
            tab_id: SessionAdminClient.tab.id,
        });
    },

    /**
     * Bootstraps the client:
     *  - resolves / generates the tab UUID (sessionStorage + navigation type)
     *  - syncs the SESSIONADMIN_TABID cookie
     *  - notifies the server when the tab UUID is new (new tab, duplicate, or stale cookie)
     *  - registers the beforeunload handler when SESSIONADMIN_AUTO_DESTROY === true
     */
    init: () => {
        SessionAdminClient.tab.assignTabUuid();

        const tabId  = SessionAdminClient.tab.id;
        const COOKIE = 'SESSIONADMIN_TABID';
        const prev   = SessionAdminClient.cookie.get(COOKIE);

        // Keep the cookie in sync with sessionStorage
        if (prev !== tabId) {
            SessionAdminClient.cookie.set(COOKIE, tabId);
        }

        // Notify the server only when the tab is new or the cookie was stale.
        // Skips the network call on plain page refreshes.
        if (SessionAdminClient.tab.isNew || prev !== tabId) {
            SessionAdminClient.notifyNewTab();
        }

        // Register close notification only when explicitly enabled
        if (window.SESSIONADMIN_AUTO_DESTROY === true) {
            window.addEventListener('beforeunload', SessionAdminClient.notifyTabClosed);
        }

        if (window.SESSIONADMIN_DEBUG) {
            console.log(
                '[SessionAdminClient] Tab UUID:', tabId,
                SessionAdminClient.tab.isNew ? '(new)' : '(existing)'
            );
        }
    },
};

// Guard against the script loading after DOMContentLoaded has already fired
// (e.g. when placed at end of <body> or loaded with defer).
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', SessionAdminClient.init);
} else {
    SessionAdminClient.init();
}

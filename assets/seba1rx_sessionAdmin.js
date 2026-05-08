/**
 * SessionAdminClient
 *
 * Ensures every browser tab has a stable UUID and keeps the server
 * informed about tab lifecycle events (open / close).
 *
 * Include in your HTML — flags must be set BEFORE the script loads,
 * because init() runs synchronously as soon as the script is parsed:
 *
 *   <script>
 *       window.SESSIONADMIN_AUTO_DESTROY = true; // optional
 *   </script>
 *   <script src="/assets/seba1rx_sessionAdmin.js"></script>
 *
 * Optional window flags:
 *   window.SESSIONADMIN_AUTO_DESTROY = true;  // notify server on tab close (beforeunload)
 *
 * The tab UUID is available in PHP on every request as:
 *   $_COOKIE['SESSIONADMIN_TABID']
 */
const SessionAdminClient = {

    tab: {
        /** @type {string|null} Current tab UUID */
        id: null,

        /** @type {boolean} True when a new UUID was generated (vs. restored from storage) */
        isNew: false,

        /**
         * Generates a cryptographically random UUIDv4 using the Web Crypto API.
         * crypto.randomUUID() is available in all modern browsers (Chrome 92+, Firefox 95+, Safari 15.4+).
         *
         * @returns {string}
         */
        generateUuid: () => crypto.randomUUID(),

        /**
         * Resolves the tab UUID from sessionStorage or generates a fresh one.
         *
         * Uses the legacy Navigation Timing API (performance.navigation.type) to
         * distinguish reloads from new navigations and tab duplications:
         *
         *   TYPE_NAVIGATE (0) — fresh navigation, OR a duplicated tab.
         *     Browsers do not copy sessionStorage to duplicated tabs, so a stored
         *     UUID is absent and a new one is generated. If sessionStorage somehow
         *     holds a value under navType 0 (e.g. some browser quirk), we still
         *     generate a new UUID to give the duplicate its own identity.
         *
         *   TYPE_RELOAD (1) — F5, Ctrl+R, Ctrl+Shift+R, location.reload().
         *     sessionStorage is preserved across reloads, so the existing UUID is
         *     always reused.
         *
         *   TYPE_BACK_FORWARD (2) — browser history navigation.
         *     sessionStorage is preserved; reuse the existing UUID.
         *
         * When the API is unavailable (navType === undefined), falls back to the
         * original logic: isNew = !stored (safe, preserves UUID when present).
         *
         * Note: 'unique-tab-id' is the browser-side storage key (internal).
         * The server-facing name is the cookie 'SESSIONADMIN_TABID', set in init().
         *
         * window.name is intentionally NOT used: it persists across same-tab
         * navigations to different origins, making it readable/writable by other sites.
         */
        assignTabUuid: () => {
            const STORAGE_KEY = 'unique-tab-id';
            const stored  = window.sessionStorage.getItem(STORAGE_KEY);

            // Legacy API — available synchronously before DOMContentLoaded.
            // Default to undefined-safe "keep UUID" behaviour when API is absent.
            const navType  = performance.navigation?.type;
            const isReload = navType === undefined || navType === 1 || navType === 2;

            // Generate a new UUID when:
            //   - sessionStorage is empty (fresh tab or discarded tab), OR
            //   - navType is TYPE_NAVIGATE (0): new tab or duplicated tab.
            // Reloads and back/forward always preserve the existing UUID.
            const isNew = !stored || !isReload;
            const uid   = isNew ? SessionAdminClient.tab.generateUuid() : stored;

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
     *  - resolves / generates the tab UUID (sessionStorage + navType)
     *  - syncs the SESSIONADMIN_TABID cookie
     *  - notifies the server when the tab UUID is genuinely new
     *  - registers the beforeunload handler when SESSIONADMIN_AUTO_DESTROY === true
     *
     * Runs synchronously when the script is parsed — no DOM access required.
     * All flags (e.g. SESSIONADMIN_AUTO_DESTROY) must be set before the script loads.
     */
    init: () => {
        SessionAdminClient.tab.assignTabUuid();

        const tabId  = SessionAdminClient.tab.id;
        const COOKIE = 'SESSIONADMIN_TABID';
        const prev   = SessionAdminClient.cookie.get(COOKIE);

        // Keep the cookie in sync with the current tab UUID.
        if (prev !== tabId) {
            SessionAdminClient.cookie.set(COOKIE, tabId);
        }

        // Notify the server when the UUID is new or the cookie was stale.
        // Skips the network call on plain page reloads.
        if (SessionAdminClient.tab.isNew || prev !== tabId) {
            SessionAdminClient.notifyNewTab();
        }

        // Register close notification only when explicitly enabled.
        if (window.SESSIONADMIN_AUTO_DESTROY === true) {
            window.addEventListener('beforeunload', SessionAdminClient.notifyTabClosed);
        }
    },
};

// init() only touches sessionStorage, cookies, sendBeacon, and performance.navigation —
// none require the DOM. Run synchronously so the cookie is correct for the next request.
SessionAdminClient.init();

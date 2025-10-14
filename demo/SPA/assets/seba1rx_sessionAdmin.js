/**
 * SessionAdminClient
 *
 * This script ensures that each browser tab has its own unique ID and
 * that this ID is also sent to the backend through a cookie.
 *
 * Move this file to your assets directory and include it in your main HTML file:
 * <script src="/assets/seba1rx_sessionadmin.js"></script>
 */
const SessionAdminClient = {
    /**
     * Tab Uuid
     * Tool to set an id to each tab.
     * This per-tab UUID mechanism lets you isolate session data and
     * prevents state bleed between tabs.
     *
     * Usage:
     * to assign the id to the tab just do:
     * * SessionAdminClient.tab.assignTabUuid();
     *
     * if you ever need to get the id just do:
     * * SessionAdminClient.tab.id;
     *
     * In the backend you will be able to get this id on each request as:
     * * $_COOKIE['session_tab_id'] (PHP example)
     */
    tab: {
        /**
         * Tab unique identifier
         * @type {string|null}
         */
        id: null,
        /**
         * Generates a UUID v4-like identifier
         * @returns {string}
         */
        generateUuid: () => {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },
        /**
         * Assigns or retrieves the tab UUID.
         * Called automatically on script load.
         */
        assignTabUuid: () => {
            let uid = window.sessionStorage.getItem('unique-tab-id');

            // Generate a new one if missing or window.name is not set
            if (!uid || !window.name) {
                uid = SessionAdminClient.tab.generateUuid();
                window.sessionStorage.setItem('unique-tab-id', uid);
                window.name = uid;
            }

            // Sync both sources
            SessionAdminClient.tab.id = uid;
            window.name = uid;
        },
    },
    /**
     * Cookie utilities
     */
    cookie: {
        /**
         * Sets a cookie.
         * @param {string} name
         * @param {string} value
         * @param {number} days Expiration in days (optional)
         */
        set: (name, value, days = 1) => {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
        },

        /**
         * Reads a cookie value by name.
         * @param {string} name
         * @returns {string|null}
         */
        get: (name) => {
            const match = document.cookie.match(new RegExp('(^| )' + encodeURIComponent(name) + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }
    },
    notifyTabClosed: () => {
        try {
            const url = '/sessionadmin/tab-close'; // endpoint in your backend
            const data = { tab_id: SessionAdminClient.tab.id };
            navigator.sendBeacon(url, JSON.stringify(data));
        } catch (e) {
            console.warn('[SessionAdminClient] Could not send tab close event:', e);
        }
    },
    /**
     * Initializes the session admin client:
     * - Assigns tab UUID
     * - Sets the identifying cookie
     */
    init: () => {
        SessionAdminClient.tab.assignTabUuid();
        const tabId = SessionAdminClient.tab.id;
        const cookieName = 'session_tab_id';
        const currentCookie = SessionAdminClient.cookie.get(cookieName);

        if (currentCookie !== tabId) {
            SessionAdminClient.cookie.set(cookieName, tabId);
        }

        // Notify backend softly when tab is closing
        window.addEventListener('beforeunload', () => {
            SessionAdminClient.notifyTabClosed();
        });

        console.log('[SessionAdminClient] Tab UUID:', tabId);
    }
};

// Run automatically on load
document.addEventListener('DOMContentLoaded', SessionAdminClient.init);
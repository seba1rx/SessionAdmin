<?php
/**
 * SessionAdmin bootstrap
 *
 * Registers internal endpoints consumed by seba1rx_sessionAdmin.js:
 *
 *   POST /sessionadmin/new-tab    index a new browser tab
 *   POST /sessionadmin/tab-close  mark a tab as inactive
 *
 * Loaded automatically via composer autoload.files — no require needed.
 * The session must already be active before any of these endpoints run.
 * Returns immediately (via `return`) when the request URI is not ours.
 */

use Seba1rx\SessionAdmin\TabManager;

if (!defined('__SEBA1RX_SESSIONADMIN_BOOTSTRAPPED__')) {
    define('__SEBA1RX_SESSIONADMIN_BOOTSTRAPPED__', true);

    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!preg_match('~^/sessionadmin(/|$)~', $uri)) {
        return; // not our endpoint — let the application handle it
    }

    // ── Internal helpers (static class to avoid polluting the global namespace) ──

    final class SessionAdminBootstrap
    {
        /** Sends a JSON response and terminates the script. */
        public static function json(mixed $data, int $status = 200): never
        {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data);
            exit;
        }

        /**
         * Ensures a PHP session is active before handling an internal endpoint.
         *
         * bootstrap.php is loaded via composer autoload.files, which means it can
         * run before the application has had a chance to call activateSession().
         * To handle this, we try session_start() ourselves if no session is active.
         * The application must have called session_name() with the correct name before
         * triggering autoload; otherwise PHP uses the default name (PHPSESSID).
         *
         * Aborts with 503 if a session cannot be established.
         */
        public static function requireSession(): void
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                self::json(['error' => 'No active session'], 503);
            }
        }

        /**
         * Reads and validates the tab_id from the JSON request body.
         * Returns null when the body is malformed or the UUID is invalid.
         */
        public static function tabIdFromBody(): ?string
        {
            $input = json_decode(file_get_contents('php://input'), true);
            $tabId = $input['tab_id'] ?? null;

            if (!is_string($tabId) || $tabId === '') {
                return null;
            }

            return (new TabManager())->isValidUuid($tabId) ? $tabId : null;
        }
    }

    // ── POST /sessionadmin/new-tab ────────────────────────────────────────────

    if ($method === 'POST' && preg_match('~^/sessionadmin/new-tab/?$~', $uri)) {
        SessionAdminBootstrap::requireSession();
        $tabId = SessionAdminBootstrap::tabIdFromBody();
        if ($tabId) {
            (new TabManager())->indexNewTab($tabId);
        }
        SessionAdminBootstrap::json(['status' => 'ok']);
    }

    // ── POST /sessionadmin/tab-close ──────────────────────────────────────────

    if ($method === 'POST' && preg_match('~^/sessionadmin/tab-close/?$~', $uri)) {
        SessionAdminBootstrap::requireSession();
        $tabId = SessionAdminBootstrap::tabIdFromBody();
        if ($tabId) {
            (new TabManager())->markInactiveTab($tabId);
        }
        SessionAdminBootstrap::json(['status' => 'ok']);
    }
}

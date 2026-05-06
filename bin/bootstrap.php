<?php
/**
 * SessionAdmin bootstrap
 *
 * Registers internal endpoints consumed by seba1rx_sessionAdmin.js:
 *
 *   POST /sessionadmin/new-tab            index a new browser tab
 *   POST /sessionadmin/tab-close          mark a tab as inactive
 *   GET  /sessionadmin/debug              JSON or HTML debug view (restricted)
 *   POST /sessionadmin/debug/delete-tab   destroy tab data (restricted)
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
         * Aborts with 403 unless debug access is allowed.
         * Access is granted when:
         *  - the PHP constant SESSIONADMIN_DEBUG is defined and true, OR
         *  - the request originates from localhost (127.0.0.1, ::1, or IPv4-mapped ::ffff:127.0.0.1)
         */
        public static function requireDebugAccess(): void
        {
            $allowed =
                (defined('SESSIONADMIN_DEBUG') && SESSIONADMIN_DEBUG === true) ||
                in_array(
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    ['127.0.0.1', '::1', '::ffff:127.0.0.1'],
                    true
                );

            if (!$allowed) {
                self::json(['error' => 'Forbidden'], 403);
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

    // ── POST /sessionadmin/debug/delete-tab ───────────────────────────────────

    if ($method === 'POST' && preg_match('~^/sessionadmin/debug/delete-tab/?$~', $uri)) {
        SessionAdminBootstrap::requireSession();
        SessionAdminBootstrap::requireDebugAccess();
        $tabId = SessionAdminBootstrap::tabIdFromBody();
        if (!$tabId) {
            SessionAdminBootstrap::json(['error' => 'Invalid or missing tab_id'], 400);
        }
        (new TabManager())->destroyTabSession($tabId);
        SessionAdminBootstrap::json(['status' => 'deleted', 'tab_id' => $tabId]);
    }

    // ── GET /sessionadmin/debug ───────────────────────────────────────────────

    if ($method === 'GET' && preg_match('~^/sessionadmin/debug/?$~', $uri)) {
        SessionAdminBootstrap::requireSession();
        SessionAdminBootstrap::requireDebugAccess();

        $tabManager   = new TabManager();
        $tabs         = $tabManager->debug();
        $currentTabId = $_COOKIE['SESSIONADMIN_TABID'] ?? null;

        if (defined('SESSIONADMIN_DEBUG_UI') && SESSIONADMIN_DEBUG_UI === true) {
            // ── HTML mode ────────────────────────────────────────────────────
            header('Content-Type: text/html; charset=utf-8');
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SessionAdmin — Tab Debug</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body   { font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; padding: 2rem; margin: 0; }
        h1     { font-size: 1.4rem; margin: 0 0 .25rem; }
        .meta  { color: #71717a; font-size: .85rem; margin-bottom: 1.5rem; }
        .toolbar { display: flex; gap: .5rem; margin-bottom: 1rem; align-items: center; }
        .btn   { padding: .35rem .75rem; border: 1px solid #d4d4d8; border-radius: 6px;
                 background: #fff; cursor: pointer; font-size: .85rem; text-decoration: none; color: inherit; }
        .btn:hover { background: #f0f0f0; }
        .btn-danger { border-color: #fca5a5; background: #fff; color: #dc2626; }
        .btn-danger:hover { background: #fee2e2; }
        table  { border-collapse: collapse; width: 100%; background: #fff;
                 border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        th, td { padding: .65rem 1rem; border-bottom: 1px solid #e4e4e7; text-align: left; vertical-align: middle; }
        th     { background: #fafafa; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #52525b; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        code   { font-size: .8rem; background: #f4f4f5; padding: .1rem .3rem; border-radius: 3px; }
        .badge { display: inline-block; padding: .15rem .45rem; border-radius: 999px;
                 font-size: .72rem; font-weight: 600; line-height: 1.4; }
        .badge-green  { background: #dcfce7; color: #15803d; }
        .badge-red    { background: #fee2e2; color: #b91c1c; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .empty { text-align: center; padding: 2rem; color: #a1a1aa; font-size: .9rem; }
        .footer { margin-top: 1rem; font-size: .78rem; color: #a1a1aa; }
    </style>
</head>
<body>
<h1>Tab Manager Debug</h1>
<p class="meta">
    Package: <strong>seba1rx/sessionadmin</strong> &nbsp;·&nbsp;
    Session key: <code><?= htmlspecialchars($tabManager->getSessionKey()) ?></code>
    <?php if ($currentTabId): ?>
        &nbsp;·&nbsp; Your tab: <code><?= htmlspecialchars($currentTabId) ?></code>
    <?php endif; ?>
</p>

<div class="toolbar">
    <a href="/sessionadmin/debug" class="btn">&#8635; Refresh</a>
    <span style="color:#a1a1aa;font-size:.85rem"><?= count($tabs) ?> tab(s) tracked</span>
</div>

<?php if (empty($tabs)): ?>
    <div class="empty">No tabs are currently tracked in this session.</div>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Tab UUID</th>
            <th>Status</th>
            <th>Last active</th>
            <th>Keys</th>
            <th>Size (bytes)</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="tabs-body">
    <?php foreach ($tabs as $tabId => $info): ?>
        <tr id="row-<?= htmlspecialchars($tabId) ?>">
            <td>
                <code><?= htmlspecialchars($tabId) ?></code>
                <?php if ($tabId === $currentTabId): ?>
                    <span class="badge badge-blue" title="This is the tab making this request">you</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($info['is_active']): ?>
                    <span class="badge badge-green">Active</span>
                <?php else: ?>
                    <span class="badge badge-red">Inactive</span>
                <?php endif; ?>
            </td>
            <td style="font-size:.85rem;color:#52525b"><?= htmlspecialchars($info['last_active']) ?></td>
            <td style="font-family:monospace;font-size:.82rem">
                <?= $info['keys'] ? htmlspecialchars(implode(', ', $info['keys'])) : '<span style="color:#a1a1aa">—</span>' ?>
            </td>
            <td><?= (int) $info['size'] ?></td>
            <td>
                <button class="btn btn-danger"
                        onclick="deleteTab('<?= htmlspecialchars($tabId) ?>', this)">Delete</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="footer">Generated at <?= date('Y-m-d H:i:s') ?> &nbsp;·&nbsp; Session ID: <code><?= session_id() ?></code></div>

<script>
    async function deleteTab(tabId, btn) {
        if (!confirm('Delete session data for tab ' + tabId + '?')) return;
        btn.disabled    = true;
        btn.textContent = 'Deleting…';

        try {
            const res = await fetch('/sessionadmin/debug/delete-tab', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ tab_id: tabId }),
            });

            if (res.ok) {
                const row = document.getElementById('row-' + tabId);
                if (row) row.style.opacity = '0.3';
                setTimeout(() => location.reload(), 350);
            } else {
                const err = await res.json().catch(() => ({ error: 'Unknown error' }));
                alert('Delete failed: ' + (err.error ?? res.status));
                btn.disabled    = false;
                btn.textContent = 'Delete';
            }
        } catch (e) {
            alert('Request error: ' + e.message);
            btn.disabled    = false;
            btn.textContent = 'Delete';
        }
    }
</script>
</body>
</html>
            <?php
            exit;
        }

        // ── JSON mode ─────────────────────────────────────────────────────────
        SessionAdminBootstrap::json([
            'package'     => 'seba1rx/sessionadmin',
            'session_key' => $tabManager->getSessionKey(),
            'current_tab' => $currentTabId,
            'tabs'        => $tabs,
        ]);
    }
}

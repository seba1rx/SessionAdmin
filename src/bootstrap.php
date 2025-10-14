<?php
/**
 * SessionAdminServer bootstrap
 *
 * Registers internal endpoints:
 *  - POST /sessionadmin/tab-close
 *  - GET  /sessionadmin/debug  (JSON or HTML if SESSIONADMIN_DEBUG_UI)
 *  - POST /sessionadmin/debug/delete-tab
 */

use Seba1rx\SessionAdmin\SessionAdminServer;

if (!defined('__SEBA1RX_SESSIONADMIN_BOOTSTRAPPED__')) {
    define('__SEBA1RX_SESSIONADMIN_BOOTSTRAPPED__', true);

    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // --- TAB CLOSE ENDPOINT ---------------------------------------------------
    if ($method === 'POST' && preg_match('~^/sessionadmin/tab-close/?$~', $uri)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $tabId = $input['tab_id'] ?? null;

        if ($tabId) {
            $admin = new SessionAdminServer();
            $admin->markInactiveTab($tabId);
        }

        http_response_code(204);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // --- DELETE TAB ENDPOINT ---------------------------------------------------
    if ($method === 'POST' && preg_match('~^/sessionadmin/debug/delete-tab/?$~', $uri)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $tabId = $input['tab_id'] ?? null;

        $allowed =
            (defined('SESSIONADMIN_DEBUG') && SESSIONADMIN_DEBUG === true) ||
            in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);

        if (!$allowed) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        if ($tabId) {
            $admin = new SessionAdminServer();
            $admin->destroyTabSession($tabId);
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'deleted', 'tab_id' => $tabId]);
        exit;
    }

    // --- DEBUG ENDPOINT -------------------------------------------------------
    if ($method === 'GET' && preg_match('~^/sessionadmin/debug/?$~', $uri)) {
        $allowed =
            (defined('SESSIONADMIN_DEBUG') && SESSIONADMIN_DEBUG === true) ||
            in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);

        if (!$allowed) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $admin = new SessionAdminServer();
        $tabs = $admin->debug();

        // --- HTML MODE --------------------------------------------------------
        if (defined('SESSIONADMIN_DEBUG_UI') && SESSIONADMIN_DEBUG_UI === true) {
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <title>SessionAdminServer Debug</title>
                <style>
                    body { font-family: system-ui, sans-serif; background: #f9f9f9; color: #333; padding: 2rem; }
                    h1 { font-size: 1.6rem; margin-bottom: 1rem; }
                    table { border-collapse: collapse; width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                    th, td { padding: 0.7rem 1rem; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
                    th { background: #fafafa; font-weight: 600; }
                    tr:hover { background: #f5f5f5; }
                    .active { color: #0a0; font-weight: bold; }
                    .inactive { color: #a00; font-weight: bold; }
                    .timestamp { color: #555; font-size: 0.9rem; }
                    .keys { font-family: monospace; color: #444; font-size: 0.9rem; }
                    .footer { margin-top: 1rem; font-size: 0.8rem; color: #777; }
                    button { background: #e33; color: white; border: none; border-radius: 6px; padding: 0.4rem 0.7rem; cursor: pointer; transition: 0.2s; }
                    button:hover { background: #c00; }
                </style>
            </head>
            <body>
                <h1>SessionAdminServer Debug</h1>
                <table>
                    <thead>
                        <tr>
                            <th>Tab UUID</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th>Keys</th>
                            <th>Size (bytes)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tabs-body">
                    <?php foreach ($tabs as $tabId => $info): ?>
                        <tr data-tab="<?= htmlspecialchars($tabId) ?>">
                            <td><code><?= htmlspecialchars($tabId) ?></code></td>
                            <td class="<?= $info['active'] ? 'active' : 'inactive' ?>">
                                <?= $info['active'] ? 'Active' : 'Inactive' ?>
                            </td>
                            <td class="timestamp"><?= htmlspecialchars($info['last_active']) ?></td>
                            <td class="keys"><?= htmlspecialchars(implode(', ', $info['keys'])) ?: '—' ?></td>
                            <td><?= (int)$info['size'] ?></td>
                            <td><button onclick="deleteTab('<?= htmlspecialchars($tabId) ?>', this)">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="footer">
                    <p><strong>Package:</strong> seba1rx/sessionadmin</p>
                    <p>Generated at <?= date('Y-m-d H:i:s') ?></p>
                </div>

                <script>
                    async function deleteTab(tabId, btn) {
                        if (!confirm('Delete session data for this tab?')) return;

                        btn.disabled = true;
                        btn.textContent = 'Deleting...';

                        const response = await fetch('/sessionadmin/debug/delete-tab', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ tab_id: tabId })
                        });

                        if (response.ok) {
                            const row = btn.closest('tr');
                            row.style.background = '#fee';
                            setTimeout(() => row.remove(), 300);
                        } else {
                            alert('Failed to delete tab.');
                            btn.disabled = false;
                            btn.textContent = 'Delete';
                        }
                    }
                </script>
            </body>
            </html>
            <?php
            exit;
        }

        // --- JSON MODE --------------------------------------------------------
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'package' => 'seba1rx/sessionadmin',
            'version' => '1.0.0',
            'session_key' => $admin->getSessionKey(),
            'tabs' => $tabs,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

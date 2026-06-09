<?php
/**
 * index.php — Integration demo entry point
 *
 * INTEGRATION: SessionAdmin starts the session; SessionAdminBridge injects
 * TabManager as a plugin. Both share $_SESSION without conflict.
 *
 * Key points:
 *   1. setTabHandler() is called BEFORE activateSession()
 *   2. SessionAdmin configures the session name and cookie params first
 *   3. SessionAdminBridge defers session_start() to activateSession()
 *   4. autoCleanupTabs = 30 fires cleanupInactiveTabs(30) on every request
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;

// INTEGRATION: wire the bridge before activateSession().
$session = new MySession();
$session->setTabHandler(new SessionAdminBridge()); // inject — does NOT start session yet
$session->activateSession();                        // sets cookie name/lifetime, starts session,
                                                    // then calls cleanupInactiveTabs(30)

// Handle auth form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login' && !empty($_POST['email'])) {
        // Simulate a successful login. createUserSession() regenerates the session ID.
        $session->createUserSession(42);
        $_SESSION['user'] = [
            'name'  => 'Demo User',
            'email' => htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8'),
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'logout') {
        // terminate() wipes all session data and opens a fresh guest session.
        // In SPA mode (default) it does not redirect — we do it manually.
        $session->terminate();
        header('Location: index.php');
        exit;
    }
}

$isUser = $_SESSION['sessionadmin']['isUser'] ?? false;

// Pre-load session as JSON so the page can render without a round-trip.
$initialSession = json_encode($_SESSION, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SessionAdmin + TabManager — Integration Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--
        INTEGRATION: configure the JS client before loading it.

        TABMANAGER_HEARTBEAT_URL overrides the default /tabmanager/heartbeat
        endpoint, which is unreachable with `php -S` (no router).
        heartbeat.php replicates the same logic at a directly accessible path.

        In a real app with a front-controller router you do NOT need this —
        the default endpoint is registered automatically by the bootstrap.
    -->
    <script>
        window.TABMANAGER_DEBUG          = true;
        window.TABMANAGER_HEARTBEAT_URL  = 'heartbeat.php';
        window.TABMANAGER_TAB_STATUS_URL = 'tab_status.php';
    </script>

    <!--
        INTEGRATION: load the JS client.
        Copied to the project root by tabmanager's Composer post-install script.
    -->
    <script src="seba1rx_tabmanagerclient.js"></script>

    <style>
        body { background: #f0f2f5; }
        .navbar-brand span.pkg  { font-size: .75rem; opacity: .6; font-weight: 400; }
        .tab-uuid { font-family: monospace; letter-spacing: .03em; font-size: .95rem; }
        #session_data .empty-state { color: #aaa; }
        .key-cell   { font-family: monospace; font-size: .85rem; color: #6c757d; }
        .value-cell { font-weight: 500; }
        .card { border: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <span class="navbar-brand mb-0">
            SessionAdmin + TabManager
            <span class="pkg ms-2">seba1rx/sessionadmin · seba1rx/tabmanager</span>
        </span>
        <a href="debug.php" target="_blank" class="btn btn-sm btn-outline-light">
            Debug view
        </a>
    </div>
</nav>

<div class="container py-4" style="max-width: 760px;">

    <!-- AUTH CARD: shared across all tabs (lives in $_SESSION['sessionadmin']) -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <?php if ($isUser): ?>
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <div class="text-muted small mb-1">Session auth <span class="badge bg-success ms-1">user</span></div>
                        <span class="fw-semibold"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Demo User') ?></span>
                        <span class="text-muted small ms-2">ID: <?= (int) ($_SESSION['sessionadmin']['id_user'] ?? 0) ?></span>
                    </div>
                    <form method="POST" class="ms-auto mb-0">
                        <input type="hidden" name="action" value="logout">
                        <button class="btn btn-sm btn-outline-danger">Log out</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <div class="text-muted small mb-1">Session auth <span class="badge bg-secondary ms-1">guest</span></div>
                        <span class="text-muted">Not logged in</span>
                    </div>
                    <button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#login-modal">
                        Log in
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB UUID CARD: isolated per browser tab (lives in $_SESSION['tabmanager']) -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <div class="text-muted small mb-1">Current tab UUID</div>
                    <span id="tabid" class="tab-uuid text-primary">—</span>
                </div>
                <span id="tab-status" class="badge bg-success ms-auto">active</span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-3">
        <button id="btn-add" class="btn btn-primary" onclick="addData()">
            <span id="btn-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
            Add random data
        </button>
        <button class="btn btn-outline-danger" onclick="reset()">Reset session</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
            <span class="fw-semibold">Tab session data <span class="text-muted fw-normal small">(all tabs)</span></span>
            <span id="data-count" class="badge bg-secondary rounded-pill">0</span>
        </div>
        <div id="session_data" class="card-body p-0">
            <p class="text-muted p-3 mb-0 empty-state">
                No data yet — click <strong>Add random data</strong> to populate this tab's session.
            </p>
        </div>
    </div>

</div>

<!-- Login modal -->
<div class="modal fade" id="login-modal" tabindex="-1" aria-labelledby="login-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="login-modal-label">Log in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="modal-body">
                    <p class="text-muted small">Any email and password work in this demo.</p>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="demo@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" value="secret">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log in</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.__INITIAL_SESSION__ = <?= $initialSession ?>;</script>
<script src="app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

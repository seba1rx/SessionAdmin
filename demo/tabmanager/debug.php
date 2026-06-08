<?php
/**
 * debug.php — Visual debug interface showing both session slices.
 *
 * Shows:
 *   - SessionAdmin state ($_SESSION['sessionadmin']) — auth, timing, unique ID
 *   - TabManager state ($_SESSION['tabmanager']) — all tabs, status, stored data
 *
 * Handles AJAX tab delete (POST to itself) since /tabmanager/debug/delete-tab
 * is not reachable with `php -S`.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;
use Seba1rx\TabManager\TabManager;

$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

// Handle AJAX delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input     = json_decode(file_get_contents('php://input'), true);
    $deleteId  = $input['tab_id'] ?? '';

    if (($input['action'] ?? '') === 'delete'
        && !empty($deleteId)
        && TabManager::isValidTabId($deleteId)
    ) {
        $session->tabHandler->destroyTabSession($deleteId);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'deleted', 'tab_id' => $deleteId]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$rawTabs      = $_SESSION['tabmanager']['tabs'] ?? [];
$sessionAdmin = $_SESSION['sessionadmin'] ?? [];
$debugSummary = $session->tabHandler->debug();
$now          = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Integration Demo — Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .uuid { font-family: monospace; font-size: .85rem; }
        .data-key { font-family: monospace; color: #6c757d; font-size: .85rem; }
        .age { font-size: .8rem; color: #999; }
        .card { border: none; }
        .tab-row { transition: background .3s; }
        pre { font-size: .8rem; background: #fff; border-radius: .5rem; padding: 1rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <span class="navbar-brand mb-0">
            Integration Demo <span style="font-size:.75rem;opacity:.6;font-weight:400">debug</span>
        </span>
        <a href="index.php" class="btn btn-sm btn-outline-light">← Demo</a>
    </div>
</nav>

<div class="container" style="max-width: 900px;">

    <!-- SESSIONADMIN STATE -->
    <h6 class="fw-semibold mb-2">
        <code>$_SESSION['sessionadmin']</code>
        <span class="badge <?= ($sessionAdmin['isUser'] ?? false) ? 'bg-success' : 'bg-secondary' ?> ms-2">
            <?= ($sessionAdmin['isUser'] ?? false) ? 'user' : 'guest' ?>
        </span>
    </h6>
    <pre class="shadow-sm mb-4"><?= htmlspecialchars(json_encode($sessionAdmin, JSON_PRETTY_PRINT)) ?></pre>

    <!-- TABMANAGER STATE -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="fw-semibold mb-0">
                <code>$_SESSION['tabmanager']['tabs']</code>
                <span class="badge bg-secondary ms-2"><?= count($rawTabs) ?></span>
            </h6>
        </div>
        <div class="d-flex align-items-center gap-3">
            <label class="form-check-label text-muted small" for="auto-refresh">Auto-refresh</label>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">Refresh now</button>
        </div>
    </div>

    <?php if (empty($rawTabs)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body text-muted">No tabs registered in this session yet.</div>
        </div>
    <?php else: ?>

        <?php foreach ($rawTabs as $tabId => $tabData):
            $isActive   = $tabData['is_active'] ?? false;
            $lastActive = $tabData['last_active'] ?? 0;
            $ageSeconds = $now - $lastActive;
            $dataEntries = $tabData['data'] ?? [];

            if ($ageSeconds < 60)        $ageLabel = $ageSeconds . 's ago';
            elseif ($ageSeconds < 3600)  $ageLabel = floor($ageSeconds / 60) . 'm ago';
            else                          $ageLabel = floor($ageSeconds / 3600) . 'h ago';
        ?>
        <div class="card shadow-sm mb-3 tab-row" data-tab="<?= htmlspecialchars($tabId) ?>">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-2 flex-wrap">
                <code class="uuid text-muted"><?= htmlspecialchars($tabId) ?></code>

                <?php if ($isActive): ?>
                    <span class="badge bg-success">active</span>
                <?php else: ?>
                    <span class="badge bg-secondary">inactive</span>
                <?php endif; ?>

                <span class="age ms-1">
                    last active: <?= htmlspecialchars(date('H:i:s', $lastActive)) ?>
                    (<?= htmlspecialchars($ageLabel) ?>)
                </span>

                <button
                    class="btn btn-sm btn-outline-danger ms-auto"
                    onclick="deleteTab('<?= htmlspecialchars($tabId) ?>', this)"
                >
                    Delete
                </button>
            </div>

            <?php if (empty($dataEntries)): ?>
                <div class="card-body text-muted small fst-italic py-2">No data stored for this tab.</div>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Key</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataEntries as $key => $value): ?>
                        <tr>
                            <td class="data-key ps-3"><?= htmlspecialchars((string) $key) ?></td>
                            <td><?= htmlspecialchars((string) $value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <p class="text-muted small mt-3">
        Session name: <code><?= htmlspecialchars(session_name()) ?></code>
        &mdash; Generated at <?= date('Y-m-d H:i:s') ?>
        &mdash; seba1rx/sessionadmin + seba1rx/tabmanager
    </p>
</div>

<script>
    const toggle = document.getElementById('auto-refresh');
    setInterval(() => { if (toggle.checked) location.reload(); }, 10000);

    async function deleteTab(tabId, btn) {
        if (!confirm('Delete session data for this tab?')) return;

        btn.disabled    = true;
        btn.textContent = 'Deleting…';

        const response = await fetch('debug.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'delete', tab_id: tabId }),
        });

        if (response.ok) {
            const card = btn.closest('.tab-row');
            card.style.opacity = '0.4';
            setTimeout(() => card.remove(), 300);
        } else {
            alert('Failed to delete tab.');
            btn.disabled    = false;
            btn.textContent = 'Delete';
        }
    }
</script>

</body>
</html>

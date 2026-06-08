<?php
/**
 * session.php — Tab registration + session fetch endpoint
 *
 * INTEGRATION: this file shows how every PHP endpoint in the app must
 * wire SessionAdmin + the bridge before doing anything else.
 *
 * What it does:
 *   1. Wires SessionAdmin + SessionAdminBridge (consistent session name)
 *   2. Reads the tab UUID from X-TabManager-TabId header
 *   3. Calls indexNewTab() to register the tab (idempotent)
 *   4. Returns the full $_SESSION so the UI can render all tabs
 *
 * Why session.php instead of /tabmanager/new-tab:
 *   The built-in endpoint works in any front-controller setup. This demo
 *   runs with `php -S`, which serves files directly, so /tabmanager/* 404s.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;
use Seba1rx\TabManager\TabManager;

// INTEGRATION: same wiring pattern as index.php — session name must match
// on every request, including AJAX endpoints.
$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

// INTEGRATION: read the tab UUID from the header sent by TabManagerClient.getHeaders()
$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;

if ($tabId && TabManager::isValidTabId($tabId)) {
    // Register the tab. Idempotent — calling multiple times for the same UUID
    // has no effect.
    $session->tabHandler->indexNewTab($tabId);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);

<?php
/**
 * heartbeat.php — Heartbeat endpoint (workaround for php -S)
 *
 * INTEGRATION: every endpoint — including the heartbeat — must wire the
 * session consistently so the session name matches the cookie.
 *
 * The JS client POSTs here every 30 s to keep last_active fresh.
 * In a real front-controller app you do NOT need this file — the built-in
 * /tabmanager/heartbeat endpoint is registered automatically by the bootstrap.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;
use Seba1rx\TabManager\TabManager;

// INTEGRATION: consistent session wiring — session name must match on all requests.
$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;

if ($tabId && TabManager::isValidTabId($tabId)) {
    // touchTab() updates last_active + is_active = true.
    // Does NOT modify the tab's data slot.
    $session->tabHandler->touchTab($tabId);
}

// 204 No Content — the JS client does not expect a response body.
http_response_code(204);

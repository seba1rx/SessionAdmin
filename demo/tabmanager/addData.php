<?php
/**
 * addData.php — Write tab-specific session data (demo endpoint)
 *
 * INTEGRATION: this file shows how to use $session->tabHandler->set()
 * to write data that belongs exclusively to the calling tab.
 *
 * The bridge resolves the tab UUID automatically from X-TabManager-TabId —
 * you never handle the UUID manually in application code.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/WordStringGenerator.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;
use Seba1rx\TabManager\TabManager;

// INTEGRATION: consistent session wiring on every request.
$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
if (!$tabId || !TabManager::isValidTabId($tabId)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or missing tab ID']);
    exit;
}

// App logic: generate a random value to store (demo only)
$generator = new WordStringGenerator();
$value     = $generator->generate(3);
$key       = (string) time();

// INTEGRATION: set() writes to this tab's isolated session slot.
// Other tabs cannot read or overwrite this value.
$session->tabHandler->set($key, $value);

// Return the full session so the UI can re-render all tabs' data
header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);

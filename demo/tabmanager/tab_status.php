<?php
/**
 * tab_status.php — php -S workaround for GET /tabmanager/tab-status
 *
 * The built-in /tabmanager/tab-status endpoint is unreachable with `php -S`
 * (no router). This physical file replicates the same behaviour.
 *
 * INTEGRATION: the session must be started with the correct name before
 * calling TabManager::handleTabStatusRequest(). Using the full SessionAdmin
 * wiring here ensures the session name matches every other endpoint.
 *
 * In a real app with a front-controller router this file is not needed —
 * bootstrap.php registers /tabmanager/tab-status automatically.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;
use Seba1rx\TabManager\TabManager;

$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

TabManager::handleTabStatusRequest();

<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../../vendor/autoload.php';

set_exception_handler(function (Throwable $e) {
    $file = basename($e->getFile());
    error_log('## ' . $file . ' Line: ' . $e->getLine() . ' ' . $e->getMessage());
});

use App\Router;
use App\Response;
use App\MyTABSessionAdmin;

// Enable the debug UI served by bin/bootstrap.php at GET /sessionadmin/debug
define('SESSIONADMIN_DEBUG', true);
define('SESSIONADMIN_DEBUG_UI', true);

$routes = require __DIR__ . '/config/routes.php';
include __DIR__ . '/config/session.php';

// Wire up the SessionAdmin bootstrap endpoints (/sessionadmin/new-tab, /tab-close, /debug …)
// Must run after the session is active.
include __DIR__ . '/../../bin/bootstrap.php';

function tpl_dir(string $tpl): string
{
    return __DIR__ . '/tpl/' . $tpl;
}

function wrapInLeftAlignedDiv(string $content): string
{
    return '<div style="text-align:left">' . $content . '</div>';
}

function sessionAdmin(): MyTABSessionAdmin
{
    global $tab_sessionAdmin;
    return $tab_sessionAdmin;
}

$router   = new Router($routes);
$response = $router->routeToAction();

Response::send($response);

<?php

// Define debug constants and session name BEFORE autoload.
// bin/bootstrap.php is registered as a composer autoload.files entry and therefore
// runs the moment vendor/autoload.php is included. It intercepts /sessionadmin/*
// requests and needs both the debug constants and an active session to work.
// Setting session_name() here lets bootstrap call session_start() with the
// correct name; activateSession() in config/session.php then skips the start
// (already active) but still runs all security checks and cookie refresh.
define('SESSIONADMIN_DEBUG', true);
define('SESSIONADMIN_DEBUG_UI', true);
session_name('MyCustomTABSessionName');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../../vendor/autoload.php';

set_exception_handler(function (Throwable $e) {
    $file = basename($e->getFile());
    error_log('## ' . $file . ' Line: ' . $e->getLine() . ' ' . $e->getMessage());
});

use App\Router;
use App\Response;
use App\MyTABSessionAdmin;

$routes = require __DIR__ . '/config/routes.php';
include __DIR__ . '/config/session.php';

// bootstrap.php already ran via autoload.files above; the guard inside it
// prevents double-execution. This explicit include is kept for documentation clarity.
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

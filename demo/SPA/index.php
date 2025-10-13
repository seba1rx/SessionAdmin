<?php

require __DIR__."/vendor/autoload.php";
require __DIR__."/../../vendor/autoload.php";

set_exception_handler(function (Throwable $e) {
    $pathToFile = $e->getFile();
    $file = $pathToFile;
    $pos = strrpos($pathToFile, "/");
    if ($pos !== false) {
        $file = substr($pathToFile, $pos + 1);
    }
    $msg = "## " . $file . " Line: " . $e->getLine() . " ". $e->getMessage() . "\n";
    error_log($msg);
});

use App\Router;
use App\Response;
use App\MySPASessionAdmin;

$routes = require __DIR__."/config/routes.php";

// include the session script
include __DIR__."/config/session.php";

/**
 * Handy fn to get the tpl path
 * * Globally scoped function
 * @return string
 */
function tpl_dir($tpl): string
{
    return __DIR__."/tpl/".$tpl;
}

/**
 * Handy fn to wrap content in a left aligned div
 * * Globally scoped function
 * @param string $content
 * @return string
 */
function wrapInLeftAlignedDiv(string $content): string
{
    $wrapped = '<div style="text-align: left;">'.$content.'</div>';
    return $wrapped;
}

/**
 * Handy fn to get the sessionAdmin instance
 * * Globally scoped function
 * @return MySPASessionAdmin
 */
function sessionAdmin(): MySPASessionAdmin
{
    global $spa_sessionAdmin;
    return $spa_sessionAdmin;
}

$router = new Router($routes);
$response = $router->routeToAction();

Response::send($response);
<?php

require(__DIR__ . '/../vendor/autoload.php');

use MPA\AppFiles\MyMPASessionAdmin;

/**
 * you can define a configuration array if you want, or you can just use
 * the default values and not pass anything to the constructor
 *
 * passing the configuration to the constructor
 * $sessionAdmin = new MyMPASessionAdmin($conf);
 *
 * using the default values
 * $sessionAdmin = new MyMPASessionAdmin();
 */

$conf = [];
$conf["sessionLifetime"] = 120; // set the maximum time for the session: 2 minutes
$conf["allowedURLs"] = ["index.php", "page2.php"]; // set the allowed URLs (array list)
$conf["keys"] = [ // set other starting data that will be globally accessible directly from $_SESSION
    "some_key" => "some_value", // $_SESSION['some_key']
    "foo" => "bar", // $_SESSION['foo']
];

$sessionAdmin = new MyMPASessionAdmin($conf);
$sessionAdmin->useTabIndexation = false;
$sessionAdmin->app_isSpa = false;
$sessionAdmin->useAuthorization = true;
$sessionAdmin->ignoreInAuthorization = ["authentication.php"];
$sessionAdmin->ipOctetsToCheck = 2;
$sessionAdmin->proxyAwareIpDetection = true;
$sessionAdmin->activateSession();
<?php

use App\MySPASessionAdmin;

$conf = [];
$conf["sessionLifetime"] = 120; // set the maximum time for the session: 2 minutes
$conf["keys"] = [ // set other starting data that will be globally accessible directly from $_SESSION
    "some_key" => "some_value", // $_SESSION['some_key']
    "foo" => "bar", // $_SESSION['foo']
];

$spa_sessionAdmin = new MySPASessionAdmin($conf);
$spa_sessionAdmin->useTabIndexation = false;
$spa_sessionAdmin->useAuthorization = true;
$spa_sessionAdmin->ipOctetsToCheck = 2;
$spa_sessionAdmin->proxyAwareIpDetection = true;
$spa_sessionAdmin->activateSession();

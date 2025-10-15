<?php

use App\MyTABSessionAdmin;

$conf = [];
$conf["sessionLifetime"] = 500; // set the maximum time for the session: 2 minutes
$conf["keys"] = [ // set other starting data that will be globally accessible directly from $_SESSION
    "some_key" => "some_value", // $_SESSION['some_key']
    "foo" => "bar", // $_SESSION['foo']
];

$tab_sessionAdmin = new MyTABSessionAdmin($conf);
$tab_sessionAdmin->useAuthorization = true;
$tab_sessionAdmin->ipOctetsToCheck = 2;
$tab_sessionAdmin->proxyAwareIpDetection = true;
$tab_sessionAdmin->activateSession();

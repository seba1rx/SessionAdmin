<?php

use App\MyTABSessionAdmin;

$conf = [
    'sessionLifetime' => 500,
    'keys'            => [
        'some_key' => 'some_value',
        'foo'      => 'bar',
    ],
];

$tab_sessionAdmin = new MyTABSessionAdmin($conf);
$tab_sessionAdmin->appIsSpa              = true;  // SPA — disables URL authorization and index redirect
$tab_sessionAdmin->useTabIndexation      = true;
$tab_sessionAdmin->useAuthorization      = false; // irrelevant when appIsSpa = true, explicit for clarity
$tab_sessionAdmin->ipOctetsToCheck       = 2;
$tab_sessionAdmin->proxyAwareIpDetection = true;
$tab_sessionAdmin->activateSession();

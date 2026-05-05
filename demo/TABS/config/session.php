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
$tab_sessionAdmin->useTabIndexation      = true;
$tab_sessionAdmin->useAuthorization      = true;
$tab_sessionAdmin->ipOctetsToCheck       = 2;
$tab_sessionAdmin->proxyAwareIpDetection = true;
$tab_sessionAdmin->activateSession();

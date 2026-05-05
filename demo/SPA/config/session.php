<?php

use App\MySPASessionAdmin;

$conf = [
    'sessionLifetime' => 120,
    'keys'            => [
        'some_key' => 'some_value',
        'foo'      => 'bar',
    ],
];

$spa_sessionAdmin = new MySPASessionAdmin($conf);
$spa_sessionAdmin->appIsSpa              = true;  // disables URL authorization and index redirect
$spa_sessionAdmin->useTabIndexation      = false;
$spa_sessionAdmin->useAuthorization      = false; // irrelevant when appIsSpa = true, explicit for clarity
$spa_sessionAdmin->ipOctetsToCheck       = 2;
$spa_sessionAdmin->proxyAwareIpDetection = true;
$spa_sessionAdmin->activateSession();

<?php

require __DIR__ . '/../vendor/autoload.php';

use MPA\AppFiles\MyMPASessionAdmin;

$conf = [
    'sessionLifetime' => 120,
    'allowedURLs'     => ['index.php', 'page2.php'],
    'keys'            => [
        'some_key' => 'some_value',
        'foo'      => 'bar',
    ],
];

$sessionAdmin = new MyMPASessionAdmin($conf);
$sessionAdmin->useTabIndexation      = true;
$sessionAdmin->appIsSpa              = false;
$sessionAdmin->useAuthorization      = true;
$sessionAdmin->ignoreInAuthorization = ['authentication.php'];
$sessionAdmin->ipOctetsToCheck       = 2;
$sessionAdmin->proxyAwareIpDetection = true;
$sessionAdmin->terminateRedirects    = true;
$sessionAdmin->activateSession();

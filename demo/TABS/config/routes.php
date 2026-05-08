<?php

return [

    'GET' => [
        '/' => [App\Controller::class, 'start'],
    ],
    'POST' => [
        '/hello'             => [App\Controller::class, 'hello'],
        '/reloadSessionData' => [App\Controller::class, 'reloadSessionData'],
        '/addVarToSession'   => [App\Controller::class, 'addVarToSession'],
        '/tabStatus'         => [App\Controller::class, 'tabStatus'],
        '/showLogin'         => [App\Controller::class, 'showLogin'],
        '/authenticate'      => [App\Authentication::class, 'authenticate'],
        '/demoData'          => [App\Controller::class, 'demoData'],
        '/logout'            => [App\Controller::class, 'logout'],
        '/destroyAndRedirect' => [App\Controller::class, 'destroyAndRedirect'],
    ],
    'PUT'    => [],
    'DELETE' => [],
];

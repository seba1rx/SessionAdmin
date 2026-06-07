<?php

include __DIR__ . '/required.php';

$validationResponse = ['ok' => false, 'msg' => null];

if (
    isset($_POST['useremail'], $_POST['userpassword']) &&
    !empty($_POST['useremail']) &&
    !empty($_POST['userpassword'])
) {
    // Simulate a DB lookup — in a real app this is where you query your database.
    $data = [
        'id'        => 123,
        'name'      => 'Sebastian',
        'profile'   => 'admin',
        'nickname'  => 'seba1rx',
        'avatar'    => 'cat-space.gif',
        'birthDate' => '1985-05-21',
        'country'   => 'Chile',
        'email'     => $_POST['useremail'],
    ];

    $sessionAdmin->createUserSession($data['id']);

    foreach ($data as $key => $value) {
        $_SESSION['data'][$key] = $value;
    }

    // Extend the allowed URL list with pages the authenticated profile can visit.
    $profileAllowedUrls = ['private.php'];
    foreach ($profileAllowedUrls as $url) {
        $_SESSION['sessionadmin']['allowedUrl'][] = $url;
    }

    $validationResponse['ok']  = true;
    $validationResponse['msg'] = 'Welcome ' . $_SESSION['data']['name'];

} else {
    $validationResponse['msg'] = 'Complete the form fields!';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($validationResponse);

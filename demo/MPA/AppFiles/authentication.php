<?php

include(__DIR__ . '/required.php');

/**
 * this scripts simulates an authentication, you should implement your own validation
 */
$validationResponse = [
    'ok' => false,
    'msg' => null,
];

if(
    isset($_POST['useremail'], $_POST['userpassword']) &&
    !empty($_POST['useremail']) &&
    !empty($_POST['userpassword'])
){
    /** lets assume you query the system database and the data matching the credentials is the following: */
    $data = [
        'id' => 123,
        'name' => 'Sebastian',
        'profile' => 'admin',
        'nickname' => 'seba1rx',
        'avatar' => 'cat-space.gif',
        'birthDate' => '1985-05-21',
        'country' => 'Chile',
        'email' => $_POST['useremail'],
    ];

    /**
     * up to now the user is in guest mode...
     * lets call the method to create the user session
     */
    $sessionAdmin->createUserSession($data['id']);

    /**
     * In this demo I will be using the key 'data'
     * you can use any key you want to add the data (if any)
     * add the data to SESSION['data']
     */
    foreach($data AS $dataName => $dataValue){
        $_SESSION['data'][$dataName] = $dataValue;
    }

    /**
     * In a very simple authorization MPA app you need to controll the pages the user can request.
     * lets suppose you query the database to get all the pages the authenticated user can visit
     */
    // $ProfileAllowedUrls = myMethodThatObtainsTheAllowedPages($data['profile']);
    $ProfileAllowedUrls = [
        "private.php",
        //"some_other.php",
    ];

    /**
     * adding the allowed pages to a session var
     */
    foreach($ProfileAllowedUrls AS $url){
       $_SESSION['sessionadmin']['allowedUrl'][] = $url;
    }

    $validationResponse['ok'] = true;
    $validationResponse['msg'] = 'Wellcome '.$_SESSION['data']['name'];

}else{
    $validationResponse['ok'] = false;
    $validationResponse['msg'] = 'Complete the form fields!';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($validationResponse);

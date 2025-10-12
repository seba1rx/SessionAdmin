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
     * since $_SESSION is a global variable there is no point in encapsulating
     * the data with get and set...
     * lets add the data to SESSION['data']
     */
    foreach($data AS $dataName => $dataValue){
        $_SESSION['data'][$dataName] = $dataValue;
    }

    /**
     * lets suppose you query the database and get all the pages the authenticated user can see
     */
    // $ProfileAllowedUrls = myMethodThatObtainsTheAllowedPages($data['profile']);
    $ProfileAllowedUrls = [
        "private.php",
        //"some_other.php",
    ];

    /**
     * here you would iterate to add allowed urls according to assigned profile
     */
    foreach($ProfileAllowedUrls AS $url){
       $_SESSION['allowedUrl'][] = $url;
    }

    $validationResponse['ok'] = true;
    $validationResponse['msg'] = 'Wellcome '.$_SESSION['data']['name'];

}else{
    $validationResponse['ok'] = false;
    $validationResponse['msg'] = 'Complete the form fields!';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($validationResponse);

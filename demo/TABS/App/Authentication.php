<?php

namespace App;

use App\Request;

class Authentication
{
    public function authenticate(Request $request): object
    {
        /**
         * this scripts simulates an authentication, you should implement your own validation
         */
        $validation = new \stdClass;
        $validation->auth = new \stdClass;
        $validation->auth->ok = false;
        $validation->auth->msg = null;

        $user = $request->getFromPayload('useremail');
        $pass = $request->getFromPayload('userpassword');

        if( $user && $pass && !empty($user) && !empty($pass) ){
            /** lets assume you query the system database and the data matching the credentials is the following: */
            $data = [
                'id' => 123,
                'name' => 'Sebastian',
                'profile' => 'admin',
                'nickname' => 'seba1rx',
                'avatar' => 'cat-space.gif',
                'birthDate' => '1985-05-21',
                'country' => 'Chile',
                'email' => $user,
            ];

            /**
             * up to now the user is in guest mode...
             * lets call the method to create the user session
             */
            sessionAdmin()->createUserSession($data['id']);

            /**
             * since $_SESSION is a global variable there is no point in encapsulating
             * the data with get and set...
             * lets add the data to SESSION['data']
             */
            foreach($data AS $dataName => $dataValue){
                $_SESSION['data'][$dataName] = $dataValue;
            }

            /**
             * in SPA mode you only need index.php
             */
            $_SESSION['allowedUrl'] = [];

            $validation->auth->ok = true;
            $validation->auth->msg = 'Wellcome '.$_SESSION['data']['name'];

        }else{
            $validation->auth->msg = 'Complete the form fields!';
        }

        return $validation;

    }
}
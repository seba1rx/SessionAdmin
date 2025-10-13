<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * The SessionAdmin Class is an abstract class, but has no abstract methods,
 * so the SessionAdmin class is intended to be extended an implemented with
 * a custom constructor
 */
class MySPASessionAdmin extends SessionAdmin{

    /**
     * Extend the SessionAdmin class by defining a custom constructor
     *
     * @param mixed $conf The config data for your SessionAdmin instance
     */
    public function __construct(mixed $conf = []){

        /**
         * Optional: define a session name:
         *
         * if you are going to use a session name,
         * you will need to destroy it by using the name,
         * see exitp.php in the demo
         */
        $this->sessionName = "MyCustomSPASessionName";

        /**
         * Optional:
         * Define the "sessionLifetime" item in te $conf parameter
         * in order to set a maximum time to keep the session
         * active between requests:
         *
         * $conf["sessionLifetime"] = 3600; // 1 hour
         *
         * If defined, the pages that need authentication will check
         * if your session is alive or not, if not the request will
         * be taken to index
         */
        if(isset($conf["sessionLifetime"])){
            $this->sessionLifetime = $conf["sessionLifetime"];
        }

        /**
         * Set useAuthorization to true if you want the SessionAdmin
         * to manage the authorization, but you will need to
         * define "allowedURLs" key in the $conf parameter.
         *
         * you can see that the line is commented because you can either set the
         * value to true here or in the code where you instantiate this class
         * (in this example is in required.php)
         */
        // $this->useAuthorization = true;

        /**
         * Set how many IP octates should the ip detection method take
         * * 2 octets (default) = good balance (ok for mobile ISPs).
         * * 3 octets = stricter (LAN or stable ISP).
         * * 4 octets = strict full-IP check.
         *
         * Set if you want a proxy-aware ip detection as well
         *
         * you can see that the following two lines are commented because
         * you can either set the value here or in the code where you
         * instantiate this class (in this example is in required.php)
         */
        // $this->ipOctetsToCheck = 2;
        // $this->proxyAwareIpDetection = true;

        /**
         * Define the "allowedURLs" item in the $conf parameter
         * in order to indicate what pages are allowed to load,
         * you can add or remove the allowed pages in
         * authentication by implementing a profile controller.
         *
         * $conf["allowedURLs"] = ["index.php", "page2.php"];
         *
         * This will only take effect if you define as true
         * the following property:
         * $this->useAuthorization = true;
         */
        if(isset($conf["allowedURLs"])){
            foreach($conf["allowedURLs"] as $page){
                if(!in_array($page, $this->allowedUrls)){
                    $this->allowedUrls[] = $page;
                }
            }
        }

        /**
         * Define the "keys" item in the $conf parameter
         * in order to load the first data into your session
         * that will be in the "data" key:
         *
         * $conf["keys"] = ["foo" => "bar", "baz" => 123];
         *
         * You can access your data like this:
         * $foo = $_SESSION["data"]["foo"]; // "bar"
         * $baz = $_SESSION["data"]["baz"]; // 123
         */
        if(isset($conf["keys"])){
            foreach($conf["keys"] as $key => $value){
                $this->keys[$key] = $value;
            }
        }
    }
}
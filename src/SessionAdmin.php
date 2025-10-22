<?php

namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\TabManager;

/**
 * Extend this class to customize it by creating your own constructor
 *
 * This class is defined as abstract to force implementing a class extending this class to define a constructor
 *
 * There are no abstract methods in this class, but it is intended to be implemented with a custom constructor.
 */
abstract class SessionAdmin extends Session{

    /**
     * array list containing the file names that will be checked in the authorization process
     *
     * @var array
     */
    protected $allowedUrls = [];

    /**
     * assoc array that you can pass to load into the session
     *
     * @var array
     */
    protected $keys = [];

    /**
     * will hold a unique ID to identify the session (it could be handy to have)
     *
     * @var string
     */
    private $uniqueId;

    /**
     * if true, will check against $allowedUrls
     *
     * @var boolean
     */
    public $useAuthorization = false;

    /**
     * if true will override all authorization since it only make since in MPA applications,
     * allowing you to implement your own way to authorize in a SPA app like using middlewares.
     *
     * @var boolean
     */
    public $app_isSpa = true;

    /**
     * Handy property to prevent some actions while unit testing
     * @var boolean
     */
    private $isRunningTests = false;

    /**
     * will hold the instance of the TabManager class
     * @var TabManager
     */
    public $tabManager;

    /**
     * If true will use TabManager class to manage the set and get of session vars by indexing under tab Uuid
     *
     * @var boolean
     */
    public $useTabIndexation = true;

    /**
     * Extend this class and define a constructor, here you have a template for a MPA app:
     *
     * $sessionAdmin = new \MyNamespace\MyImplementationOfSessionAdmin(
     *     [
     *          "sessionLifetime" => 3600,
     *          "allowedURLs" => ["index.php", "legal.php", "contact_us.php", "our_history.php", "products_and_plans.php"],
     *          "keys" => [
     *              "some_key" => "some_value",
     *              "foo" => "bar",
     *          ],
     *     ]
     * );
     * $sessionAdmin->useAuthorization = true; // if you want SessionAdmin to manage authorization
     * $sessionAdmin->activateSession(); // this is like session_start()
     */

    /**
     * Starts a new session as guest or renewes user session if $_SESSION['uniqueId'] is set
     *
     * @return void
     */
    public function activateSession(): void
    {
        session_name($this->sessionName);
        $this->setSessionTime();

        session_start();

        $this->setSessionTimeStamps();

        $this->uniqueId = bin2hex(random_bytes(6));
        $_SESSION['uniqueId'] = $this->uniqueId;

        if($this->currentStateIsUser()){
            # user
            $this->checkTime();
        }else{
            # guest
            $this->configureGuestSession();
        }

        // if(isset($_SESSION['uniqueId'])){
        //     # user
        //     $this->checkTime();
        // }else{
        //     # guest
        //     $this->configureGuestSession();
        // }

        // only check url when app is MPA
        if($this->useAuthorization && !$this->app_isSpa){
            $this->checkIfUrlIsAllowed();
        }

        // set the session admin tab manager
        if($this->useTabIndexation){
            $this->setTabManager();
        }else{
            unset($this->tabManager);
        }

        foreach($this->keys as $key => $item){
            if(!isset($_SESSION[$key])){
                $_SESSION[$key] = $item;
            }
        }
    }

    /**
     * Adds user data to SESSION and sets time
     *
     * @param mixed $id_user
     * @return void
     */
    public function createUserSession(mixed $id_user): void
    {
        // $this->uniqueId = bin2hex(random_bytes(6));
        // $_SESSION['uniqueId'] = $this->uniqueId;

        $_SESSION['isUser'] = TRUE;
        $_SESSION['msg'] = 'you are a user';
        $_SESSION['id_user'] = $id_user;
        $_SESSION['urlIsAllowedToLoad'] = FALSE;

        $this->setSessionTime();
        $this->setSessionTimeStamps();
    }

    /**
     * Wipes out all session data, sends the request to safe pace
     *
     * @return void
     */
    public function terminate(): void
    {
        /** destroy session */
        $_SESSION = [];
        $this->destroySession();

        // only for MPA
        if(!$this->app_isSpa && !$this->isRunningTests){
            /** go to safe page */
            $this->redirectToIndex();
        }
    }

}


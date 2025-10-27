<?php

namespace Seba1rx\SessionAdmin;

/**
 * This class is defined as abstract to force to implement it by extending it.
 *
 * This class has no abstract methods, so just extend this class by defining a constructor.
 *
 * You will find a template here or you can check the demos
 */
abstract class SessionAdmin extends Session{

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
     * Starts a new session as guest or renewes user session if $_SESSION['sessionadmin']['uniqueId'] is set
     *
     * @return void
     */
    public function activateSession(): void
    {
        session_name($this->sessionName);
        $this->setSessionTime();

        session_start();

        if(!isset($_SESSION['sessionadmin'])) $_SESSION['sessionadmin'] = [];

        $this->setSessionTimeStamps();

        $this->uniqueId = bin2hex(random_bytes(6));
        $_SESSION['sessionadmin']['uniqueId'] = $this->uniqueId;

        if($this->currentStateIsUser()){
            # user
            $this->checkTime();
        }else{
            # guest
            $this->configureGuestSession();
        }

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

        // assign the defined keys to the root of $_SESSION var (if any)
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

        $_SESSION['sessionadmin']['isUser'] = TRUE;
        $_SESSION['sessionadmin']['msg'] = 'you are a user';
        $_SESSION['sessionadmin']['id_user'] = $id_user;
        $_SESSION['sessionadmin']['urlIsAllowedToLoad'] = FALSE;

        $this->setSessionTime();
        $this->setSessionTimeStamps();
    }

    /**
     * Terminates the session by wiping out all session data, sends the request to safe pace (if configured)
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
            if($this->terminateRedirects){
                /** go to safe page */
                $this->redirectToIndex();
            }
        }
    }

    /**
     * Clears $_SESSION, destroys current session, and calls to start new session as guest
     *
     * @return void
     */
    protected function destroySession(): void
    {
        if(isset($_SESSION)){
            $_SESSION = [];
            session_write_close();
            session_destroy();
            $this->activateSession();
        }
    }

    /**
     * Calls to destroySession if request is obsolete
     * * session always exists when this function is called
     * * if request is still on time, checks if request is hijacking attempt
     *
     * @return void
     */
    protected function checkTime(): void
    {
        if($this->requestIsObsolete()){
            $this->destroySession();
        }else{

            if ($this->requestIsHijackingAttempt()) {

                // Reset session data and regenerate id
                $_SESSION = [];
                $_SESSION['sessionadmin'] = [];
                if($this->proxyAwareIpDetection){
                    $_SESSION['sessionadmin']['ipAddress'] = $this->getIpAddress_proxyAware();
                }else{
                    $_SESSION['sessionadmin']['ipAddress'] = $this->getIpAddress_noProxys();
                }
                $_SESSION['sessionadmin']['userAgent'] = $this->getUserAgent();
                $this->regenerateSession();

                // Give a 3% chance of the session id changing on any request
            } elseif ($this->shouldRandomlyRegenerate()) {
                $this->regenerateSession();
            }
        }
    }

}


<?php

namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\TabManager;
use Seba1rx\SessionAdmin\Exceptions\SessionAdminException;

abstract class Session{

    const IP_REGEX = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.)(\d{1,3})/';

    /**
     * The secconds the session will be alive between requests
     *
     * @var integer
     */
    protected $sessionLifetime = 2400;

    /**
     * The session name that will be used
     *
     * @var string
     */
    protected $sessionName = 'SESSION';

    /**
     * Path on the domain where the cookie will work. Use a single slash ('/') for all paths on the domain.
     * used in session_set_cookie_params()
     * it is set to protected in case you want to change the value in your implementation
    *
    * @var string
    */
    protected $path = '/';

    /**
     * Cookie domain, for example 'www.php.net'. To make cookies visible on all subdomains then the domain must be prefixed with a dot like '.php.net'.
     * used in session_set_cookie_params()
     * it is set to protected in case you want to change the value in your implementation
    *
    * @var string|null
    */
    protected $domain = NULL;

    /**
     * If true cookie will only be sent over secure connections.
     * used in session_set_cookie_params()
     * it is set to protected in case you want to change the value in your implementation
     *
     * @param bool|null
     */
    protected $secure = NULL;

    /**
     * if true the IP detection will consider $ipOctetsToCheck
     *
     * @var boolean
     */
    public $proxyAwareIpDetection = true;

    /**
     * the number of octates to consider when identifying the IP
     *
     * @var integer
     */
    public $ipOctetsToCheck = 2;

    /**
     * array list containing the file names that are excluded in the authorization process
     *
     * @var array
     */
    public $ignoreInAuthorization = [];


    /**
     * returns true if the Session has the key isUser and the value is true
     * * empty() automatically returns false if the key doesn’t exist and the value is falsy
     *
     * @return bool
     */
    protected function currentStateIsUser(): bool
    {
        return !empty($_SESSION['isUser']);
    }

    /**
     * Sets the TabManager instance
     *
     * @return void
     */
    protected function setTabManager(): void
    {
        $this->tabManager = new TabManager();
    }

    /**
     * Configure $_SESSION with guest data which is the minimum data to use the website
     *
     * @return void
     */
    protected function configureGuestSession(): void
    {
        // setting / resetting session
        $_SESSION = [];
        $_SESSION['isUser'] = FALSE;
        $_SESSION['msg'] = 'you are a guest';
        $_SESSION['allowedUrl'] = $this->allowedUrls;
        $_SESSION['urlIsAllowedToLoad'] = FALSE; // by default
    }

    /**
     * Set the domain to current domain if not set
     * Set the secure value to whether the site is being accessed with SSL
     *
     * @return void
     */
    protected function preparesDomainAndSecureVars(): void
    {
        $this->domain = $this->domain ?? $_SERVER['SERVER_NAME'];
        $this->secure = $this->secure ?? isset($_SERVER['HTTPS']);
    }

    /**
     * Updates the available session time
     *
     * @return void
     */
    protected function setSessionTime(): void
    {
        // refresh expire time of session
        if (isset($_COOKIE[$this->sessionName])){
            setcookie($this->sessionName, $_COOKIE[$this->sessionName], time() + $this->sessionLifetime, "/");
        }

        // sets expire time (when creating session only)
        if(!isset($_SESSION)) {
            $this->preparesDomainAndSecureVars();
            session_set_cookie_params($this->sessionLifetime, $this->path, $this->domain, $this->secure, true);
            ini_set('session.gc_maxlifetime',(string)$this->sessionLifetime);
        }
    }

    /**
     * Sets session time vars used to calculate whether request is obsolete
     * session always exists when this function is called
     *
     * @return void
     */
    protected function setSessionTimeStamps(): void
    {
        $time = time();
        $previous = time();
        if(isset($_SESSION['time_atRequest'])){
            $previous = $_SESSION['time_atRequest'];
        }
        $_SESSION['time_atRequest'] = $time;
        $_SESSION['time_sinceLastRequest'] = $time - $previous;
    }

    /**
     * Checks if the request was received within session time or it was obsolete
     *
     * @return bool true if request is obsolete
     */
    protected function requestIsObsolete(): bool
    {
        return ($_SESSION['time_sinceLastRequest'] > $this->sessionLifetime ? TRUE : FALSE);
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
                if($this->proxyAwareIpDetection){
                    $_SESSION['ipAddress'] = $this->getIpAddress_proxyAware();
                }else{
                    $_SESSION['ipAddress'] = $this->getIpAddress_noProxys();
                }
                $_SESSION['userAgent'] = $this->getUserAgent();
                $this->regenerateSession();

                // Give a 3% chance of the session id changing on any request
            } elseif ($this->shouldRandomlyRegenerate()) {
                $this->regenerateSession();
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
     * Checks if url that originated the request is in allowd URL array
     * This check is done when an url is requested or when an XHR request is sent to the server
     * This evaluation only marks TRUE when allowed, FALSE by default
     *
     * @return void
     * @throws SessionAdminException
     */
    protected function checkIfUrlIsAllowed(): void
    {
        if(empty($_SESSION['allowedUrl'])) throw new SessionAdminException("the allowedUrl key is empty");
        $_SESSION['urlIsAllowedToLoad'] = FALSE;
        $url_to_check = basename($_SERVER['PHP_SELF']);

        if(in_array($url_to_check, $this->ignoreInAuthorization)){
            return;
        }

        foreach($_SESSION['allowedUrl'] AS $allowed){
            $url_to_compare_against = $this->getSubStrAfterLast($allowed, '/');
            if($url_to_check == $url_to_compare_against){
                $_SESSION['urlIsAllowedToLoad'] = TRUE;
                break;
            }
        }
        if(!$_SESSION['urlIsAllowedToLoad'] && $this->useAuthorization){
            $this->redirectToIndex();
            // header('Location: index.php');
        }
    }

    /**
     * Checks if request is valid or a hijacking attempt
     *
     * @return bool true if hijicking detected, false for a valid request
     */
    protected function requestIsHijackingAttempt(): bool
    {
        if($this->proxyAwareIpDetection){
            $ipAddress = $this->getIpAddress_proxyAware();
        }else{
            $ipAddress = $this->getIpAddress_noProxys();
        }
        $userAgent = $this->getUserAgent();

        // Build IP prefix based on configuration
        if($this->ipOctetsToCheck < 2 && $this->ipOctetsToCheck > 4){
            throw new SessionAdminException("Ip octates to check must be in the 2 to 4 range");
        }
        $ipPrefix = $this->getIpPrefix($ipAddress, $this->ipOctetsToCheck);

        if (!isset($_SESSION['ipPrefix'], $_SESSION['userAgent'])) {
            $_SESSION['ipPrefix'] = $ipPrefix;
            $_SESSION['userAgent'] = $userAgent;
            return false; // first request → not hijacking
        }

        if ($_SESSION['ipPrefix'] !== $ipPrefix) {
            return true; // possible hijacking
        }

        if ($_SESSION['userAgent'] !== $userAgent) {
            return true; // possible hijacking
        }

        return false; // looks safe
    }

    /**
     * Obtains the full IP in a web server behind a proxy.
     * Use this if your app is behind Cloudflare, Nginx, Apache mod_proxy,
     * AWS ELB, etc. and you want the real client IP.
     *
     * * Checks common proxy headers in order.
     * * Handles comma-separated lists (X-Forwarded-For).
     * * Validates IPs and skips private/reserved ranges (so attackers can’t inject 127.0.0.1).
     * * Falls back to REMOTE_ADDR
     *
     * @return string
     */
    protected function getIpAddress_proxyAware(): string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                // Handle multiple IPs (first = client, rest = proxies)
                $ipList = explode(',', $_SERVER[$key]);
                foreach ($ipList as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Obtains the ip address in a web server that does not uses a proxy/load balancer
     *
     * Use this if your app runs directly on a server and you don’t trust
     * proxy headers (e.g., a VPS without Cloudflare or load balancer)
     *
     * * attacker cannot spoof it
     * * if your app sits behind a reverse proxy/load balancer this will only give you the proxy’s IP
     *
     * @return string
     */
    protected function getIpAddress_noProxys(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Obtains the first 2, 3 or 4 octates from the ip accordind to ipOctetsToCheck
     *
     * @param string $ip
     * @param integer $parts
     * @return string
     */
    protected function getIpPrefix(string $ip, int $parts = 2): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $chunks = explode('.', $ip);
            return implode('.', array_slice($chunks, 0, $parts));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $chunks = explode(':', $ip);
            return implode(':', array_slice($chunks, 0, $parts));
        }

        return $ip; // fallback if invalid
    }

    /**
     * Gets the user agent
     *
     * @return string
     */
    protected function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Creates a fresh session Id to make it harder to hack
     *
     * @return void
     */
    protected function regenerateSession(): void
    {
        // Create new session without destroying the old one
        session_regenerate_id(false);

        // Grab current session ID and close both sessions to allow other scripts to use them
        $session_id = session_id();
        session_write_close();

        // Set session ID to the new one, and start it back up again
        session_id($session_id);
        session_start();
    }

    /**
     * 3% chances of returning true on each request
     *
     * @return bool
     */
    protected function shouldRandomlyRegenerate(): bool
    {
        return rand(1, 100) <= 3;
    }

    /**
     * Gets the substring after the last occurence of the needle
     * e.g., getSubStrAfterLast('dot.separated.string.parts', '.') -> returns 'parts'
     *
     * @param string $haystack
     * @param string $needle
     * @return string
     */
    protected function getSubStrAfterLast (string $haystack, string $needle): string
    {
        if (!is_bool($this->strrevpos($haystack, $needle))){
            return substr($haystack, $this->strrevpos($haystack, $needle)+strlen($needle));
        }else{
            return $haystack;
        }
    }

    /**
     * Use strrevpos function in case your php version does not include it
     *
     * @param string $haystack
     * @param string $needle
     * @return mixed
     */
    protected function strrevpos(string $haystack, string $needle): mixed
    {
        $rev_pos = strpos (strrev($haystack), strrev($needle));
        if($rev_pos===false){
            return false;
        }else{
            return strlen($haystack) - $rev_pos - strlen($needle);
        }
    }

    /**
     * Redirects the current request to index.php.
     * Handles normal browser requests, XHR/fetch calls, and preserves original URI.
     *
     * * requires that the script request sender (e.g. jquery ajax) implements a handler to redirect to the intended page
     *
     * @return void
     */
    protected function redirectToIndex(): void
    {
        // Target file
        $url = '/index.php';

        // Detect request type
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isXhrOrFetch = false;

        if (strtolower($requestedWith) === 'xmlhttprequest') {
            $isXhrOrFetch = true;
        } elseif (stripos($accept, 'application/json') !== false || stripos($accept, 'text/javascript') !== false) {
            $isXhrOrFetch = true;
        } elseif (!empty($_SERVER['HTTP_X_FETCH_REQUEST']) || !empty($_SERVER['HTTP_SEC_FETCH_MODE'])) {
            $isXhrOrFetch = true;
        }

        // Decide HTTP status code
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            // Preserve method → use 307
            $status = 307;
        } else {
            // Safe GET redirect
            $status = 303;
        }

        // Always send Location header
        if (!headers_sent()) {
            header("Location: {$url}", true, $status);
        }

        // XHR/fetch requests → return JSON + custom header
        if ($isXhrOrFetch) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true, $status);
                header("X-Redirect-URL: {$url}");
            }
            echo json_encode(['redirect' => $url]);
            exit;
        }

        // Fallback HTML (for non-JS browsers)
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8', true, $status);
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting…</title>';
        echo "<meta http-equiv=\"refresh\" content=\"0;url={$url}\">";
        echo '</head><body>';
        echo "Redirecting to <a href=\"{$url}\">index.php</a>.";
        echo '</body></html>';
        exit;
    }

    /**
     * Wrapper for PHP's native setcookie() function.
     *
     * This method exists to make cookie management testable in unit tests
     * without invoking actual HTTP headers. It mirrors the native PHP
     * setcookie() signature and behavior as closely as possible.
     *
     * @param string $name     The name of the cookie.
     * @param string $value    The value of the cookie. An empty string will delete the cookie.
     * @param int|string|array $expiresOrOptions
     *        - If an int, it is interpreted as a Unix timestamp for expiration.
     *        - If a string, it will be parsed as a date/time for expiration.
     *        - If an array, it must match the PHP 7.3+ options format, e.g.:
     *          [
     *              'expires'  => time() + 3600,
     *              'path'     => '/',
     *              'domain'   => '',
     *              'secure'   => true,
     *              'httponly' => true,
     *              'samesite' => 'Lax'
     *          ]
     * @param string $path     Optional. The path on the server in which the cookie will be available.
     * @param string $domain   Optional. The (sub)domain the cookie is available to.
     * @param bool   $secure   Optional. Indicates that the cookie should only be transmitted over HTTPS.
     * @param bool   $httponly Optional. When true, the cookie will be made accessible only through the HTTP protocol.
     *
     * @return bool Returns true on success or false on failure, just like native setcookie().
     *
     * @see https://www.php.net/manual/en/function.setcookie.php
     */
    protected function setCookie(
        string $name,
        string $value = "",
        int|string|array $expiresOrOptions = 0,
        string $path = "",
        string $domain = "",
        bool $secure = false,
        bool $httponly = false
    ): bool {
        // Allow unit test override: if a mock method exists, use it.
        if (method_exists($this, 'mockSetCookie')) {
            return $this->mockSetCookie(
                $name,
                $value,
                $expiresOrOptions,
                $path,
                $domain,
                $secure,
                $httponly
            );
        }

        return setcookie(
            $name,
            $value,
            $expiresOrOptions,
            $path,
            $domain,
            $secure,
            $httponly
        );
    }

    /**
     * Wrapper for accessing cookie values from the $_COOKIE superglobal.
     *
     * This method exists to make cookie reads testable in unit tests,
     * providing a single point of access to cookie data. During testing,
     * you can override or mock this method to simulate cookie states
     * without modifying global variables.
     *
     * @param string $name     The name of the cookie to retrieve.
     * @param mixed  $default  Optional. The default value to return if the cookie is not set.
     *
     * @return mixed Returns the cookie value if it exists, otherwise the default value.
     *
     * @see https://www.php.net/manual/en/reserved.variables.cookies.php
     */
    protected function getCookie(string $name, mixed $default = null): mixed
    {
        // Allow unit test override
        if (method_exists($this, 'mockGetCookie')) {
            return $this->mockGetCookie($name, $default);
        }

        return $_COOKIE[$name] ?? $default;
    }

    /**
     * called from unit tests
     *
     * @codeCoverageIgnore
     */
    protected function setIsRunningTests(bool $isRunningTest = true): void
    {
        $this->isRunningTests = $isRunningTest;
    }
}


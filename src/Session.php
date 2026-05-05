<?php

namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\Exceptions\SessionAdminException;

abstract class Session{

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
    protected $uniqueId;

    /**
     * if true, will check against $allowedUrls
     *
     * @var boolean
     */
    public $useAuthorization = false;

    /**
     * if true will override all authorization since it only makes sense in MPA applications,
     * allowing you to implement your own way to authorize in a SPA app like using middlewares.
     *
     * @var boolean
     */
    public $appIsSpa = true;

    /**
     * if true the IP detection will consider $ipOctetsToCheck
     *
     * @var boolean
     */
    public $proxyAwareIpDetection = true;

    /**
     * the number of octets to consider when identifying the IP
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
     * If true, when terminate() function is called, the request will be redirected to index
     * @var boolean
     */
    public $terminateRedirects = true;

    /**
     * The seconds the session will be alive between requests
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
     * @var bool|null
     */
    protected $secure = NULL;


    /**
     * Returns true if the Session has the key isUser and the value is true
    *
    * @return bool
    */
    protected function currentStateIsUser(): bool
    {
        return !empty($_SESSION['sessionadmin']['isUser']);
    }

    /**
     * Configure $_SESSION with guest data which is the minimum data to use the website.
     *
     * URL authorization keys (allowedUrl, urlIsAllowedToLoad) are only written for
     * MPA apps — they are meaningless and confusing when $appIsSpa is true.
     *
     * uniqueId is preserved across calls so it remains stable for the full session
     * lifetime even for unauthenticated users.
     *
     * @return void
     */
    protected function configureGuestSession(): void
    {
        $existingUniqueId = $_SESSION['sessionadmin']['uniqueId'] ?? null;
        $existingTabs     = $_SESSION['tabs'] ?? null;

        $_SESSION = [];
        $_SESSION['sessionadmin'] = [];
        $_SESSION['sessionadmin']['isUser'] = false;
        $_SESSION['sessionadmin']['msg'] = 'you are a guest';

        if ($existingUniqueId !== null) {
            $_SESSION['sessionadmin']['uniqueId'] = $existingUniqueId;
        }

        if ($existingTabs !== null) {
            $_SESSION['tabs'] = $existingTabs;
        }

        if (!$this->appIsSpa) {
            $_SESSION['sessionadmin']['allowedUrl'] = $this->allowedUrls;
            $_SESSION['sessionadmin']['urlIsAllowedToLoad'] = false;
        }
    }

    /**
     * Set the domain to current domain if not set
     * Set the secure value to whether the site is being accessed with SSL
     *
     * @return void
     */
    protected function preparesDomainAndSecureVars(): void
    {
        $this->domain ??= $_SERVER['SERVER_NAME'];
        $this->secure ??= isset($_SERVER['HTTPS']);
    }

    /**
     * Refreshes the session cookie lifetime and configures cookie parameters.
     *
     * When the session is already active (e.g. called from createUserSession() after
     * session_regenerate_id()), session_id() is used so the refreshed cookie carries
     * the current — possibly regenerated — session ID. Using the old request cookie
     * value ($_COOKIE) after regeneration would send a stale ID and cause the browser
     * to revert to the old session on the next request.
     *
     * session_set_cookie_params() is only effective before session_start(); calling it
     * on an already-active session has no effect, so it is guarded accordingly.
     *
     * @return void
     */
    protected function setSessionTime(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->setCookie($this->sessionName, session_id(), time() + $this->sessionLifetime, '/');
        } elseif ($this->getCookie($this->sessionName) !== null) {
            $this->setCookie($this->sessionName, (string) $this->getCookie($this->sessionName), time() + $this->sessionLifetime, '/');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->preparesDomainAndSecureVars();
            session_set_cookie_params($this->sessionLifetime, $this->path, $this->domain, $this->secure, true);
            ini_set('session.gc_maxlifetime', (string)$this->sessionLifetime);
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
        $time     = time();
        $previous = $_SESSION['sessionadmin']['time_atRequest'] ?? $time;
        $_SESSION['sessionadmin']['time_atRequest']        = $time;
        $_SESSION['sessionadmin']['time_sinceLastRequest'] = $time - $previous;
    }

    /**
     * Checks if the request was received within session time or it was obsolete
     *
     * @return bool true if request is obsolete
     */
    protected function requestIsObsolete(): bool
    {
        return $_SESSION['sessionadmin']['time_sinceLastRequest'] > $this->sessionLifetime;
    }

    /**
     * Checks if url that originated the request is in allowed URL array.
     * Uses SCRIPT_NAME (not PHP_SELF) to avoid PATH_INFO manipulation.
     *
     * @return void
     * @throws SessionAdminException
     */
    protected function checkIfUrlIsAllowed(): void
    {
        if(empty($_SESSION['sessionadmin']['allowedUrl'])) throw new SessionAdminException("the allowedUrl key is empty");
        $_SESSION['sessionadmin']['urlIsAllowedToLoad'] = FALSE;
        $url_to_check = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');

        if(\in_array($url_to_check, $this->ignoreInAuthorization)){
            return;
        }

        foreach($_SESSION['sessionadmin']['allowedUrl'] AS $allowed){
            $url_to_compare_against = $this->getSubStrAfterLast($allowed, '/');
            if($url_to_check == $url_to_compare_against){
                $_SESSION['sessionadmin']['urlIsAllowedToLoad'] = TRUE;
                break;
            }
        }
        if(!$_SESSION['sessionadmin']['urlIsAllowedToLoad'] && $this->useAuthorization){
            $this->redirectToIndex();
        }
    }

    /**
     * Checks if request is valid or a hijacking attempt
     *
     * @return bool true if hijacking detected, false for a valid request
     */
    protected function requestIsHijackingAttempt(): bool
    {
        if($this->proxyAwareIpDetection){
            $ipAddress = $this->getIpAddressProxyAware();
        }else{
            $ipAddress = $this->getIpAddressNoProxies();
        }
        $userAgent = $this->getUserAgent();

        if ($this->ipOctetsToCheck < 2 || $this->ipOctetsToCheck > 4) {
            throw new SessionAdminException("Ip octets to check must be in the 2 to 4 range");
        }
        $ipPrefix = $this->getIpPrefix($ipAddress, $this->ipOctetsToCheck);

        if (!isset($_SESSION['sessionadmin']['ipPrefix'], $_SESSION['sessionadmin']['userAgent'])) {
            $_SESSION['sessionadmin']['ipPrefix'] = $ipPrefix;
            $_SESSION['sessionadmin']['userAgent'] = $userAgent;
            return false;
        }

        if ($_SESSION['sessionadmin']['ipPrefix'] !== $ipPrefix) {
            return true;
        }

        if ($_SESSION['sessionadmin']['userAgent'] !== $userAgent) {
            return true;
        }

        return false;
    }

    /**
     * Obtains the client IP in a web server behind a proxy.
     * Use this if your app is behind Cloudflare, Nginx, Apache mod_proxy,
     * AWS ELB, etc. and you want the real client IP.
     *
     * Checks common proxy headers in order and handles comma-separated lists (X-Forwarded-For).
     * Validates IP format only — does NOT filter private/reserved ranges.
     * Falls back to REMOTE_ADDR if no valid IP is found in proxy headers.
     *
     * @return string
     */
    protected function getIpAddressProxyAware(): string
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
     * Obtains the ip address in a web server that does not use a proxy/load balancer.
     *
     * Use this if your app runs directly on a server and you don't trust
     * proxy headers (e.g., a VPS without Cloudflare or load balancer).
     *
     * @return string
     */
    protected function getIpAddressNoProxies(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Obtains the first 2, 3 or 4 octets from the ip according to ipOctetsToCheck
     *
     * @param string $ip
     * @param integer $parts
     * @return string
     */
    protected function getIpPrefix(string $ip, int $parts = 2): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $chunks = explode('.', $ip);
            return implode('.', \array_slice($chunks, 0, $parts));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $chunks = explode(':', $ip);
            return implode(':', \array_slice($chunks, 0, $parts));
        }

        return $ip;
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
        session_regenerate_id(false);

        $session_id = session_id();
        session_write_close();

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
     * Returns the substring after the last occurrence of needle in haystack.
     *
     * e.g., getSubStrAfterLast('dot.separated.string.parts', '.') → 'parts'
     * Returns the original string unchanged if needle is not found.
     *
     * @param string $haystack
     * @param string $needle
     * @return string
     */
    protected function getSubStrAfterLast(string $haystack, string $needle): string
    {
        $pos = strrpos($haystack, $needle);
        return $pos !== false ? substr($haystack, $pos + \strlen($needle)) : $haystack;
    }

    /**
     * Redirects the current request to index.php.
     * Handles normal browser requests, XHR/fetch calls, and preserves original URI.
     *
     * Override this method in subclasses (e.g., test fixtures) to intercept the redirect
     * without triggering headers or exit().
     *
     * @return void
     */
    protected function redirectToIndex(): void
    {
        $url = '/index.php';

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

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $status = ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') ? 307 : 303;

        if (!headers_sent()) {
            header("Location: {$url}", true, $status);
        }

        if ($isXhrOrFetch) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true, $status);
                header("X-Redirect-URL: {$url}");
            }
            echo json_encode(['redirect' => $url]);
            exit;
        }

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
     * Override this method in test subclasses to intercept cookie writes
     * without sending real HTTP headers.
     *
     * @param string $name
     * @param string $value
     * @param int|string|array $expiresOrOptions
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httponly
     *
     * @return bool
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
     * Override this method in test subclasses to read from an in-memory
     * cookie store instead of the real superglobal.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getCookie(string $name, mixed $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }
}

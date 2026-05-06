<?php

namespace Seba1rx\SessionAdmin;

use Seba1rx\SessionAdmin\Contracts\SessionInterface;

/**
 * Abstract public API layer for session management.
 *
 * Extend this class and define a constructor to configure your session.
 * There are no abstract methods — the minimum implementation is a constructor
 * that sets the desired properties (see template below).
 *
 * Implements SessionInterface so consumers can type-hint the contract
 * without depending on the concrete class.
 *
 * Template:
 *
 *   class MySession extends SessionAdmin
 *   {
 *       public function __construct()
 *       {
 *           $this->sessionName     = 'my_app';
 *           $this->sessionLifetime = 3600;
 *           $this->keys            = ['theme' => 'light'];
 *       }
 *   }
 */
abstract class SessionAdmin extends Session implements SessionInterface
{

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
     * When true, TabManager auto-indexes the current tab from the SESSIONADMIN_TABID
     * cookie on every request, without waiting for the JS client to call
     * /sessionadmin/new-tab.
     *
     * Useful when the application needs the tab entry to exist immediately on page load —
     * for example, to write tab-scoped data before the first JS beacon completes, or to
     * guarantee that a duplicated browser tab gets an entry even if the JS beacon was
     * skipped.
     *
     * @var bool
     */
    public bool $autoIndexTab = false;

    /**
     * Seconds after which inactive tabs are automatically removed on every call to
     * activateSession(). A tab is considered eligible for removal when its is_active
     * flag is false AND its last_active timestamp is older than this many seconds.
     *
     * Set to 0 (default) to disable automatic cleanup — tabs will persist until the
     * session expires naturally or until cleanupInactiveTabs() is called manually.
     *
     * Recommended value: 30–60 seconds when SESSIONADMIN_AUTO_DESTROY is enabled,
     * so closed-tab data is removed promptly on the next request from any open tab.
     *
     * @var int
     */
    public int $autoCleanupTabs = 0;

    /**
     * Sets the TabManager instance.
     *
     * Passes $autoIndexTab so TabManager can index the current tab immediately
     * from the SESSIONADMIN_TABID cookie when the flag is enabled.
     *
     * @return void
     */
    protected function setTabManager(): void
    {
        $this->tabManager = new TabManager($this->autoIndexTab);
    }

    /**
     * Plugs in a custom session storage backend.
     *
     * Must be called before activateSession(). Allows swapping PHP's default
     * file-based storage for any SessionHandlerInterface implementation
     * (Redis, database, Memcached, encrypted file store, etc.).
     *
     * The second argument to session_set_save_handler() is true so PHP
     * automatically calls session_write_close() on script shutdown, which
     * prevents data loss when exit() is called without an explicit close.
     *
     * @param \SessionHandlerInterface $handler Custom save handler instance.
     * @return void
     */
    public function setSessionHandler(\SessionHandlerInterface $handler): void
    {
        session_set_save_handler($handler, true);
    }

    /**
     * Starts a new session as guest or renews an existing user session.
     *
     * Configures the session cookie, starts the PHP session, enforces lifetime
     * and hijacking checks, optionally validates URL authorization (MPA), and
     * initialises the TabManager if tab indexation is enabled.
     *
     * @return void
     */
    public function activateSession(): void
    {
        // Guard against calling session_name() or session_start() on an already-active
        // session. This happens when bootstrap.php (loaded via autoload.files) starts
        // the session early — before the application's activateSession() call —
        // so that /sessionadmin/* endpoints can access the session data immediately.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($this->sessionName);
        }
        $this->setSessionTime();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['sessionadmin'])) {
            $_SESSION['sessionadmin'] = [];
        }

        $this->setSessionTimeStamps();

        if ($this->currentStateIsUser()) {
            $this->checkTime();
        } else {
            $this->configureGuestSession();
        }

        $_SESSION['sessionadmin']['appType'] = $this->appIsSpa ? 'SPA' : 'MPA';

        if (!isset($_SESSION['sessionadmin']['uniqueId'])) {
            $this->uniqueId = bin2hex(random_bytes(6));
            $_SESSION['sessionadmin']['uniqueId'] = $this->uniqueId;
        } else {
            $this->uniqueId = $_SESSION['sessionadmin']['uniqueId'];
        }

        if ($this->useAuthorization && !$this->appIsSpa) {
            $this->checkIfUrlIsAllowed();
        }

        if ($this->useTabIndexation) {
            $this->setTabManager();
            if ($this->autoCleanupTabs > 0) {
                $this->tabManager->cleanupInactiveTabs($this->autoCleanupTabs);
            }
        } else {
            unset($this->tabManager);
        }

        foreach ($this->keys as $key => $item) {
            if (!isset($_SESSION[$key])) {
                $_SESSION[$key] = $item;
            }
        }
    }

    /**
     * Marks the current session as authenticated and stores the user identifier.
     *
     * Regenerates the session ID to prevent session fixation attacks,
     * then refreshes cookie lifetime and timestamps.
     *
     * @param mixed $id_user Any scalar identifier (int, string, etc.)
     * @return void
     */
    public function createUserSession(mixed $id_user): void
    {
        $_SESSION['sessionadmin']['isUser'] = true;
        $_SESSION['sessionadmin']['msg'] = 'you are a user';
        $_SESSION['sessionadmin']['id_user'] = $id_user;

        $this->regenerateSession();
        $this->setSessionTime();
        $this->setSessionTimeStamps();
    }

    /**
     * Terminates the session by wiping out all session data, reinitialises as guest,
     * and redirects to index (MPA only, when $terminateRedirects is true).
     *
     * @return void
     */
    public function terminate(): void
    {
        $_SESSION = [];
        $this->destroySession();

        if (!$this->appIsSpa && $this->terminateRedirects) {
            $this->redirectToIndex();
        }
    }

    /**
     * Wipes all session data, destroys the current session, and opens a fresh guest session.
     *
     * session_destroy() requires an active session; session_write_close() must NOT be called
     * before it, as that would leave the session in PHP_SESSION_NONE and trigger a warning.
     *
     * @return void
     */
    protected function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->activateSession();
    }

    /**
     * Calls to destroySession if request is obsolete.
     * If request is still on time, checks for hijacking attempt and destroys
     * the session if one is detected, or randomly regenerates the session ID.
     *
     * @return void
     */
    protected function checkTime(): void
    {
        if ($this->requestIsObsolete()) {
            $this->destroySession();
        } else {
            if ($this->requestIsHijackingAttempt()) {
                $this->destroySession();
            } elseif ($this->shouldRandomlyRegenerate()) {
                $this->regenerateSession();
            }
        }
    }

}

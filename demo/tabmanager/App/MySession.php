<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * Demo SessionAdmin implementation.
 *
 * Extends SessionAdmin with a fixed session name and lifetime.
 * The bridge is injected via setTabHandler() in every entry point,
 * not here — that keeps this class decoupled from the concrete TabManager class.
 */
class MySession extends SessionAdmin
{
    public function __construct()
    {
        $this->sessionName     = 'tabmanager_demo';
        $this->sessionLifetime = 600;  // 10 minutes

        // Prune inactive tabs older than 30 s on every activateSession() call.
        // Requires setTabHandler() to have been called first.
        $this->autoCleanupTabs = 30;
    }
}

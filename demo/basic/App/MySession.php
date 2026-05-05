<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * Minimal SessionAdmin implementation — extend and define a constructor.
 *
 * This is all you need to start using the package.
 */
class MySession extends SessionAdmin
{
    public function __construct()
    {
        $this->sessionName     = 'basic_demo';
        $this->sessionLifetime = 300; // 5 minutes

        // Pre-seed a custom key that will be available in $_SESSION from the start
        $this->keys = ['app_theme' => 'light'];

        // Tab indexation is disabled in this demo to keep things simple.
        // Set useTabIndexation = true (and include the JS client) to enable per-tab storage.
        $this->useTabIndexation = false;

        // appIsSpa = true (default): no MPA URL authorization, no redirect on terminate().
        // Set appIsSpa = false for traditional multi-page apps.
    }
}

<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * Advanced demo — SPA with per-tab session isolation.
 *
 * Extend SessionAdmin and define a constructor that configures the session.
 *
 * @param array $conf  Keys: sessionLifetime, allowedURLs, keys
 */
class MyTABSessionAdmin extends SessionAdmin
{
    public function __construct(array $conf = [])
    {
        $this->sessionName = 'MyCustomTABSessionName';

        if (isset($conf['sessionLifetime'])) {
            $this->sessionLifetime = $conf['sessionLifetime'];
        }

        if (isset($conf['allowedURLs'])) {
            foreach ($conf['allowedURLs'] as $page) {
                if (!\in_array($page, $this->allowedUrls)) {
                    $this->allowedUrls[] = $page;
                }
            }
        }

        if (isset($conf['keys'])) {
            foreach ($conf['keys'] as $key => $value) {
                $this->keys[$key] = $value;
            }
        }
    }
}

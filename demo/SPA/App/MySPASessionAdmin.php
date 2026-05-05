<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * SPA demo — single-entry-point app without tab indexation.
 *
 * Extend SessionAdmin and define a constructor that configures the session.
 *
 * @param array $conf  Keys: sessionLifetime, allowedURLs, keys
 */
class MySPASessionAdmin extends SessionAdmin
{
    public function __construct(array $conf = [])
    {
        $this->sessionName = 'MyCustomSPASessionName';

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

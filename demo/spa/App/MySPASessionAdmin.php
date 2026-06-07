<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/** SPA demo — single-entry-point app without tab indexation. */
class MySPASessionAdmin extends SessionAdmin
{
    /**
     * @param array $conf  Keys: sessionLifetime, allowedURLs, keys
     */
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

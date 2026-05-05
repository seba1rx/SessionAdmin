<?php

namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

/** Advanced demo — SPA with per-tab session isolation via TabManager. */
class MyTABSessionAdmin extends SessionAdmin
{
    /**
     * @param array $conf  Keys: sessionLifetime, allowedURLs, keys
     */
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

<?php

namespace MPA\AppFiles;

include __DIR__ . "/../vendor/autoload.php";
include __DIR__ . "/../../../vendor/autoload.php";

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * Intermediate demo — MPA (Multi-Page Application) with URL authorization.
 *
 * Extend SessionAdmin and define a constructor that configures the session.
 * You can pass a $conf array or hard-code values directly as properties.
 *
 * @param array $conf  Keys: sessionLifetime, allowedURLs, keys
 */
class MyMPASessionAdmin extends SessionAdmin
{
    public function __construct(array $conf = [])
    {
        $this->sessionName = 'MyCustomMPASessionName';

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

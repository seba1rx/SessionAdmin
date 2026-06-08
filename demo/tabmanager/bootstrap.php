<?php
/**
 * bootstrap.php — Demo-only session configuration
 *
 * Redirects PHP session storage to demo/sessions/ for isolation.
 * This is NOT part of the integration — in a real app configure session
 * storage in your framework or php.ini.
 *
 * Must be included BEFORE vendor/autoload.php.
 */

$sessionsDir = __DIR__ . '/sessions';
if (!is_dir($sessionsDir)) {
    mkdir($sessionsDir, 0755);
}
session_save_path($sessionsDir);

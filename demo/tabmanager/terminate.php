<?php
/**
 * terminate.php — Destroy session and redirect to home.
 *
 * Uses SessionAdmin::terminate() which wipes $_SESSION, destroys the session,
 * and re-initialises a fresh guest session. In SPA mode (default) it does
 * not redirect, so we redirect manually.
 *
 * Both sessionadmin and tabmanager data are wiped — terminate() clears all
 * of $_SESSION, not just the sessionadmin slice.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;

$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();

// terminate() wipes everything (auth + all tab data) and reinitialises as guest.
$session->terminate();

header('Location: index.php');
exit;

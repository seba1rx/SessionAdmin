<?php

require 'AppFiles/required.php';

// terminate() destroys the session, opens a fresh guest session,
// and redirects to index.php (because appIsSpa = false in required.php).
$sessionAdmin->terminate();

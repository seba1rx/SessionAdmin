# SPA: Single Page Application demo

in a SPA you use a front controller tipically in a "public" directory, but for simplicity's sake I just placed it in the root of the SPA demo.

You can see an implementation of the SessionAdmin class in the file MySPASessionAdmin.php

## What you should be paying attention to in this SPA demo:

+ config/session.php (you would normally bootstrap it in your app)
+ App/MySPASessionAdmin.php
+ App/Authentication.php (check the logic in order to call createUserSession())
+ The session is configured (config/session.php) to last only 120 seconds, so try waiting and then try interacting with the app again
+ Each time you create a request you will see that 'time_atRequest' and 'time_sinceLastRequest' reset, this means that the time limit for the session resets to the defined max (120 sec in this demo)
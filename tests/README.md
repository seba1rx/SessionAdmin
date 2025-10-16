    vendor/bin/phpunit --testdox tests/SessionAdminTest.php


expected output:

```
Session Lifecycle
 ✔ Activate session creates guest session
 ✔ Create user session sets expected session data
Security & IP
 ✔ Request is hijacking attempt returns false when session fresh
 ✔ Get ip prefix extracts correct prefix
Utility
 ✔ Get substr after last returns correct segment
 ✔ Strrevpos finds reverse position
Cookie Handling
 ✔ Set cookie stores values with expected attributes
 ✔ Get cookie value returns existing cookie
 ✔ Get cookie value returns null when missing
 ✔ Delete cookie removes cookie correctly
Configuration Integrity
 ✔ Constructor applies configuration array
 ✔ Activate session loads configured keys
 ✔ Activate session with authorization enabled
 ✔ Configuration defaults when no values provided
```

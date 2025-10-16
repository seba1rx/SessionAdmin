Test each class:
```
vendor/bin/phpunit --testdox tests/SessionAdminServerTest.php --display-all-issues

vendor/bin/phpunit --testdox tests/SessionAdminTest.php --display-all-issues
```

Test both classes in specific order
```
vendor/bin/phpunit --testdox tests/SessionAdminServerTest.php tests/SessionAdminTest.php --display-all-issues
```

if you want to filter a specific test method:
```
vendor/bin/phpunit --testdox tests/SessionAdminTest.php --filter testMethodName
```



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

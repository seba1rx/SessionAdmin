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
Session Admin (Seba1rx\SessionAdmin\SessionAdmin)
 ✔ Activate session creates guest session
 ✔ Create user session sets expected session data
 ✔ Request is hijacking attempt returns false when session fresh
 ✔ Get ip prefix extracts correct prefix
 ✔ Get sub str after last returns correct segment
 ✔ Strrevpos finds reverse position
 ✔ Set cookie stores values with expected attributes
 ✔ Get cookie value returns existing cookie
 ✔ Get cookie value returns null when missing
 ✔ Constructor applies configuration array
 ✔ Activate session loads configured keys
 ✔ Activate session with authorization enabled
 ✔ Configuration defaults when no values provided

Session Admin Server
 ✔ Constructor initializes session key
 ✔ Set and get data for tab
 ✔ Set does nothing without tab id
 ✔ Get returns default when missing
 ✔ Destroy tab session removes data
 ✔ Mark inactive tab
 ✔ Debug returns expected structure
 ✔ Get session key returns constant

OK (21 tests, 59 assertions)
```

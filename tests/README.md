# Tests

Test each class individually:
```bash
vendor/bin/phpunit --testdox tests/TabManagerTest.php --display-all-issues
vendor/bin/phpunit --testdox tests/SessionAdminTest.php --display-all-issues
```

Test both classes in defined order:
```bash
vendor/bin/phpunit --testdox tests/TabManagerTest.php tests/SessionAdminTest.php --display-all-issues
```

Or via Composer:
```bash
composer test
```

## Notes

- `SessionTestable` (in `tests/`) exposes protected methods from `Session` / `SessionAdmin` via reflection and provides `mock*` overrides for `setCookie` / `getCookie` / `session_*`, allowing tests to run without real HTTP headers.
- Coverage reports may show zero for `SessionAdminTest` because it accesses private methods via reflection — this is expected.

## Expected output

```
OK (82 tests, 158 assertions)
```

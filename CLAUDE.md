# seba1rx/sessionadmin

PHP 8.1+ library that wraps native PHP sessions with security hardening and URL authorization (MPA).

## Development rules

These rules apply to every change, no matter how small:

- **README sync** — update `README.md` whenever a public API, configuration option, or behaviour changes. Keep the usage examples accurate.
- **Demo review** — review each demo in `demo/` and update if the change affects configuration, method signatures, or visible behaviour.
- **Tests and coverage** — every new public method and every significant behaviour change must have corresponding PHPUnit test cases. Run `composer test` before committing. Run `composer coverage` to verify coverage is not regressing.
- **Docblocks required** — every class, interface, property, and method must have a PHPDoc comment. Use `@param`, `@return`, and `@throws` tags. Implementations that inherit the interface docblock may omit redundant tags, but must not omit the docblock entirely.
- **Commit message** — at the end of every task that produced code changes, provide a ready-to-copy `git commit -m` suggestion. One subject line (≤72 chars, imperative mood), optional body bullet points for non-obvious details. Do not commit — just print the message.

## Commands

```bash
composer test                    # run full test suite (PHPUnit)
```

## Architecture

### Class hierarchy

```
Contracts/SessionInterface
        │
        ▼
Session  (abstract)
  └── SessionAdmin  (abstract, implements SessionInterface)
        └── YourImplementation  (concrete, user-defined)
```

**`src/Contracts/SessionInterface.php`** — Public API contract for `SessionAdmin`. Type-hint against this when you need to swap or mock the session implementation:
- `activateSession(): void`
- `createUserSession(mixed $id_user): void`
- `terminate(): void`

**`src/Session.php`** — Abstract base. All security and session mechanics live here as `protected` methods:
- Session cookie configuration (`setSessionTime`, `setSessionTimeStamps`)
- Hijacking detection: compares IP prefix + User-Agent on every request (`requestIsHijackingAttempt`)
- IP resolution: `getIpAddressProxyAware()` (checks `HTTP_X_FORWARDED_FOR` chain) or `getIpAddressNoProxies()` (`REMOTE_ADDR` only)
- URL authorization for MPA: `checkIfUrlIsAllowed()` matches `basename($_SERVER['SCRIPT_NAME'])` against `$allowedUrls`
- Session regeneration: on login and randomly 3% of requests (`shouldRandomlyRegenerate`)
- `setCookie()` / `getCookie()` wrappers — override in test subclasses to intercept cookie I/O without sending headers
- `redirectToIndex()` — override in test subclasses to intercept redirects without calling `exit()`

**`src/SessionAdmin.php`** — Abstract public API layer. Implements `SessionInterface`. Extend this in your app:
- `activateSession()` — call instead of `session_start()`; runs the full boot sequence
- `createUserSession($id)` — marks session as authenticated, regenerates session ID
- `terminate()` — destroys session, re-initialises as guest, redirects to `index.php` (MPA only)
- `setSessionHandler(\SessionHandlerInterface $handler)` — plugs in a custom session storage backend (Redis, DB, etc.); must be called before `activateSession()`

### Session data layout

```
$_SESSION
└── sessionadmin/
    ├── appType             string — 'SPA' | 'MPA' (always present)
    ├── isUser              bool   — true for authenticated users
    ├── id_user             mixed  — value passed to createUserSession()
    ├── msg                 string — 'you are a user' | 'you are a guest'
    ├── allowedUrl          array  — copy of $allowedUrls  (MPA only)
    ├── urlIsAllowedToLoad  bool                           (MPA only)
    ├── uniqueId            string — 12-char hex, stable for session lifetime
    ├── ipPrefix            string — first N octets of client IP
    ├── userAgent           string
    ├── time_atRequest      int    — Unix timestamp of last request
    └── time_sinceLastRequest int  — seconds since previous request
```

### Configuration properties

Set these on your implementation instance before calling `activateSession()`:

| Property | Default | Purpose |
|---|---|---|
| `$sessionLifetime` | `2400` | Seconds before idle session expires |
| `$sessionName` | `'SESSION'` | PHP session name / cookie name |
| `$useAuthorization` | `false` | Enforce `$allowedUrls` check (MPA) |
| `$appIsSpa` | `true` | Disables URL check and index redirect for SPAs |
| `$ipOctetsToCheck` | `2` | How many IP octets to compare (2–4) |
| `$proxyAwareIpDetection` | `true` | Read IP from proxy headers |
| `$terminateRedirects` | `true` | Redirect to index on `terminate()` |
| `$ignoreInAuthorization` | `[]` | Files excluded from URL auth check |

### Composer plugin (`build/SessionAdminPlugin.php`)

Registered as a Composer plugin (`"type": "composer-plugin"`). Currently a no-op stub kept for backwards compatibility — consumers who already depend on the plugin class in their `composer.json` do not break on update.

## Implementing the package

```php
// App/MySession.php
namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

class MySession extends SessionAdmin
{
    public function __construct()
    {
        $this->sessionName     = 'my_app';
        $this->sessionLifetime = 3600;
        $this->keys            = ['theme' => 'light'];   // pre-seeded session keys
    }
}

// index.php (every page entry point)
require 'vendor/autoload.php';

$session = new App\MySession();
$session->activateSession();

// On login:
$session->createUserSession($userId);

// On logout:
$session->terminate();

// Check auth:
if (!empty($_SESSION['sessionadmin']['isUser'])) { /* ... */ }
```

## Testing

`tests/SessionTestable.php` is a concrete-but-abstract subclass of `SessionAdmin` that:
- Overrides `setCookie()` / `getCookie()` to store cookies in `$this->cookies` (no HTTP headers)
- Overrides `redirectToIndex()` to set `$this->redirectCalled = true` (no `exit()`)
- Exposes every protected method via a public `call*()` wrapper

`SessionAdminTest` uses an anonymous subclass of `SessionAdmin` with the same overrides, accessed via Reflection — `SessionTestable` is available for custom test scenarios.

PHPUnit configuration is in `phpunit.xml`. Run with `composer test`.

## Demos

| Directory | Description |
|---|---|
| `demo/basic/` | Minimal single-file demo: login/logout |
| `demo/MPA/` | Multi-page app: URL authorization, `$allowedUrls`, multiple PHP pages |
| `demo/SPA/` | Single-page app: SPA mode, AJAX login |

Each demo has its own `composer.json` and `vendor/`.

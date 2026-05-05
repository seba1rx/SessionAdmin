# seba1rx/sessionadmin

PHP 8.1+ library that wraps native PHP sessions with security hardening, URL authorization (MPA), and optional per-browser-tab session isolation.

## Development rules

These rules apply to every change, no matter how small:

- **README sync** — update `README.md` whenever a public API, configuration option, or behaviour changes. Keep the usage examples accurate.
- **Demo review** — review each demo in `demo/` and update if the change affects configuration, method signatures, or visible behaviour.
- **Tests and coverage** — every new public method and every significant behaviour change must have corresponding PHPUnit test cases. Run `composer test` before committing. Run `composer coverage` to verify coverage is not regressing.
- **Docblocks required** — every class, interface, property, and method must have a PHPDoc comment. Use `@param`, `@return`, and `@throws` tags. Implementations that inherit the interface docblock may omit redundant tags, but must not omit the docblock entirely.

## Commands

```bash
composer test                    # run full test suite (PHPUnit)
composer run publish-assets      # copy seba1rx_sessionAdmin.js to project root
```

## Architecture

### Class hierarchy

```
Contracts/SessionInterface          Contracts/TabStorageInterface
        │                                       │
        ▼                                       ▼
Session  (abstract)                       TabManager
  └── SessionAdmin  (abstract, implements SessionInterface)
        └── YourImplementation  (concrete, user-defined)
```

**`src/Contracts/SessionInterface.php`** — Public API contract for `SessionAdmin`. Type-hint against this when you need to swap or mock the session implementation:
- `activateSession(): void`
- `createUserSession(mixed $id_user): void`
- `terminate(): void`

**`src/Contracts/TabStorageInterface.php`** — Contract for per-tab scoped storage. Type-hint against this to decouple callers from the concrete `TabManager`:
- `set(string $key, mixed $value): void`
- `get(string $key, mixed $default = null): mixed`
- `isTabIndexed(?string $tabId = null): bool`
- `cleanupInactiveTabs(int $olderThanSeconds): int`

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
- Also owns tab isolation members: `$tabManager`, `$useTabIndexation`, `setTabManager()`

**`src/TabManager.php`** — Per-tab session isolation. Keyed under `$_SESSION['tabs'][$tabUuid]`:
- Tab UUID comes from the `SESSIONADMIN_TABID` cookie set by the JS client
- `indexNewTab(string $tabId)` — creates the tab entry
- `isTabIndexed(?string $tabId)` — checks whether a tab has an entry
- `set(string $key, mixed $value)` / `get(string $key, mixed $default)` — tab-scoped storage
- `markInactiveTab(string $tabId)` — soft-deactivation (fired via beforeunload)
- `destroyTabSession(string $tabId)` — hard delete
- `cleanupInactiveTabs(int $olderThanSeconds)` — removes stale inactive tabs; returns count removed
- `debug()` — returns structured summary of all tracked tabs
- `generateUuid()` — RFC 4122 UUIDv4 via `random_bytes()`
- `isValidUuid(string $uuid, bool $onlyV4 = true)` — validates UUID format
- Constructor accepts `bool $autoIndex = false`: when true, auto-indexes the current tab from cookie without waiting for the JS endpoint

### Session data layout

```
$_SESSION
├── sessionadmin/
│   ├── isUser              bool   — true for authenticated users
│   ├── id_user             mixed  — value passed to createUserSession()
│   ├── msg                 string — 'you are a user' | 'you are a guest'
│   ├── allowedUrl          array  — copy of $allowedUrls
│   ├── urlIsAllowedToLoad  bool
│   ├── uniqueId            string — 12-char hex, stable for session lifetime
│   ├── ipPrefix            string — first N octets of client IP
│   ├── userAgent           string
│   ├── time_atRequest      int    — Unix timestamp of last request
│   └── time_sinceLastRequest int  — seconds since previous request
└── tabs/
    └── {uuid}/
        ├── data        array  — key/value store for this tab
        ├── is_active   bool
        └── last_active int    — Unix timestamp
```

### Configuration properties

Set these on your implementation instance before calling `activateSession()`:

| Property | Default | Purpose |
|---|---|---|
| `$sessionLifetime` | `2400` | Seconds before idle session expires |
| `$sessionName` | `'SESSION'` | PHP session name / cookie name |
| `$useAuthorization` | `false` | Enforce `$allowedUrls` check (MPA) |
| `$appIsSpa` | `true` | Disables URL check and index redirect for SPAs |
| `$useTabIndexation` | `true` | Enable TabManager |
| `$ipOctetsToCheck` | `2` | How many IP octets to compare (2–4) |
| `$proxyAwareIpDetection` | `true` | Read IP from proxy headers |
| `$terminateRedirects` | `true` | Redirect to index on `terminate()` |
| `$ignoreInAuthorization` | `[]` | Files excluded from URL auth check |

### Bootstrap endpoints (`bin/bootstrap.php`)

Included via `autoload.files` — active on every request once the package is installed. Intercepts URIs starting with `/sessionadmin/` before returning control to the application. Internal routing is handled by the `SessionAdminBootstrap` final class defined in that file, avoiding global function namespace pollution.

| Method | URI | Purpose |
|---|---|---|
| POST | `/sessionadmin/new-tab` | Index a new tab (called by JS on tab open) |
| POST | `/sessionadmin/tab-close` | Mark tab inactive (called by JS on beforeunload) |
| GET | `/sessionadmin/debug` | JSON or HTML debug view (localhost or `SESSIONADMIN_DEBUG` only) |
| POST | `/sessionadmin/debug/delete-tab` | Destroy a tab's data |

The debug HTML view is enabled when `define('SESSIONADMIN_DEBUG_UI', true)` is set before the request reaches bootstrap. The JSON response never includes raw `$_SESSION` data — only the structured tab summary from `TabManager::debug()`.

### JS client (`assets/seba1rx_sessionAdmin.js`)

Copy this file to the project's public assets directory (done automatically by the Composer plugin on install/update).

Responsibilities:
- Generates a UUIDv4 per browser tab using `crypto.randomUUID()` (CSPRNG — not `Math.random()`)
- Persists the UUID in `sessionStorage` only — `window.name` is intentionally NOT used as a fallback (it persists across same-tab navigations to different origins, making it readable by other sites)
- Syncs the UUID to the `SESSIONADMIN_TABID` cookie (server-facing name) on every page load
- Calls `/sessionadmin/new-tab` via `sendBeacon` only when the tab is genuinely new or the cookie was stale — skips the call on plain refreshes
- Registers a `beforeunload` handler to call `/sessionadmin/tab-close` only when `window.SESSIONADMIN_AUTO_DESTROY = true`
- Uses a `readyState` guard so `init()` fires correctly whether the script loads before or after `DOMContentLoaded`

`sessionStorage` key (`unique-tab-id`) and cookie name (`SESSIONADMIN_TABID`) are intentionally different: one is browser-internal, the other is server-facing.

Optional flags (set before the script loads):
```js
window.SESSIONADMIN_AUTO_DESTROY = true;   // notify server on tab close
window.SESSIONADMIN_DEBUG = true;          // console.log tab UUID and status
```

### Composer plugin (`build/SessionAdminPlugin.php`)

Registered as a Composer plugin (`"type": "composer-plugin"`). Subscribes to `POST_INSTALL_CMD` and `POST_UPDATE_CMD` so the JS asset is published automatically both when this package is the root and when it is installed as a dependency. The static `publishAssets(Event)` method is also exposed for `composer run publish-assets`.

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
$session->useTabIndexation = false; // or true for per-tab data
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
| `demo/basic/` | Minimal single-file demo: login/logout, no tab indexation |
| `demo/MPA/` | Multi-page app: URL authorization, `$allowedUrls`, multiple PHP pages |
| `demo/SPA/` | Single-page app: SPA mode, tab indexation disabled, AJAX login |
| `demo/TABS/` | Full-featured: tab indexation, TabManager CRUD, debug endpoint UI |

Each demo has its own `composer.json` and `vendor/`.

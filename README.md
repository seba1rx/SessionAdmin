# seba1rx/sessionadmin

PHP session management library with security hardening and optional per-browser-tab session isolation.

```bash
composer require seba1rx/sessionadmin
```

---

## Features

**Session security**
- Named sessions with configurable cookie parameters
- Hijacking detection: IP prefix + User-Agent fingerprint verified on every request
- Proxy-aware IP detection (reads `X-Forwarded-For` and equivalent headers)
- Session destruction when a request arrives after the configured lifetime
- Session ID regenerated on login and randomly (~3% of requests) to resist fixation

**URL authorization (MPA)**
- Define an `$allowedUrls` list; guests are redirected to `index.php` on any unlisted page
- Expandable per user role or profile
- Disable entirely for SPA apps (`$appIsSpa = true`, the default)

**Per-tab session isolation**
- Each browser tab gets its own scoped `$_SESSION` space via a UUID cookie
- Tabs tracked server-side: active/inactive status, last-active timestamp, stored keys
- Automatic stale-tab cleanup via `TabManager::cleanupInactiveTabs()`
- Optional close notification when a tab is closed (`SESSIONADMIN_AUTO_DESTROY`)
- Built-in debug interface (HTML table or JSON)
- Zero manual routing — endpoints registered automatically via `autoload.files`

---

## Quick start

`SessionAdmin` is abstract with no abstract methods. Extend it and define a constructor:

```php
// App/MySession.php
namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

class MySession extends SessionAdmin
{
    public function __construct()
    {
        $this->sessionName     = 'my_app';
        $this->sessionLifetime = 3600;          // seconds
        $this->keys            = ['theme' => 'light']; // pre-seeded session keys
    }
}
```

Then on every entry point, before any output:

```php
require 'vendor/autoload.php';

$session = new App\MySession();
$session->useAuthorization  = false; // true for MPA URL enforcement
$session->useTabIndexation  = true;  // false to disable TabManager
$session->activateSession();         // replaces session_start()

// On login:
$session->createUserSession($userId);

// On logout:
$session->terminate();

// Auth check:
if (!empty($_SESSION['sessionadmin']['isUser'])) {
    // authenticated
}
```

### Public API

| Method | Description |
|---|---|
| `activateSession()` | Starts or resumes the session; runs all security checks |
| `createUserSession(mixed $id)` | Marks session as authenticated, regenerates session ID |
| `terminate()` | Destroys session, reinitialises as guest, redirects to `index.php` (MPA) |
| `setSessionHandler(\SessionHandlerInterface $handler)` | Plug in a custom storage backend; call before `activateSession()` |

The full class is documented via docblocks — your IDE will surface every property and its purpose.

### Session data written to `$_SESSION['sessionadmin']`

| Key | Present | Description |
|---|---|---|
| `appType` | Always | `'SPA'` or `'MPA'` — reflects the `$appIsSpa` flag |
| `isUser` | Always | `true` when authenticated, `false` for guests |
| `id_user` | After login | Value passed to `createUserSession()` |
| `msg` | Always | Human-readable state label |
| `uniqueId` | Always | 12-char hex token, stable for the session lifetime |
| `ipPrefix` | Always | First N octets of the client IP (hijacking detection) |
| `userAgent` | Always | User-Agent string (hijacking detection) |
| `time_atRequest` | Always | Unix timestamp of the last request |
| `time_sinceLastRequest` | Always | Seconds elapsed since the previous request |
| `allowedUrl` | **MPA only** | Copy of `$allowedUrls` used for URL authorization |
| `urlIsAllowedToLoad` | **MPA only** | `true` when the current URL is in the allow-list |

`allowedUrl` and `urlIsAllowedToLoad` are omitted entirely in SPA mode — they only make sense when URL authorization is active.

---

## Custom session storage

By default the package uses PHP's native file-based session storage. Pass any [`SessionHandlerInterface`](https://www.php.net/manual/en/class.sessionhandlerinterface.php) implementation to `setSessionHandler()` before calling `activateSession()` to swap the backend:

```php
$session = new App\MySession();
$session->setSessionHandler(new RedisSessionHandler($redis));
$session->activateSession();
```

Any PSR-compatible or custom handler works — Redis, database, encrypted file store, etc. The handler must be set **before** `activateSession()` because PHP applies the handler prior to calling `session_start()`.

---

## Contracts (interfaces)

The package ships two interfaces under `Seba1rx\SessionAdmin\Contracts` that you can type-hint against to decouple your code from the concrete classes:

| Interface | Implemented by | Methods |
|---|---|---|
| `SessionInterface` | `SessionAdmin` | `activateSession()`, `createUserSession()`, `terminate()` |
| `TabStorageInterface` | `TabManager` | `set()`, `get()`, `isTabIndexed()`, `cleanupInactiveTabs()` |

**Example — type-hint in a service class:**

```php
use Seba1rx\SessionAdmin\Contracts\SessionInterface;
use Seba1rx\SessionAdmin\Contracts\TabStorageInterface;

class CartService
{
    public function __construct(
        private readonly SessionInterface    $session,
        private readonly TabStorageInterface $tabs,
    ) {}

    public function addItem(string $sku, int $qty): void
    {
        $cart = $this->tabs->get('cart', []);
        $cart[$sku] = ($cart[$sku] ?? 0) + $qty;
        $this->tabs->set('cart', $cart);
    }
}
```

**Example — mock in tests:**

```php
$mockSession = $this->createMock(SessionInterface::class);
$mockSession->expects($this->once())->method('activateSession');
```

---

## Tab isolation

When `$useTabIndexation = true` (the default), a `TabManager` instance is available at `$session->tabManager` after `activateSession()`.

**PHP:**

```php
// Store data scoped to the current browser tab
$session->tabManager->set('cart', ['apple' => 3]);

// Retrieve it
$cart = $session->tabManager->get('cart');

// Check whether the current tab has a session entry
$session->tabManager->isTabIndexed();

// Remove stale inactive tabs (e.g. call periodically to prevent session bloat)
$session->tabManager->cleanupInactiveTabs(3600); // remove tabs inactive for > 1 hour
```

**HTML — include the JS client** (published to your project root on `composer install`):

```html
<script src="/seba1rx_sessionAdmin.js"></script>
```

Optional flags (set before the script loads):

```html
<script>
    window.SESSIONADMIN_AUTO_DESTROY = true;  // notify server on tab close
    window.SESSIONADMIN_DEBUG        = true;  // log tab UUID to console
</script>
```

The script generates a UUIDv4 per tab using `crypto.randomUUID()`, persists it in `sessionStorage`, and writes it to the `SESSIONADMIN_TABID` cookie so every PHP request can identify its tab. The server is notified only when a tab is genuinely new — not on every refresh.

---

## Built-in endpoints

Registered automatically via `autoload.files` — no route configuration needed:

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/sessionadmin/new-tab` | Index a new tab (called by JS on open) |
| `POST` | `/sessionadmin/tab-close` | Mark tab inactive (called by JS on close) |
| `GET`  | `/sessionadmin/debug` | Tab debug info (JSON or HTML) |
| `POST` | `/sessionadmin/debug/delete-tab` | Destroy a specific tab's data |

---

## Debug interface

Define these constants in your bootstrap before `activateSession()`:

| Constant | Effect |
|---|---|
| `SESSIONADMIN_DEBUG` | Enables the `/sessionadmin/debug` endpoint from any host |
| `SESSIONADMIN_DEBUG_UI` | Renders the debug endpoint as an interactive HTML table |

```php
define('SESSIONADMIN_DEBUG',    true);
define('SESSIONADMIN_DEBUG_UI', true);
```

The endpoint is always accessible from `localhost` / `127.0.0.1` regardless of `SESSIONADMIN_DEBUG`.

Visit `http://localhost/sessionadmin/debug` to see a live table of tracked tabs:

| Tab UUID | Status | Last active | Keys | Size | Action |
|---|---|---|---|---|---|
| `7ac3d...` | Active | 2025-01-14 15:23 | cart, user | 132 | Delete |

JSON response (without `SESSIONADMIN_DEBUG_UI`):

```json
{
    "package": "seba1rx/sessionadmin",
    "session_key": "tabs",
    "current_tab": "7ac3d...",
    "tabs": {
        "7ac3d...": {
            "is_active": true,
            "last_active": "2025-01-14 15:23:00",
            "keys": ["cart", "user"],
            "size": 132
        }
    }
}
```

---

## Demos

| Demo | Description |
|---|---|
| [`demo/basic/`](demo/basic/) | Minimal login/logout, no tab indexation — the simplest possible implementation |
| [`demo/MPA/`](demo/MPA/) | Multi-page app with URL authorization and `$allowedUrls` |
| [`demo/SPA/`](demo/SPA/) | Single-page app, SPA mode, AJAX login |
| [`demo/TABS/`](demo/TABS/) | Full feature set: tab isolation, TabManager CRUD, debug UI |

Each demo is self-contained with its own `composer.json`.

### Running a demo locally

1. Install dependencies for the chosen demo:

```bash
cd demo/TABS
composer install
```

2. Start PHP's built-in web server from the demo directory:

```bash
php -S localhost:8000
```

3. Open your browser and navigate to:

```
http://localhost:8000
```

> The built-in server serves `index.php` by default. Change the port if `8000` is already in use (`php -S localhost:8080`).

To run the debug endpoint, open a second tab and visit `http://localhost:8000/sessionadmin/debug`. If the HTML table is not visible, add `define('SESSIONADMIN_DEBUG_UI', true)` to the demo's bootstrap before starting the server.

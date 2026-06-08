# seba1rx/sessionadmin

PHP session management library with security hardening and URL authorization.

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
| `setTabHandler(TabHandlerInterface $handler)` | Inject a tab handler (e.g. `seba1rx/tabmanager`); call before `activateSession()` |

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

## Tab isolation (optional)

Per-browser-tab session isolation is provided by the companion package [`seba1rx/tabmanager`](https://github.com/seba1rx/tabmanager), which implements `TabHandlerInterface`.

```bash
composer require seba1rx/tabmanager
```

Inject it before calling `activateSession()` using `SessionAdminBridge` — the integration class shipped with tabmanager:

```php
use Seba1rx\TabManager\Bridge\SessionAdminBridge;

$session = new App\MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->autoCleanupTabs = 30; // optional: remove tabs inactive for > 30 s
$session->activateSession();

// After the JS client has registered the tab:
$session->tabHandler->set('cart', ['apple' => 3]);
$cart  = $session->tabHandler->get('cart');
$ready = $session->tabHandler->isTabIndexed(); // false until JS registers the tab
```

`SessionAdminBridge` extends `TabManager` but does not call `session_start()` in its constructor — `activateSession()` owns the session lifecycle and configures the session name and cookie parameters before the session starts. Both packages write to distinct keys in `$_SESSION` (`sessionadmin` vs `tabmanager`) and do not interfere with each other.

---

## Contracts (interfaces)

The package ships two interfaces under `Seba1rx\SessionAdmin\Contracts`:

| Interface | Role | Key methods |
|---|---|---|
| `SessionInterface` | Implemented by `SessionAdmin` | `activateSession()`, `createUserSession()`, `terminate()` |
| `TabHandlerInterface` | Implemented by `seba1rx/tabmanager` | `set()`, `get()`, `isTabIndexed()`, `cleanupInactiveTabs()`, … |

`TabHandlerInterface` defines the full tab lifecycle contract. Any class implementing it can be injected via `setTabHandler()` — SessionAdmin never depends on the concrete `TabManager` class.

**Example — mock session in tests:**

```php
$mockSession = $this->createMock(SessionInterface::class);
$mockSession->expects($this->once())->method('activateSession');
```

**Example — mock tab handler in tests:**

```php
$mockTabs = $this->createMock(TabHandlerInterface::class);
$mockTabs->method('get')->with('cart')->willReturn(['apple' => 3]);
$session->setTabHandler($mockTabs);
```

---

## Demos

| Demo | Description |
|---|---|
| [`demo/basic/`](demo/basic/) | Minimal login/logout — the simplest possible implementation |
| [`demo/mpa/`](demo/mpa/) | Multi-page app with URL authorization and `$allowedUrls` |
| [`demo/spa/`](demo/spa/) | Single-page app, SPA mode, AJAX login |
| [`demo/tabmanager/`](demo/tabmanager/) | SessionAdmin + TabManager integration — shared session, per-tab data isolation |

Each demo is self-contained with its own `composer.json`.

### Running a demo locally

1. Install dependencies for the chosen demo:

```bash
cd demo/basic
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

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

The package ships `Seba1rx\SessionAdmin\Contracts\SessionInterface` that you can type-hint against to decouple your code from the concrete class:

| Interface | Implemented by | Methods |
|---|---|---|
| `SessionInterface` | `SessionAdmin` | `activateSession()`, `createUserSession()`, `terminate()` |

**Example — mock in tests:**

```php
$mockSession = $this->createMock(SessionInterface::class);
$mockSession->expects($this->once())->method('activateSession');
```

---

## Demos

| Demo | Description |
|---|---|
| [`demo/basic/`](demo/basic/) | Minimal login/logout — the simplest possible implementation |
| [`demo/MPA/`](demo/MPA/) | Multi-page app with URL authorization and `$allowedUrls` |
| [`demo/SPA/`](demo/SPA/) | Single-page app, SPA mode, AJAX login |

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

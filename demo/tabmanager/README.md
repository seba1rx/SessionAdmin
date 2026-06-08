# SessionAdmin + TabManager — Integration Demo

Demonstrates `seba1rx/sessionadmin` and `seba1rx/tabmanager` working together in the same PHP session. The demo is based on the standalone TabManager demo and is intentionally nearly identical — the integration adds SessionAdmin's auth layer on top without changing TabManager's tab isolation behaviour.

---

## How to run

```bash
cd demo/tabmanager
composer install
php -S localhost:8000
```

Open `http://localhost:8000` and duplicate the tab a few times to see isolation in action.

---

## What to pay attention to

### 1. Session name is `tabmanager_demo` — not `PHPSESSID`

Open DevTools → Application → Cookies. You will see a cookie named **`tabmanager_demo`** instead of the PHP default `PHPSESSID`. This is SessionAdmin's session name (`$this->sessionName = 'tabmanager_demo'`), applied before `session_start()`. TabManager inherits it because `SessionAdminBridge` defers `session_start()` to `activateSession()`.

### 2. Auth state is shared — tab data is not

Log in on one tab, then duplicate that tab:
- The auth badge ("user") is shared across all tabs — it lives in `$_SESSION['sessionadmin']`.
- Click "Add random data" in Tab A and then in Tab B — each tab writes to its own slot. Tab A cannot read Tab B's data.

### 3. `$_SESSION` has two independent keys

Open the **Debug view** to see both slices side by side:

```
$_SESSION
├── sessionadmin/   → managed by SessionAdmin (auth, timing, security checks)
└── tabmanager/     → managed by TabManager (per-tab data, last_active, is_active)
```

Neither package touches the other's key.

### 4. `autoCleanupTabs = 30` fires silently on every request

`MySession` sets `$this->autoCleanupTabs = 30`. On every `activateSession()` call, tabs that have been inactive for more than 30 seconds are pruned. Watch it in the Debug view: close a tab, wait 30 seconds, refresh the Debug view — the entry is gone.

### 5. `isTabIndexed()` is false until the JS client fires

On a fresh page load, `$session->tabHandler->isTabIndexed()` returns `false` until the JS client POSTs to `session.php` (which calls `indexNewTab()`). This is the intended lifecycle — server-side code can use it to guard against requests before the tab is registered.

### 6. Every endpoint runs the same wiring block

All PHP endpoint files (index.php, session.php, addData.php, heartbeat.php, terminate.php) include the same three-line block:

```php
$session = new MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->activateSession();
```

This ensures the session name is consistent on every request, including AJAX and heartbeat calls.

### 7. Bootstrap endpoints and the `php -S` caveat

tabmanager's built-in endpoints (`/tabmanager/new-tab`, `/tabmanager/heartbeat`, etc.) are registered by `bin/bootstrap.php` and work automatically in any front-controller setup. With `php -S` those paths return 404 because there is no router. This demo works around it with custom endpoint files (`session.php`, `heartbeat.php`).

In a real app with a framework or front controller you do **not** need these files — the built-in endpoints handle registration and heartbeat automatically. However, if you do expose those endpoints, make sure they also call `$session->activateSession()` before anything else, so the session name matches the cookie.

---

## Configuration guide

### Step 1 — Install both packages

```bash
composer require seba1rx/sessionadmin seba1rx/tabmanager
```

### Step 2 — Create your SessionAdmin subclass

```php
// App/MySession.php
namespace App;

use Seba1rx\SessionAdmin\SessionAdmin;

class MySession extends SessionAdmin
{
    public function __construct()
    {
        $this->sessionName     = 'my_app';     // cookie name
        $this->sessionLifetime = 3600;          // seconds
        $this->autoCleanupTabs = 30;            // prune inactive tabs > 30 s (optional)
    }
}
```

### Step 3 — Wire the bridge in every entry point

On every page or endpoint that handles an HTTP request:

```php
require 'vendor/autoload.php';

use App\MySession;
use Seba1rx\TabManager\Bridge\SessionAdminBridge;

$session = new MySession();
$session->setTabHandler(new SessionAdminBridge()); // must come before activateSession()
$session->activateSession();
```

**Order matters.** `setTabHandler()` injects the bridge; `activateSession()` sets the session name and starts the session. The bridge defers `session_start()` to `activateSession()`, so the session name is always applied first.

### Step 4 — Use `$session->tabHandler` for tab-scoped data

```php
// Write data visible only to the current browser tab
$session->tabHandler->set('cart', $cartItems);

// Read it back (returns null if tab not registered or key absent)
$cart = $session->tabHandler->get('cart');

// Check if the JS client has registered this tab yet
if ($session->tabHandler->isTabIndexed()) {
    // tab is known — safe to read/write
}
```

### Step 5 — Include the tab header in every AJAX call

```js
await TabManagerClient.ready; // wait for UUID to be confirmed

const response = await fetch('/your-endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        ...TabManagerClient.getHeaders(), // adds X-TabManager-TabId: <uuid>
    },
    body: JSON.stringify(payload),
});
```

Without `getHeaders()`, the backend falls back to the shared cookie — all tabs write to the same slot and isolation breaks.

### Step 6 — Handle bootstrap endpoints (if using a custom session name)

tabmanager's built-in endpoints (`/tabmanager/new-tab`, `/tabmanager/heartbeat`, `/tabmanager/tab-close`) are auto-registered by `bin/bootstrap.php`. They create `new TabManager()` internally, which opens a session with PHP's default name (`PHPSESSID`) unless you configure it first.

To keep the session name consistent, set `session_name()` before the autoload in any script that serves those endpoints:

```php
session_name('my_app'); // must match $this->sessionName in MySession
require 'vendor/autoload.php';
```

Or route those paths through your front controller so `$session->activateSession()` runs first — which is what the demo's `heartbeat.php` and `session.php` files do.

---

## Files in this demo

| File | Role |
|---|---|
| `App/MySession.php` | SessionAdmin subclass — sets session name, lifetime, autoCleanupTabs |
| `bootstrap.php` | Demo-only — redirects session files to `sessions/` |
| `index.php` | Main page — wires both packages, renders auth + tab UI |
| `app.js` | JS logic — identical to tabmanager standalone demo |
| `session.php` | AJAX endpoint — tab registration + session fetch |
| `addData.php` | AJAX endpoint — writes random data to current tab's slot |
| `heartbeat.php` | AJAX endpoint — updates `last_active` for the current tab |
| `terminate.php` | Wipes the entire session (auth + all tab data) |
| `debug.php` | Debug view — shows both `sessionadmin` and `tabmanager` session slices |
| `WordStringGenerator.php` | Utility — generates random words for demo data |
| `seba1rx_tabmanagerclient.js` | Generated by `composer install` (tabmanager post-install script) |

`vendor/`, `sessions/`, and `seba1rx_tabmanagerclient.js` are git-ignored — they are generated locally.

# PHP session admin

A lightweight library that provides **session isolation per browser tab**, so each tab gets its own `$_SESSION` space and also **security against hijacking**.
Useful for apps where multiple tabs can interfere with each other’s session state.

## Installation

```bash
composer require seba1rx/sessionadmin
```

The SessionAdmin class has 3 public methods:
```bash
activateSession()
createUserSession()
terminate()
```


The SessionAdmin class is fully documented so you can check each method or property in order to get to understand it better.

### The SessionAdmin class is defined as an abstract class but has no abstract methods, it is intended to be extended by implementing a custom constructor.

In order to get an implementation idea go check the demos.

## There are 2 demos
* MPA (Multi Page Application)
* SPA (Single Page Application)

---
## 🚀 Features

    ✅ Creates a session for guest and users
    ✅ Named session
    ✅ 3% chances of regenerating session id on each request to prevent session fixation
    ✅ Prevents hijacking
    ✅ session destruction on obsolete request
    ✅ proxy-aware ip detection
    ✅ Optional in MPA: Define allowed URL array for guests, that can be expanded when user logs in according to system profile

**Per-tab PHP session isolation and management:**

    ✅ Unique session data per browser tab
    ✅ Automatic tab tracking via JavaScript
    ✅ Optional session cleanup on tab close
    ✅ Built-in debug interface (HTML + JSON)
    ✅ Works with Fetch/XHR/HTTP requests
    ✅ Plug-and-play bootstrap — no manual routes required


### On each demo you will find more info about each implementation
---

This will also publish a small JavaScript file automatically into your project root:

> /seba1rx_sessionadmin.js


⚙️ Setup

Include the JS helper in your HTML layout:
```html
<script src="/seba1rx_sessionadmin.js"></script>
<script>
    // Optional: enable automatic tab cleanup
    window.SESSIONADMIN_AUTO_DESTROY = true;
</script>
```

Then in PHP:

```PHP
require 'vendor/autoload.php';

use Seba1rx\SessionAdminServer\SessionAdminServer;

$session = new SessionAdminServer();

// Store data per tab
$session->set('cart', ['apple' => 3, 'banana' => 2]);

// Retrieve per-tab data
$cart = $session->get('cart');

// Debug info (optional)
print_r($session->debug());

```

⚙️ Constants

You can define the following constants in your bootstrap or entrypoint (optional):

| Constant                    | Type   | Default | Description                                                        |
| --------------------------- | ------ | ------- | ------------------------------------------------------------------ |
| `SESSIONADMIN_DEBUG`        | `bool` | `false` | Enables the `/sessionadmin/debug` endpoint                         |
| `SESSIONADMIN_DEBUG_UI`     | `bool` | `false` | Renders the debug endpoint as an interactive HTML table            |
| `SESSIONADMIN_AUTO_DESTROY` | `bool` | `false` | If `true`, tabs automatically mark their session inactive on close |


Example:

    define('SESSIONADMIN_DEBUG', true);
    define('SESSIONADMIN_DEBUG_UI', true);

🧩 Endpoints

These are automatically handled by the library through bootstrap.php:

| Method | Path                             | Purpose                                                    |
| ------ | -------------------------------- | ---------------------------------------------------------- |
| `POST` | `/sessionadmin/tab-close`        | Marks a tab’s session as inactive (triggered on tab close) |
| `GET`  | `/sessionadmin/debug`            | Returns session debug info (JSON or HTML)                  |
| `POST` | `/sessionadmin/debug/delete-tab` | Deletes a specific tab’s session data (from debug UI)      |


🔍 Debug Interface

If SESSIONADMIN_DEBUG_UI is true, visit:

👉 http://localhost/sessionadmin/debug

You’ll see a dashboard like this:

| Tab UUID   | Status   | Last Active      | Keys       | Size | Action     |
| ---------- | -------- | ---------------- | ---------- | ---- | ---------- |
| `7ac3d...` | ✅ Active | 2025-10-14 15:23 | cart, auth | 132  | 🗑️ Delete |


Each row corresponds to an isolated tab session.
You can delete individual tab data directly.

🧠 How It Works

1 On page load, JS generates a unique UUID per tab (stored in sessionStorage)

2 That ID is sent to PHP via a cookie SESSIONADMIN_TABID

3 The PHP class namespaces session data under that ID:

```PHP
$_SESSION['__sessionadmin_tabs'][<tab_uuid>]['_data']

```
4 When the tab is closed, /sessionadmin/tab-close marks it as inactive

5 Debug endpoint lets you visualize or clear inactive sessions

🧰 Methods Overview

| Method                                   | Description                                    |
| ---------------------------------------- | ---------------------------------------------- |
| `set(string $key, $value): void`         | Store data for the current tab                 |
| `get(string $key, $default = null)`      | Retrieve data for the current tab              |
| `destroyTabSession(string $tabId): void` | Delete a tab’s session data                    |
| `markInactiveTab(string $tabId): void`   | Mark a tab as inactive (used internally)       |
| `debug(): array`                         | Get structured debug info for all tab sessions |
| `getSessionKey(): string`                | Returns the internal session key used          |


🧩 Example Integration

```PHP
<?php
require 'vendor/autoload.php';

use Seba1rx\SessionAdminServer\SessionAdminServer;

// Enable debug tools locally
define('SESSIONADMIN_DEBUG', true);
define('SESSIONADMIN_DEBUG_UI', true);

$session = new SessionAdminServer();

// Set per-tab user data
$session->set('user', ['id' => 10, 'name' => 'Sebastian']);
echo 'Hello ' . $session->get('user')['name'];

```

Now each browser tab behaves independently — e.g., different users logged in simultaneously in different tabs.

🧹 Optional: Tab Cleanup on Close

Enable automatic cleanup by adding this before including the JS script:

```html
<script>
window.SESSIONADMIN_AUTO_DESTROY = true;
</script>

```

This triggers a POST /sessionadmin/tab-close when the user closes or reloads a tab.

🧪 Debug JSON Example

If you visit /sessionadmin/debug without SESSIONADMIN_DEBUG_UI, you’ll get:

```json
{
    "package": "seba1rx/sessionadmin",
    "version": "1.0.0",
    "session_key": "__sessionadmin_tabs",
    "tabs": {
        "1af2c3b4-3ff2-49ce-8aa9-d091b2e20d0c": {
            "active": true,
            "last_active": "2025-10-14 17:04:18",
            "keys": ["cart", "user"],
            "size": 253
        }
    }
}

```
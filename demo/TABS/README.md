# TABS Demo — SPA with Per-Tab Session Isolation

Demonstrates per-browser-tab session isolation in a single-page PHP application.
Each browser tab gets its own scoped storage under `$_SESSION['tabs'][{uuid}]`,
managed automatically by `TabManager` and the JS client.

---

## Pages / routes

| Method | URI | Handler | Description |
|---|---|---|---|
| `GET` | `/` | `Controller::start` | Renders the app shell |
| `POST` | `/hello` | `Controller::hello` | Returns a dialog greeting |
| `POST` | `/demoData` | `Controller::demoData` | Returns sample JSON in a dialog |
| `POST` | `/showLogin` | `Controller::showLogin` | Injects the login form into the page |
| `POST` | `/authenticate` | `Authentication::authenticate` | Runs login, calls `createUserSession()` |
| `POST` | `/addVarToSession` | `Controller::addVarToSession` | Stores a key/value in the current tab's session |
| `POST` | `/tabStatus` | `Controller::tabStatus` | Reports whether the current tab is indexed |
| `POST` | `/reloadSessionData` | `Controller::reloadSessionData` | Refreshes the session dump panel |
| `POST` | `/logout` | `Controller::logout` | Calls `terminate()`, reloads |
| `GET` | `/sessionadmin/debug` | bootstrap | HTML debug view of all tracked tabs |

---

## Setup and running locally

```bash
# 1. Install dependencies
cd demo/TABS
composer install

# 2. Start the built-in PHP server — the router script is required
#    so POST routes and /sessionadmin/* endpoints reach index.php
php -S localhost:8000 index.php

# 3. Open in browser
# http://localhost:8000

# 4. Open the tab debug view in a separate browser tab
# http://localhost:8000/sessionadmin/debug
```

Any email + password combination is accepted — authentication simulates a database
lookup without actually querying one.

---

## Configuration at a glance (`config/session.php`)

| Property | Value | Note |
|---|---|---|
| `sessionName` | `MyCustomTABSessionName` | Name of the session cookie |
| `sessionLifetime` | `500` s | ~8-minute idle timeout |
| `appIsSpa` | `true` | SPA mode — no URL authorization, no index redirect |
| `useTabIndexation` | `true` | TabManager enabled |
| `useAuthorization` | `false` | Irrelevant in SPA mode; explicit for clarity |
| `ipOctetsToCheck` | `2` | First two IP octets compared on each request |
| `proxyAwareIpDetection` | `true` | Reads IP from proxy headers |
| `keys` | `['some_key' => 'some_value', 'foo' => 'bar']` | Pre-seeded keys injected into `$_SESSION` on first request |

---

## Files worth reading

| File | What it shows |
|---|---|
| `App/MyTABSessionAdmin.php` | Minimal concrete subclass — constructor sets name, lifetime, and pre-seeded keys |
| `config/session.php` | Bootstraps the session; the single source of configuration |
| `App/Authentication.php` | Login endpoint — calls `createUserSession()`, stores user data in `$_SESSION['data']` |
| `App/Controller.php` | `addVarToSession()` uses `tabManager->set()` for tab-scoped storage; `tabStatus()` checks `isTabIndexed()` |
| `index.php` | Defines `SESSIONADMIN_DEBUG` / `SESSIONADMIN_DEBUG_UI` constants and includes `bin/bootstrap.php` |
| `assets/seba1rx_sessionAdmin.js` | JS client — generates tab UUID, sets cookie, calls `/sessionadmin/new-tab` beacon |

---

## What to look for

### Tab indexation flow

1. Page loads → JS client generates a UUID per tab (stored in `sessionStorage`)
2. UUID is synced to the `SESSIONADMIN_TABID` cookie
3. JS sends `sendBeacon('/sessionadmin/new-tab', { tab_id: '...' })` only when the tab is genuinely new (not on refresh)
4. Server creates the entry under `$_SESSION['tabs'][{uuid}]`

Open the same URL in two browser windows — the session dump will show two distinct
entries under `tabs`, each with its own `data` section. Refreshing does not create
duplicate entries.

### Tab-scoped storage

Log in, then click **Add var to this tab's session**. The key/value is written only to
the calling tab's namespace via `TabManager::set()`. Open a second window with the same
session — the second tab won't see the variable, demonstrating true per-tab isolation.

### Tab status check

Click **Check if this tab is indexed** before the JS beacon has fired (very first load,
before the beacon response completes) — you may see "not indexed". After the beacon
completes and you reload, it shows "indexed". This demonstrates `TabManager::isTabIndexed()`.

### Debug view

Visit `http://localhost:8000/sessionadmin/debug` while the app is running. The HTML table
shows all tracked tabs with their status (Active / Inactive), last-active timestamp, stored
keys, and data size. The **Delete** button calls `POST /sessionadmin/debug/delete-tab`.

`SESSIONADMIN_DEBUG_UI = true` is set in `index.php`. Without it the same endpoint
returns JSON only.

### Tab cleanup on close

`window.SESSIONADMIN_AUTO_DESTROY = true` is set in `tpl/main.php` before the JS client
loads. This registers a `beforeunload` handler that fires `/sessionadmin/tab-close` when
the tab closes, marking it Inactive in the debug view.

### Session security keys

The dump shows the keys written by the security layer on every request:

| Key | Purpose |
|---|---|
| `uniqueId` | 12-char hex token, stable for the full session lifetime |
| `ipPrefix` | First two octets of the client IP — compared on every request |
| `userAgent` | Browser User-Agent — checked alongside `ipPrefix` |
| `time_atRequest` | Unix timestamp of the last request |
| `time_sinceLastRequest` | Elapsed seconds since the previous request — compared against `sessionLifetime` |

### Pre-seeded custom keys

`config/session.php` passes `keys: ['some_key' => 'some_value', 'foo' => 'bar']`.
These appear at the root of `$_SESSION` alongside `sessionadmin` and `tabs` and are only
written when the key does not yet exist.

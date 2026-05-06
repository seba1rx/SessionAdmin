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
| `autoIndexTab` | `true` | Index tab from cookie on every request; no beacon needed |
| `autoCleanupTabs` | `30` s | Remove tabs inactive for > 30 s automatically on every request |
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
| `index.php` | Calls `session_name()` and defines debug constants **before** `vendor/autoload.php` so that `bin/bootstrap.php` (loaded via autoload.files) can start the session early for `/sessionadmin/*` requests |
| `App/Authentication.php` | Login endpoint — calls `createUserSession()`, stores user data in `$_SESSION['data']` |
| `App/Controller.php` | `addVarToSession()` uses `tabManager->set()` for tab-scoped storage; `tabStatus()` checks `isTabIndexed()` |
| `index.php` | Defines `SESSIONADMIN_DEBUG` / `SESSIONADMIN_DEBUG_UI` constants and includes `bin/bootstrap.php` |
| `assets/seba1rx_sessionAdmin.js` | JS client — generates tab UUID per navigation, sets cookie, sends beacon on new/duplicate tabs |

---

## What to look for

### Tab indexation flow

Two mechanisms work together to guarantee every tab is indexed:

**Server-side (immediate):** `autoIndexTab = true` in `config/session.php` tells the `TabManager` to index the current tab from the `SESSIONADMIN_TABID` cookie on every request. The tab entry exists before the JS beacon completes.

**JS-side (UUID lifecycle):**
1. Page loads → JS reads the `PerformanceNavigationTiming` type
2. On `'reload'`: restores the existing UUID from `sessionStorage` (same tab, same identity)
3. On any other type (`'navigate'`, `'back_forward'`): generates a fresh UUID — this covers both normal tab opens and the duplicate-tab case (browsers copy `sessionStorage` on duplicate, but navigation type is still `'navigate'`)
4. UUID is written to `sessionStorage` and synced to the `SESSIONADMIN_TABID` cookie
5. Beacon (`/sessionadmin/new-tab`) fires when the UUID is new

Open the same URL in two browser windows — or duplicate a tab — and the session dump will show two distinct entries under `tabs`. Refreshing does not create a new entry.

### Tab-scoped storage

Log in, then click **Add var to this tab's session**. The key/value is written only to
the calling tab's namespace via `TabManager::set()`. Open a second window with the same
session — the second tab won't see the variable, demonstrating true per-tab isolation.

### Tab status check

Click **Check if this tab is indexed** — because `autoIndexTab = true`, the tab is already
indexed by the time the page finishes rendering, even before the JS beacon completes.
This demonstrates `TabManager::isTabIndexed()`.

### Debug view

Visit `http://localhost:8000/sessionadmin/debug` while the app is running. The HTML table
shows all tracked tabs with their status (Active / Inactive), last-active timestamp, stored
keys, and data size. The **Delete** button calls `POST /sessionadmin/debug/delete-tab`.

`SESSIONADMIN_DEBUG_UI = true` is set in `index.php`. Without it the same endpoint
returns JSON only.

### Tab cleanup on close

`window.SESSIONADMIN_AUTO_DESTROY = true` is set in `tpl/main.php`. On `beforeunload`,
the JS sends a `/sessionadmin/tab-close` beacon that marks the tab Inactive and records
its close time. `autoCleanupTabs = 30` then removes it automatically: the next request
from any open tab triggers `cleanupInactiveTabs(30)`, and tabs inactive for more than 30
seconds are deleted. Watch the debug view — within 30 seconds of closing a tab, its row
disappears on the next page interaction.

Note: `beforeunload` also fires on page refresh. The touch step inside `autoIndexTab`
re-activates the tab (`is_active = true`) on the same request that runs cleanup, so
refreshing a tab never deletes its data.

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

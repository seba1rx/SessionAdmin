# SPA Demo — Single-Page Application

Demonstrates session management in a single-page PHP application where a single
`index.php` acts as the front controller and all navigation is handled client-side
via `fetch`. Tab indexation is disabled — this demo focuses on the session lifecycle
(login, logout, idle expiry) without per-tab isolation.

---

## Routes

| Method | URI | Handler | Description |
|---|---|---|---|
| `GET` | `/` | `Controller::start` | Renders the app shell |
| `POST` | `/hello` | `Controller::hello` | Returns a dialog greeting |
| `POST` | `/demoData` | `Controller::demoData` | Returns sample JSON in a dialog |
| `POST` | `/showLogin` | `Controller::showLogin` | Injects the login form into the page |
| `POST` | `/authenticate` | `Authentication::authenticate` | Runs login, calls `createUserSession()` |
| `POST` | `/addVarToSession` | `Controller::addVarToSession` | Stores a key/value at the session root (user only) |
| `POST` | `/logout` | `Controller::logout` | Calls `terminate()`, reloads |

---

## Setup and running locally

```bash
# 1. Install dependencies
cd demo/SPA
composer install

# 2. Start the built-in PHP server — the router script is required
#    so POST routes reach index.php instead of returning 404
php -S localhost:8000 index.php

# 3. Open in browser
# http://localhost:8000
```

Any email + password combination is accepted — authentication simulates a database
lookup without actually querying one.

---

## Configuration at a glance (`config/session.php`)

| Property | Value | Note |
|---|---|---|
| `sessionName` | `MyCustomSPASessionName` | Name of the session cookie |
| `sessionLifetime` | `120` s | 2-minute idle timeout — short so you can observe expiry quickly |
| `appIsSpa` | `true` | SPA mode — no URL authorization, no index redirect |
| `useTabIndexation` | `false` | TabManager disabled |
| `useAuthorization` | `false` | Irrelevant in SPA mode; explicit for clarity |
| `ipOctetsToCheck` | `2` | First two IP octets compared on each request |
| `proxyAwareIpDetection` | `true` | Reads IP from proxy headers |
| `keys` | `['some_key' => 'some_value', 'foo' => 'bar']` | Pre-seeded keys injected into `$_SESSION` on first request |

---

## Files worth reading

| File | What it shows |
|---|---|
| `App/MySPASessionAdmin.php` | Minimal concrete subclass — constructor sets name, lifetime, and pre-seeded keys |
| `config/session.php` | Bootstraps the session; the single source of configuration |
| `App/Authentication.php` | Login endpoint — calls `createUserSession()`, stores user data in `$_SESSION['data']` |
| `App/Controller.php` | `addVarToSession()` writes to `$_SESSION` root (no tab isolation) |
| `App/Router.php` | GET fallback serves the SPA root for any unmatched URL |

---

## What to look for

### Session lifecycle

The session lifetime is 120 seconds. Make a request, wait 2+ minutes without
interacting, then click any action — the session is destroyed and re-initialised
as a guest. Watch `time_sinceLastRequest` in the dump climb toward the limit.

### SPA mode — no URL authorization keys

Because `appIsSpa = true`, the session dump does not contain `allowedUrl` or
`urlIsAllowedToLoad`. These keys only appear in MPA sessions. The `appType` key
reads `"SPA"`.

### Session regeneration on login

`createUserSession()` regenerates the session ID to prevent session fixation.
Watch the `MyCustomSPASessionName` cookie value in DevTools → Application → Cookies
before and after submitting the login form — it changes.

### Pre-seeded custom keys

`config/session.php` passes `keys: ['some_key' => 'some_value', 'foo' => 'bar']`.
These appear at the root of `$_SESSION` alongside `sessionadmin` and are only
written when the key does not yet exist.

### Security keys

The dump shows the keys written by the security layer on every request:

| Key | Purpose |
|---|---|
| `uniqueId` | 12-char hex token, stable for the full session lifetime (guest and user) |
| `ipPrefix` | First two octets of the client IP — compared on every request to detect hijacking |
| `userAgent` | Browser User-Agent — checked alongside `ipPrefix` |
| `time_atRequest` | Unix timestamp of the last request |
| `time_sinceLastRequest` | Elapsed seconds since the previous request |

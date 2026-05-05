# MPA Demo — Multi-Page Application with URL Authorization

Demonstrates session management in a classic multi-page PHP application where
each page is a separate PHP file and navigation is enforced server-side.

---

## Pages

| File | Visibility | Description |
|---|---|---|
| `index.php` | Public | Landing page, shows full session dump |
| `page2.php` | Public | Contains the login form |
| `private.php` | Private | Accessible only after login |
| `exit.php` | — | Destroys the session and redirects to `index.php` |

---

## Setup and running locally

```bash
# 1. Install dependencies
cd demo/MPA
composer install

# 2. Start the built-in PHP server
php -S localhost:8000

# 3. Open in browser
# http://localhost:8000
```

Any email + password combination is accepted — the login simulates a database
lookup without actually querying one.

---

## Configuration at a glance (`AppFiles/required.php`)

| Property | Value | Note |
|---|---|---|
| `sessionName` | `MyCustomMPASessionName` | Name of the session cookie |
| `sessionLifetime` | `120` s | 2-minute idle timeout — short so you can observe expiry quickly |
| `allowedURLs` | `['index.php', 'page2.php']` | Guest-accessible pages |
| `useAuthorization` | `true` | URL enforcement active |
| `appIsSpa` | `false` | MPA mode |
| `useTabIndexation` | `true` | TabManager enabled — session has a `tabs` section |
| `ignoreInAuthorization` | `['authentication.php']` | Login endpoint bypasses the URL check |
| `keys` | `['some_key' => 'some_value', 'foo' => 'bar']` | Pre-seeded keys injected into `$_SESSION` on every request |

---

## Files worth reading

| File | What it shows |
|---|---|
| `AppFiles/MyMPASessionAdmin.php` | Minimal concrete subclass — constructor sets name, lifetime, and allowed URLs |
| `AppFiles/required.php` | Bootstraps the session on every page; the single source of configuration |
| `AppFiles/authentication.php` | Login endpoint — calls `createUserSession()`, stores user data, extends `allowedUrl` with profile-specific pages |
| `exit.php` | Calls `terminate()`, which destroys the session and redirects |

---

## What to look for

### URL authorization

`required.php` sets `useAuthorization = true` with guest pages `['index.php',
'page2.php']`. Try navigating directly to `private.php` while logged out — the
library redirects you to `index.php` automatically.

`authentication.php` is listed in `ignoreInAuthorization` so the login POST is
never blocked by the URL check.

### How `allowedUrl` grows on login

Open the session dump on any page and watch `sessionadmin.allowedUrl`. As a
guest it contains only the two public pages. After login, `authentication.php`
appends `private.php` so the authenticated user can reach the private page.
Logging out resets the list back to the guest config.

### MPA-only session keys

Because `appIsSpa = false`, the session contains keys that are absent in SPA
mode:

| Key | Guest value | User value | Meaning |
|---|---|---|---|
| `appType` | `"MPA"` | `"MPA"` | Confirms MPA mode |
| `allowedUrl` | `["index.php", "page2.php"]` | `[..., "private.php"]` | Pages the current role may visit |
| `urlIsAllowedToLoad` | `true` / `false` | `true` / `false` | Whether the current page passed the authorization check |

The page header turns **green** when `urlIsAllowedToLoad` is `true`. On
`index.php` and `page2.php` it turns grey when false; on `private.php` it turns
red.

### Tab indexation

`useTabIndexation = true` is set, so the JS client assigns a UUID to each
browser tab and the session has a `tabs` section. Open the same URL in two
different tabs and observe two separate entries under `tabs` in the dump.
Visit `http://localhost:8000/sessionadmin/debug` to see the tab manager's debug
view.

### Session security keys

The dump shows the keys written by the security layer on every request:

| Key | Purpose |
|---|---|
| `uniqueId` | 12-char hex token, stable for the full session lifetime (guest and user) |
| `ipPrefix` | First two octets of the client IP — compared on every request to detect hijacking |
| `userAgent` | Browser User-Agent — checked alongside `ipPrefix` |
| `time_atRequest` | Unix timestamp of the last request |
| `time_sinceLastRequest` | Elapsed seconds since the previous request — compared against `sessionLifetime` (120 s) to expire idle sessions |

### Session regeneration

`createUserSession()` regenerates the session ID on login to prevent session
fixation. Watch the `MyCustomMPASessionName` cookie value in DevTools →
Application → Cookies before and after submitting the login form — it changes.

### Pre-seeded custom keys

`required.php` passes `keys: ['some_key' => 'some_value', 'foo' => 'bar']`.
These appear at the root of `$_SESSION` alongside `sessionadmin` and `tabs`.
They demonstrate how to inject application-level defaults that are only written
when the key does not yet exist.

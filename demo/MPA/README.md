# MPA Demo — Multi-Page Application with URL Authorization

Demonstrates session management in a classic multi-page PHP application where
each page is a separate PHP file and navigation is enforced server-side.

---

## Pages

| File | Visibility | Description |
|---|---|---|
| `index.php` | Public | Landing page, shows session data |
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

## Files worth reading

| File | What it shows |
|---|---|
| `AppFiles/MyMPASessionAdmin.php` | Minimal concrete subclass — constructor sets name, lifetime, and allowed URLs |
| `AppFiles/required.php` | Bootstraps the session on every page; the single source of configuration |
| `AppFiles/authentication.php` | Login endpoint — calls `createUserSession()`, then extends `allowedUrl` with profile-specific pages |
| `exit.php` | Calls `terminate()`, which destroys the session and redirects |

---

## What to look for

### URL authorization

`required.php` passes `allowedURLs: ['index.php', 'page2.php']` and sets
`useAuthorization = true`. Try navigating to `private.php` while logged out —
the library redirects you back to `index.php` automatically.

`authentication.php` is in `ignoreInAuthorization` so the login POST itself
is never blocked.

### How `allowedUrl` grows on login

The session dump (visible on every page) shows `sessionadmin.allowedUrl`. As a
guest it contains only the two public pages. After login, `authentication.php`
appends `private.php` to that list so the user can reach the private page.
After logout the list resets to the guest config.

### `appType` and MPA-only session keys

Because `appIsSpa = false`, the session contains keys that are absent in SPA
mode:

| Key | Value | Meaning |
|---|---|---|
| `appType` | `"MPA"` | Confirms the app is running in MPA mode |
| `allowedUrl` | `["index.php", "page2.php"]` (guest) / `[..., "private.php"]` (user) | Pages the current role may visit |
| `urlIsAllowedToLoad` | `true` / `false` | Whether the current page passed the authorization check |

The page header turns green when `urlIsAllowedToLoad` is `true` and grey/red
otherwise.

### Session security keys

The dump also shows the keys written by the security layer on every request:

| Key | Purpose |
|---|---|
| `uniqueId` | 12-char hex token, stable for the full session lifetime (guest and user) |
| `ipPrefix` | First two octets of the client IP — checked on every request to detect hijacking |
| `userAgent` | Browser User-Agent — checked alongside `ipPrefix` |
| `time_atRequest` | Unix timestamp of the last request |
| `time_sinceLastRequest` | Elapsed seconds — compared against `sessionLifetime` to expire idle sessions |

### Session regeneration

`createUserSession()` regenerates the session ID on login to prevent session
fixation. You can observe the `SESSION` cookie value changing in the browser's
DevTools → Application → Cookies after submitting the login form.

# SPA Demo — AJAX-Friendly Session Management

This demo illustrates how `seba1rx/sessionadmin` integrates with Single-Page Applications (SPAs) or apps heavily reliant on AJAX/Fetch requests.

## Purpose
In an SPA environment, server-side redirects (302) are often problematic for background requests. This demo shows the "Silent" mode of the library:
- **No Redirects**: The library provides metadata in the session or via response codes rather than forcing the browser to change URLs.
- **JSON Responses**: Demonstrates handling authentication transitions without page reloads.
- **Standard Mode**: Showcases the library's default state (`appIsSpa = true`).

## Implementation Highlights

### 1. `App/MySession.php` (or equivalent config)
Uses the default `$appIsSpa = true`. This disables the URL authorization redirect logic, making it safe for API-driven frontends.

### 2. Login Logic
Observe how the authentication script returns a JSON response. The session is hardened and the ID is regenerated just like in the MPA version, but the frontend maintains control over the UI state.

## What to Watch For

1.  **Metadata Over Redirection**: Inspect the `$_SESSION['sessionadmin']` dump. Notice the `appType` is set to `SPA`.
2.  **Termination Behavior**: When `$session->terminate()` is called, the session is cleared and a new guest session starts, but no header-based redirect is sent to the browser.

## Running the Demo

1.  Install dependencies:
    ```bash
    composer install
    ```
2.  Start the PHP built-in server:
    ```bash
    php -S localhost:8000
    ```
3.  Navigate to:
    `http://localhost:8000`
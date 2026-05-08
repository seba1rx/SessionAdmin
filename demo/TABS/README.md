# TABS Demo — Per-Tab Session Isolation

This is the most advanced demo of the `seba1rx/sessionadmin` library. It showcases the **TabManager**, which allows you to store data that is unique to a specific browser tab, preventing "leaked" state when a user has multiple tabs open (e.g., two different shopping carts or two different search results).

## Purpose
Standard PHP sessions are shared across the entire browser. This demo shows how to:
- **Isolate Data**: Store variables that only exist within the context of the current tab.
- **Automate Tracking**: Use the JS client to manage Tab UUIDs via `sessionStorage` and cookies.
- **Cleanup**: Automatically prune session data from tabs that have been closed.

## Implementation Highlights

### 1. `config/session.php`
This is where the magic happens.
- `$useTabIndexation = true`: Activates the `TabManager`.
- `$autoIndexTab = true`: Ensures the tab is identified immediately from the cookie without waiting for a JS beacon.
- `$autoCleanupTabs = 30`: Automatically removes data for tabs that have been inactive for more than 30 seconds.

### 2. `App/Controller.php` (Tab CRUD)
Look for usage of `$session->tabManager->set()` and `get()`. This data is stored under `$_SESSION['tabs'][TAB_ID]`, keeping the root `$_SESSION` clean and the tab data isolated.

### 3. Frontend Integration
Check the HTML source to see the inclusion of `/seba1rx_sessionAdmin.js`. This script generates the Tab UUID and notifies the server when a tab is closed.

## What to Watch For

1.  **The Multi-Tab Test**: Open the demo in one tab and add some "Tab-specific data". Now open the demo in a **new** tab. Notice that the second tab has its own empty state, while the first tab retains its data.
2.  **The JSON Dump**: Look at the `tabs` key in the session JSON. You will see multiple UUID entries, each with its own `data` and `last_active` timestamp.
3.  **Auto-Cleanup**: Close one tab and wait 30 seconds. Refresh the remaining tab; you will see the closed tab's entry disappear from the session dump.

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
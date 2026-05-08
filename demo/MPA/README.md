# MPA Demo — URL Authorization & Access Control

This demo showcases the Multi-Page Application (MPA) capabilities of `seba1rx/sessionadmin`, specifically focusing on **URL Authorization**. It demonstrates how to restrict guest access to specific entry points and dynamically expand permissions upon authentication.

## Purpose
The goal of this demo is to show how the library can act as a lightweight gatekeeper for traditional PHP applications where users navigate between physical `.php` files.
- **URL Enforcement**: Automatic redirection to `index.php` if a guest tries to access a restricted page.
- **Dynamic Permissions**: Adding restricted pages to the "allowed list" only after a successful login.
- **Profile-based Access**: Demonstrating how different user roles can have different sets of accessible URLs.

## Implementation Highlights

### 1. `AppFiles/MyMPASessionAdmin.php`
Extends `SessionAdmin` to handle the MPA-specific configuration. Notice the `$allowedUrls` property which defines the "Public" area of the site.

### 2. `AppFiles/required.php`
This is the bootstrap file included in every page. Key settings to observe:
- `$sessionAdmin->appIsSpa = false`: Tells the library to handle redirects automatically.
- `$sessionAdmin->useAuthorization = true`: Enables the URL checking logic.
- `$sessionAdmin->ignoreInAuthorization`: A list of files (like the auth processor) that should never trigger a redirect loop.

### 3. `AppFiles/authentication.php`
This script processes the login. Note how it calls `$sessionAdmin->createUserSession($id)` and then appends `private.php` to the `$_SESSION['sessionadmin']['allowedUrl']` array. This is how the library knows the user is now allowed to see restricted content.

## What to Watch For

1.  **Unauthorized Access**: Try navigating directly to `private.php` without logging in. You will be redirected back to `index.php`.
2.  **Stateful Authorization**: After logging in, check the `$_SESSION` dump. Notice how the `allowedUrl` list has grown to include the private page.
3.  **The `urlIsAllowedToLoad` Flag**: Observe this boolean in the session metadata; it is recalculated on every request based on the current URI.

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
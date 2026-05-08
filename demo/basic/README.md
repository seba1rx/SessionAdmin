# Basic Demo — Minimal Session Management

This demo provides the simplest possible implementation of the `seba1rx/sessionadmin` library. It focuses on the core lifecycle of a secure session: initialization, authentication, and termination, without the added complexity of per-tab isolation or URL authorization.

## Purpose
The goal of this demo is to show how to replace native PHP session handling with a hardened alternative using minimal code. It demonstrates:
- **Session Hardening**: Automatic protection against hijacking via IP and User-Agent fingerprinting.
- **Lifecycle Management**: Easy transitions between Guest and Authenticated states.
- **Security Best Practices**: Automatic session ID regeneration on login and random intervals to mitigate fixation and side-channel attacks.

## Implementation Highlights

When reviewing this demo, pay close attention to the following files:

### 1. `App/MySession.php`
This is where you define your session's "personality" by extending `SessionAdmin`.
- **Configuration**: The constructor sets the `sessionName` (the cookie name) and `sessionLifetime`. 
- **Pre-seeded Keys**: Notice the `$keys` array. These values are automatically injected into `$_SESSION` when the session starts if they don't already exist.
- **Simplicity**: `useTabIndexation` is set to `false`, meaning data stays at the root of `$_SESSION` rather than being scoped to specific browser tabs.

### 2. `index.php`
The application entry point.
- **Bootstrap**: The call to `$session->activateSession()` replaces the traditional `session_start()`. It handles all the security verification logic internally.
- **Authentication**: Look at how `createUserSession(42)` is called. This marks the session as authenticated and triggers an immediate **session ID regeneration** for security.
- **Session Transparency**: The page renders the full `$_SESSION` array. This allows you to inspect the `sessionadmin` metadata (like `ipPrefix`, `userAgent`, and `uniqueId`) that the library manages for you.

## What to Watch For

While running the demo, observe these behaviors:

1.  **Session Regeneration**: Check your browser's cookies (DevTools -> Application -> Cookies). The value of `basic_demo` changes immediately after you click "Log in".
2.  **Security Metadata**: Notice the `sessionadmin` key in the JSON dump. Even as a guest, the library tracks a `uniqueId` and request timestamps.
3.  **Idle Timeout**: The `sessionLifetime` is set to 300 seconds (5 minutes). If you wait or manually manipulate the `time_atRequest` value, the library will detect the expiry and destroy the session on the next load.
4.  **Hijacking Protection**: The `ipPrefix` and `userAgent` are verified on every request. If either changes during an active session, the library treats it as a hijacking attempt and destroys the session.

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

> **Note**: This demo uses a "fake" authentication logic in `index.php` that accepts any email/password combination to focus purely on the session management aspect.
<?php

declare(strict_types=1);

namespace Seba1rx\SessionAdmin\Contracts;

/**
 * Public API contract for the SessionAdmin layer.
 *
 * Type-hint against this interface when you need to swap or mock the session
 * implementation — for example in application service classes, middleware, or tests.
 *
 * Implementations must guarantee the following invariants:
 *  - activateSession() is safe to call on every page request before any output.
 *  - createUserSession() regenerates the session ID to prevent session fixation.
 *  - terminate() leaves the application in a clean guest state.
 */
interface SessionInterface
{
    /**
     * Starts or resumes the session and runs all security checks.
     *
     * Configures the session cookie, calls session_start(), enforces the
     * configured lifetime and hijacking detection, and optionally validates
     * URL authorization (MPA mode). Must be called on every page entry point
     * before any output is sent to the browser.
     *
     * @return void
     */
    public function activateSession(): void;

    /**
     * Marks the current session as authenticated and stores the user identifier.
     *
     * Regenerates the session ID to prevent session fixation attacks, then
     * refreshes the cookie lifetime and timestamps.
     *
     * @param mixed $id_user Any scalar identifier (int, string, UUID, etc.)
     * @return void
     */
    public function createUserSession(mixed $id_user): void;

    /**
     * Destroys the current session, reinitialises as a guest, and redirects
     * to index.php in MPA mode when $terminateRedirects is true.
     *
     * @return void
     */
    public function terminate(): void;
}

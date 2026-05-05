<?php

namespace Seba1rx\SessionAdmin\Tests;

use Seba1rx\SessionAdmin\SessionAdmin;

/**
 * Concrete test double for SessionAdmin.
 *
 * Extends SessionAdmin so all protected methods from both Session and SessionAdmin
 * are accessible. Overrides cookie/header/redirect I/O so tests run without
 * emitting HTTP headers or calling exit().
 *
 * Each public call* method exposes one protected method for direct unit testing.
 */
abstract class SessionTestable extends SessionAdmin
{
    /** Cookies intercepted by the setCookie override. */
    public array $cookies = [];

    /** True whenever redirectToIndex() is called. */
    public bool $redirectCalled = false;

    /** Intercepts setcookie() so tests never emit headers. */
    protected function setCookie(
        string $name,
        string $value = "",
        int|string|array $expiresOrOptions = 0,
        string $path = "",
        string $domain = "",
        bool $secure = false,
        bool $httponly = false
    ): bool {
        $this->cookies[$name] = compact(
            'name', 'value', 'expiresOrOptions', 'path', 'domain', 'secure', 'httponly'
        );
        return true;
    }

    /** Reads from the in-memory cookie store instead of $_COOKIE. */
    protected function getCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name]['value'] ?? $default;
    }

    /** Prevents exit() from killing the test process on redirects. */
    protected function redirectToIndex(): void
    {
        $this->redirectCalled = true;
    }

    // ── Exposed Session internals ────────────────────────────────────────────

    public function callCurrentStateIsUser(): bool
    {
        return $this->currentStateIsUser();
    }

    public function callSetTabManager(): void
    {
        $this->setTabManager();
    }

    public function callConfigureGuestSession(): void
    {
        $this->configureGuestSession();
    }

    public function callPreparesDomainAndSecureVars(): void
    {
        $this->preparesDomainAndSecureVars();
    }

    public function callSetSessionTime(): void
    {
        $this->setSessionTime();
    }

    public function callSetSessionTimeStamps(): void
    {
        $this->setSessionTimeStamps();
    }

    public function callRequestIsObsolete(): bool
    {
        return $this->requestIsObsolete();
    }

    public function callCheckTime(): void
    {
        $this->checkTime();
    }

    public function callDestroySession(): void
    {
        $this->destroySession();
    }

    public function callCheckIfUrlIsAllowed(): void
    {
        $this->checkIfUrlIsAllowed();
    }

    public function callRequestIsHijackingAttempt(): bool
    {
        return $this->requestIsHijackingAttempt();
    }

    public function callGetIpAddressProxyAware(): string
    {
        return $this->getIpAddressProxyAware();
    }

    public function callGetIpAddressNoProxies(): string
    {
        return $this->getIpAddressNoProxies();
    }

    public function callGetIpPrefix(string $ip, int $parts = 2): string
    {
        return $this->getIpPrefix($ip, $parts);
    }

    public function callGetUserAgent(): string
    {
        return $this->getUserAgent();
    }

    public function callRegenerateSession(): void
    {
        $this->regenerateSession();
    }

    public function callShouldRandomlyRegenerate(): bool
    {
        return $this->shouldRandomlyRegenerate();
    }

    public function callGetSubStrAfterLast(string $haystack, string $needle): string
    {
        return $this->getSubStrAfterLast($haystack, $needle);
    }

    public function callRedirectToIndex(): void
    {
        $this->redirectToIndex();
    }
}

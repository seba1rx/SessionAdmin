<?php

namespace Seba1rx\SessionAdmin\Tests;

use Seba1rx\SessionAdmin\Session;

abstract class SessionTestable extends Session{

    /**
     * Exposes Session->currentStateIsUser() for testing puroposes
     * * should return true if the user is logged in
     *
     * @return bool
     */
    public function callCurrentStateIsUser(): bool
    {
        return $this->currentStateIsUser();
    }

    /**
     * Exposes Session->setTabManager() for testing purposes
     * * should set the instance of TabManager class
     *
     * @return void
     */
    public function callSetTabManager(): void
    {
        $this->setTabManager();
    }

    /**
     * Exposes Session->configureGuestSession() for testing purposes
     * * should set some values with spesific keys in the $_SESSION array
     *
     * @return void
     */
    public function callConfigureGuestSession(): void
    {
        $this->configureGuestSession();
    }

    /**
     * Exposes Session->preparesDomainAndSecureVars() for testing purposes
     * * should set Session class properties: domain and secure
     *
     * @return void
     */
    private function callPreparesDomainAndSecureVars(): void
    {
        $this->preparesDomainAndSecureVars();
    }

    /**
     * Exposes Session->setSessionTime() for testing purposes
     * * should set cookie <session name>
     * * should call preparesDomainAndSecureVars()
     * * should set session life time
    *
    * @return void
    */
    public function callSetSessionTime(): void
    {
        $this->setSessionTime();
    }

    /**
     * Exposes Session->setSessionTimeStamps() for testing purposes
     * * should set $_SESSION keys time_atRequest, time_sinceLastRequest
    *
    * @return void
    */
    protected function callSetSessionTimeStamps(): void
    {
        $this->setSessionTimeStamps();
    }

    /**
     * Exposes Session->requestIsObsolete() for testing purposes
     * * should return true if time_sinceLastRequest is higher than sessionLifetime
    *
    * @return bool true if request is obsolete
    */
    public function callRequestIsObsolete(): bool
    {
        return $this->requestIsObsolete();
    }

    /**
     * Exposes Session->checkTime() for testing purposes
     * * should call destroySession if obsolete
     * * should reset session if hijacking attempt
    *
    * @return void
    */
    public function callCheckTime(): void
    {
        $this->checkTime();
    }

    /**
     * Exposes Session->destroySession() for testing purposes
     * * should destroy session and reactivate as guest
    *
    * @return void
    */
    private function callDestroySession(): void
    {
        $this->destroySession();
    }

    /**
     * Exposes Session->checkIfUrlIsAllowed() for testing purposes
     * * should set $_SESSION['urlIsAllowedToLoad'] as false if ur not allowed
     * * should redirect to index if not
    *
    * @return void
    */
    private function callCheckIfUrlIsAllowed(): void
    {
        $this->checkIfUrlIsAllowed();
    }

    /**
     * Exposes Session->requestIsHijackingAttempt() for testing purposes
     * * should return true if request is hijacking attempt
    *
    * @return bool true if hijicking detected, false for a valid request
    */
    public function callRequestIsHijackingAttempt(): bool
    {
        return $this->requestIsHijackingAttempt();
    }

    /**
     * Exposes Session->getIpAddress_proxyAware() for testing purposes
     * * should get the IP considering proxy
    *
     *  @return string
    */
    public function callGetIpAddress_proxyAware(): string
    {
        return $this->getIpAddress_proxyAware();
    }

    /**
     * Exposes Session->getIpAddress_noProxys() for testing purposes
     * should get the IP without considering proxy
    *
    * @return string
    */
    public function callGetIpAddress_noProxys(): string
    {
        return $this->getIpAddress_noProxys();
    }

    /**
     * Exposes Session->getIpPrefix() for testing purposes
     * should get the first 2, 3 or 4 octates from the ip accordind to ipOctetsToCheck
    *
    * @param string $ip
    * @param integer $parts
    * @return string
    */
    private function callGetIpPrefix(string $ip, int $parts = 2): string
    {
        return $this->getIpPrefix($ip, $parts);
    }

    /**
     * Exposes Session->getUserAgent() for testing purposes
     * * should get the user agent
    *
     * @return string
     */
    public function callGetUserAgent(): string
    {
        return $this->getUserAgent();
    }

    /**
     * Exposes Session->regenerateSession() for testing purposes
     * * should regen session by keeping the session id
    *
     * @return void
    */
    public function callRegenerateSession(): void
    {
        $this->regenerateSession();
    }

    /**
     * Exposes Session->shouldRandomlyRegenerate() for testing purposes
     * * should return true 3% of the times it gets called
    *
    * @return bool
    */
    public function callShouldRandomlyRegenerate(): bool
    {
        return $this->shouldRandomlyRegenerate();
    }

    /**
     * Exposes Session->getSubStrAfterLast() for testing purposes
     * * should get the string after the last ocurrence of the needle
    *
    * @param string $haystack
    * @param string $needle
    * @return string
    */
    public function callGetSubStrAfterLast (string $haystack, string $needle): string
    {
        return $this->getSubStrAfterLast($haystack, $needle);
    }

    /**
     * Exposes Session->strrevpos() for testing purposes
     * * should get the index of the last occurrance of a needl in a haystack
    *
    * @param string $haystack
    * @param string $needle
    * @return mixed
    */
    public function callStrrevpos(string $haystack, string $needle): mixed
    {
        return $this->strrevpos($haystack, $needle);
    }

    /**
     * Exposes Session->redirectToIndex() for testing purposes
     * * should redirect to index
    *
     * @return void
    */
    public function callRedirectToIndex(): void
    {
        $this->redirectToIndex();
    }

    /**
     * Exposes Session->setIsRunningTests() for testing purposes
     * * should set property isRunningTests
     *
     * @codeCoverageIgnore
     */
    public function callSetIsRunningTests(bool $isRunningTest = true): void
    {
        $this->setIsRunningTests($isRunningTest);
    }
}


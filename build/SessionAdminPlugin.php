<?php

namespace Seba1rx\SessionAdmin\Build;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin stub.
 *
 * Asset publishing was removed when tab management moved to its own package.
 * The plugin entry point is kept so that consumers who already have
 * `"extra": {"class": "Seba1rx\\SessionAdmin\\Build\\SessionAdminPlugin"}`
 * in their composer.json do not break on update.
 */
class SessionAdminPlugin implements PluginInterface, EventSubscriberInterface
{
    /** @inheritdoc */
    public function activate(Composer $composer, IOInterface $io): void {}

    /** @inheritdoc */
    public function deactivate(Composer $composer, IOInterface $io): void {}

    /** @inheritdoc */
    public function uninstall(Composer $composer, IOInterface $io): void {}

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

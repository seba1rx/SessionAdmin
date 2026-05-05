<?php

namespace Seba1rx\SessionAdmin\Build;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class SessionAdminPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    private const ASSETS = [
        'assets/seba1rx_sessionAdmin.js' => 'seba1rx_sessionAdmin.js',
    ];

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostCmd',
            ScriptEvents::POST_UPDATE_CMD  => 'onPostCmd',
        ];
    }

    public function onPostCmd(Event $event): void
    {
        self::copyAssets($event->getComposer(), $event->getIO());
    }

    /**
     * Entry point for `composer run publish-assets`.
     * Also usable in the root package's `post-install-cmd` / `post-update-cmd` scripts.
     */
    public static function publishAssets(Event $event): void
    {
        self::copyAssets($event->getComposer(), $event->getIO());
    }

    private static function copyAssets(Composer $composer, IOInterface $io): void
    {
        $vendorDir   = $composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir);
        $packageDir  = dirname(__DIR__); // build/../ == package root

        foreach (self::ASSETS as $sourceRel => $targetRel) {
            $source = $packageDir . '/' . $sourceRel;
            $target = $projectRoot . '/' . $targetRel;

            if (!file_exists($source)) {
                $io->writeError("<error>[SessionAdmin] Source not found: {$sourceRel}</error>");
                continue;
            }

            if (!@copy($source, $target)) {
                $io->writeError("<error>[SessionAdmin] Failed to publish: {$targetRel}</error>");
                continue;
            }

            $io->write("<info>[SessionAdmin] Published: {$targetRel}</info>");
        }
    }
}

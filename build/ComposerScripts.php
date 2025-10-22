<?php

namespace Seba1rx\SessionAdmin\Build;

use Composer\Script\Event;

class ComposerScripts
{
    public static function publishAssets(Event $event)
    {
        $io = $event->getIO();
        $composer = $event->getComposer();
        $extra = $composer->getPackage()->getExtra();

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir); // root of the installing project

        if (empty($extra['publish']) || !is_array($extra['publish'])) {
            $io->write("<comment>No publishable assets defined.</comment>");
            return;
        }

        foreach ($extra['publish'] as $sourceRel => $targetRel) {
            $source = __DIR__ . '/../' . $sourceRel;
            $target = $projectRoot . '/' . $targetRel;

            if (!file_exists($source)) {
                $io->writeError("<error>Source not found: $source</error>");
                continue;
            }

            if (!@copy($source, $target)) {
                $io->writeError("<error>Failed to copy $source → $target</error>");
                continue;
            }

            $io->write("<info>Published: $targetRel</info>");
        }
    }
}

<?php
namespace TYPO3\CMS\Composer\Installer;

/*
 * This file is part of the TYPO3 project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Composer\Cache;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use TYPO3\CMS\Composer\Plugin\Config;
use TYPO3\CMS\Composer\Plugin\Core\AutoloadConnector;
use TYPO3\CMS\Composer\Plugin\Util\Filesystem;

/**
 * The plugin that registers the installers (registered by extra key in composer.json)
 *
 * @author Christian Opitz <christian.opitz at netresearch.de>
 * @author Thomas Maroschik <tmaroschik@dfau.de>
 * @author Helmut Hummel <info@helhum.io>
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoload'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $cache = null;
        if ($composer->getConfig()->get('cache-files-ttl') > 0) {
            $cache = new Cache($io, $composer->getConfig()->get('cache-files-dir'), 'a-z0-9_./');
        }

        $isDevMode = !in_array('--no-dev', $_SERVER['argv']);

        $extra = $composer->getPackage()->getExtra();
        if ($isDevMode && isset($extra['typo3/cms-dev'])) {
            $extra['typo3/cms'] = $extra['typo3/cms-dev'];
            unset($extra['typo3/cms-dev']);
            $composer->getPackage()->setExtra($extra);
        }

        $filesystem = new Filesystem();
        $binaryInstaller = new BinaryInstaller($io, rtrim($composer->getConfig()->get('bin-dir'), '/'), $composer->getConfig()->get('bin-compat'), $filesystem);
        $pluginConfig = Config::load($composer);
        $composer
            ->getInstallationManager()
            ->addInstaller(
                new CoreInstaller($io, $composer, $filesystem, $pluginConfig, $binaryInstaller)
            );
        $composer
            ->getInstallationManager()
            ->addInstaller(
                new ExtensionInstaller($io, $composer, $filesystem, $pluginConfig, $binaryInstaller)
            );
        $composer
            ->getDownloadManager()
            ->setDownloader(
                't3x',
                new Downloader\T3xDownloader($io, $composer->getConfig(), null, $cache)
            );
    }

    /**
     * @param Event $event
     */
    public function postAutoload(Event $event)
    {
        $autoloadConnector = new AutoloadConnector();
        $autoloadConnector->linkAutoloader($event);
    }
}

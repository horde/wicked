<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package Wicked
 */

/* Determine the base directories. */
if (!defined('WICKED_BASE')) {
    define('WICKED_BASE', realpath(__DIR__ . '/..'));
}

if (!defined('HORDE_BASE')) {
    /* If Horde does not live directly under the app directory, the HORDE_BASE
     * constant should be defined in config/horde.local.php. */
    if (file_exists(WICKED_BASE . '/config/horde.local.php')) {
        include WICKED_BASE . '/config/horde.local.php';
    } else {
        define('HORDE_BASE', realpath(WICKED_BASE . '/..'));
    }
}

use Horde\Cache\Cache as HordeCache;
use Horde\Cache\FileStorage;
use Horde\Core\Uri\RoutesProvider;
use Horde\Util\Variables;
use Horde\Wicked\HordeWikilinkUrlResolver;
use Horde\Wicked\Service\UrlGenerator;
use Horde\Wicked\WickedEngine;
use Horde\Wicked\WikilinkUrlResolver;
use Horde\Util\Util;

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
require_once HORDE_BASE . '/lib/core.php';

/**
 * Wicked application API.
 *
 * This file defines Horde's core API interface. Other core Horde libraries
 * can interact with Wicked through this API.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package Wicked
 */
class Wicked_Application extends Horde_Registry_Application
{
    /**
     */
    public $version = '3.3.1-RC1';

    protected function _bootstrap()
    {
        $GLOBALS['injector']->bindFactory('Wicked_Driver', 'Wicked_Factory_Driver', 'create');

        $GLOBALS['injector']->bindImplementation(
            WikilinkUrlResolver::class,
            HordeWikilinkUrlResolver::class,
        );

        $GLOBALS['injector']->bindClosure(
            WickedEngine::class,
            function ($injector) {
                $format = $GLOBALS['conf']['wicked']['format'] ?? 'yawiki';
                $blockFactory = $injector->has('Horde_Core_Factory_BlockCollection')
                    ? $injector->get('Horde_Core_Factory_BlockCollection')
                    : null;

                $cacheDir = $GLOBALS['conf']['cache']['params']['dir'] ?? '';
                $cacheLifetime = (int) ($GLOBALS['conf']['wicked']['cache']['lifetime'] ?? 86400);
                $cache = new HordeCache(
                    new FileStorage(dir: $cacheDir),
                    [
                        'namespace' => 'wicked_render',
                        'lifetime' => $cacheLifetime,
                    ],
                );

                return new WickedEngine(
                    storageDriver: $injector->get('Wicked_Driver'),
                    registry: $injector->get('Horde_Registry'),
                    urlResolver: $injector->get(WikilinkUrlResolver::class),
                    format: $format,
                    blockFactory: $blockFactory,
                    cache: $cache,
                );
            },
        );

        $GLOBALS['injector']->bindClosure(
            UrlGenerator::class,
            function ($injector) {
                $provider = $injector->getInstance(RoutesProvider::class);
                $registry = $injector->getInstance('Horde_Registry');
                $webroot = $registry->get('webroot', 'wicked');
                $runtimeProvider = $injector->getInstance(Horde\Core\RuntimeRoutesProvider::class);

                return new UrlGenerator($provider, $webroot, $runtimeProvider->environ);
            },
        );
    }

    /**
     * Global variables defined:
     * - $wicked:   The Wicked_Driver object.
     */
    protected function _init()
    {
        $GLOBALS['wicked'] = $GLOBALS['injector']->getInstance('Wicked_Driver');
    }

    /**
     */
    public function menu($menu)
    {
        global $conf, $page;

        if (!empty($conf['menu']['pages'])) {
            $pages = [
                'Wiki/Home' => _("_Home"),
                'Wiki/Usage' => _("_Usage"),
                'RecentChanges' => _("_Recent Changes"),
                'AllPages' => _("_All Pages"),
                'MostPopular' => _("Most Popular"),
                'LeastPopular' => _("Least Popular"),
                'Search' => _("_Search"),
            ];
            foreach ($conf['menu']['pages'] as $pagename) {
                /* Determine who we should say referred us. */
                $curpage = isset($page) ? $page->pageName() : null;
                $referrer = Util::getFormData('referrer', $curpage);

                /* Determine if we should depress the button. We have to do
                 * this on our own because all the buttons go to the same .php
                 * file, just with different args. */
                if (!strstr($_SERVER['PHP_SELF'], 'prefs.php')
                    && $curpage === $pagename) {
                    $cellclass = 'current';
                } else {
                    $cellclass = '__noselection';
                }

                $url = Wicked::url($pagename)->add('referrer', $referrer);
                $menu->add($url, $pages[$pagename], 'wicked-' . str_replace('/', '', $pagename), null, null, null, $cellclass);
            }
        }
    }

    public function sidebar($sidebar)
    {
        global $registry;

        if ($registry->isAdmin()
            || $registry->isAdmin(['permission' => 'wicked:admin'])
            || $registry->isAdmin(['permission' => 'wicked:admin:attachments'])
        ) {
            $sidebar->containers['admin'] = [
                'header' => [
                    'id' => 'wicked-toggle-admin',
                    'label' => _("Administration"),
                ],
            ];
            $sidebar->addRow([
                'label' => _("Attachments"),
                'url' => new Horde_Url($registry->get('webroot', 'wicked') . '/admin/attachments'),
                'cssClass' => 'horde-admin',
            ], 'admin');
        }
    }

    /**
     * Returns values for <configspecial> configuration settings.
     *
     * @param string $what  The configuration setting to return.
     *
     * @return array  The values for the requested configuration setting.
     */
    public function configSpecialValues($what)
    {
        if ($what === 'wiki-formats') {
            $catalog = Horde\Text\Wiki\SimpleFormatCatalog::withDefaults();
            $formats = [];
            foreach ($catalog->getParserFormats() as $format) {
                $formats[$format] = ucfirst($format);
            }
            // Legacy compat: existing configs may have 'Default'
            $formats['default'] = 'Default (Yawiki)';
            return $formats;
        }
        return [];
    }

    /**
     */
    public function perms()
    {
        $perms = [
            'admin' => [
                'title' => _("Administration"),
            ],
            'admin:attachments' => [
                'title' => _("Attachment Management"),
            ],
            'pages' => [
                'title' => _("Pages"),
            ],
        ];

        foreach (['AllPages', 'LeastPopular', 'MostPopular', 'RecentChanges'] as $val) {
            $perms['pages:' . $val] = [
                'title' => $val,
            ];
        }

        try {
            $pages = $GLOBALS['wicked']->getPages();
            sort($pages);
            foreach ($pages as $pagename) {
                $perms['pages:' . $GLOBALS['wicked']->getPageId($pagename)] = [
                    'title' => $pagename,
                ];
            }
        } catch (Wicked_Exception $e) {
        }

        return $perms;
    }

    /* Download data. */

    /**
     */
    public function download(Variables|Horde_Variables $vars)
    {
        global $wicked;

        $pageName = $vars->get('page', 'Wiki/Home');
        $page = Wicked_Page::getPage($pageName);
        if (!$page->allows(Wicked::MODE_DISPLAY)) {
            throw new Horde_Exception_PermissionDenied();
        }

        $page_id = (($id = $wicked->getPageId($pageName)) === false)
            ? $pageName
            : $id;

        $version = $vars->version;
        if (empty($version)) {
            try {
                $attachments = $wicked->getAttachedFiles($page_id);
                foreach ($attachments as $attachment) {
                    if ($attachment['attachment_name'] == $vars->file) {
                        $version = $attachment['attachment_version'];
                    }
                }
            } catch (Wicked_Exception $e) {
            }

            if (empty($version)) {
                // If we redirect here, we cause an infinite loop with inline
                // attachments.
                header('HTTP/1.1 404 Not Found');
                exit;
            }
        }

        try {
            $data = $wicked->getAttachmentContents($page_id, basename($vars->file), (int) $version);
            $wicked->logAttachmentDownload($page_id, $vars->file);
        } catch (Wicked_Exception $e) {
            // If we redirect here, we cause an infinite loop with inline
            // attachments.
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain');
            echo $e->getMessage();
            exit;
        }

        $type = Horde_Mime_Magic::analyzeData($data, $conf['mime']['magic_db'] ?? null);
        if ($type === false) {
            $type = Horde_Mime_Magic::filenameToMime($vars->file, false);
        }

        return [
            'data' => $data,
            'file' => $vars->file,
            'type' => $type,
        ];
    }

}

<?php

use Horde\Wicked\Domain\PageMatchType;
use Horde\Wicked\Domain\PageRepositoryInterface;
use Horde\Wicked\Domain\SearchRepositoryInterface;
use Horde\Wicked\Service\TagService;

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

/**
 * Wicked external API interface.
 *
 * This file defines Wicked's external API interface. Other applications
 * can interact with Wicked through this API.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package Wicked
 */
class Wicked_Api extends Horde_Registry_Api
{
    /**
     * Links.
     *
     * @var array
     */
    protected $_links = [
        'show' => '%application%/|page|?version=|version|#|toc|',
    ];

    /**
     * Returns a list of available pages.
     *
     * @param boolean $special Include special pages
     * @param boolean $no_cache Always retreive pages from backed
     *
     * @return array  An array of all available pages.
     */
    public function listPages($special = true, $no_cache = false)
    {
        return $GLOBALS['wicked']->getPages($special, $no_cache);
    }

    /**
     * Return basic page information.
     *
     * @param string $pagename Page name
     *
     * @return array  An array of page parameters.
     * @throws Wicked_Exception
     */
    public function getPageInfo($pagename)
    {
        $page = Wicked_Page::getPage($pagename);
        $info = [
            'page_version' => $page->version(),
            'page_checksum' => md5($page->getText()),
            'version_created' => $page->versionCreated(),
            'change_author' => $page->author(),
            'change_log' => $page->changeLog(),
        ];

        try {
            $pageData = $GLOBALS['wicked']->retrieveByName($pagename);
            $pageUid = $pageData['page_uid'] ?? '';
            if ($pageUid !== '') {
                $info['page_uid'] = $pageUid;
                $tagService = $GLOBALS['injector']->getInstance(TagService::class);
                $tags = $tagService->getTags($pageUid);
                if (!empty($tags)) {
                    $info['tags'] = array_values($tags);
                }
            }
        } catch (Throwable) {
        }

        return $info;
    }

    /**
     * Return basic information for multiple pages.
     *
     * @param array $pagenames Page names
     *
     * @return array  An array of arrays of page parameters.
     * @throws Wicked_Exception
     */
    public function getMultiplePageInfo($pagenames = [])
    {
        if (empty($pagenames)) {
            $pagenames = $GLOBALS['wicked']->getPages(false);
        }

        $info = [];
        $uidMap = [];

        foreach ($pagenames as $pagename) {
            $page = Wicked_Page::getPage($pagename);
            $info[$pagename] = [
                'page_version' => $page->version(),
                'page_checksum' => md5($page->getText()),
                'version_created' => $page->versionCreated(),
                'change_author' => $page->author(),
                'change_log' => $page->changeLog(),
            ];
            try {
                $pageData = $GLOBALS['wicked']->retrieveByName($pagename);
                $uid = $pageData['page_uid'] ?? '';
                if ($uid !== '') {
                    $info[$pagename]['page_uid'] = $uid;
                    $uidMap[$uid] = $pagename;
                }
            } catch (Throwable) {
            }
        }

        if (!empty($uidMap)) {
            try {
                $tagService = $GLOBALS['injector']->getInstance(TagService::class);
                $allTags = $tagService->getTagsByPages(array_keys($uidMap));
                foreach ($allTags as $uid => $tags) {
                    $pagename = $uidMap[$uid] ?? null;
                    if ($pagename !== null && !empty($tags)) {
                        $info[$pagename]['tags'] = array_values($tags);
                    }
                }
            } catch (Throwable) {
            }
        }

        return $info;
    }

    /**
     * Return page history.
     *
     * @param string $pagename Page name
     *
     * @return array  An array of page parameters.
     * @throws Wicked_Exception
     */
    public function getPageHistory($pagename)
    {
        $page = Wicked_Page::getPage($pagename);
        $summaries = $GLOBALS['wicked']->getHistory($pagename);

        foreach ($summaries as $i => $summary) {
            $summaries[$i]['page_checksum'] = md5($summary['page_text']);
            unset($summaries[$i]['page_text']);
        }

        return $summaries;
    }

    /**
     * Chech if a page exists
     *
     * @param string $pagename Page name
     *
     * @return boolean
     */
    public function pageExists($pagename)
    {
        // TODO: Move to constructor injection
        $pageRepo = $GLOBALS['injector']->getInstance(PageRepositoryInterface::class);

        return $pageRepo->pageExists($pagename);
    }

    /**
     * Returns a rendered wiki page.
     *
     * @param string $pagename Page to display
     *
     * @return array  Page without CSS link
     * @throws Wicked_Exception
     */
    public function display($pagename)
    {
        // TODO: Move to constructor injection
        $pageRepo = $GLOBALS['injector']->getInstance(PageRepositoryInterface::class);

        $page = Wicked_Page::getPage($pagename);
        $pageRepo->logPageView($page->pageName());
        return $page->displayContents(false);
    }

    /**
     * Returns a rendered wiki page.
     *
     * @param string $pagename Page to display
     * @param string $format Format to render page to (Plain, XHtml)
     *
     * @return array  Rendered page
     * @throws Wicked_Exception
     */
    public function renderPage($pagename, $format = 'Plain')
    {
        // TODO: Move to constructor injection
        $pageRepo = $GLOBALS['injector']->getInstance(PageRepositoryInterface::class);

        $page = Wicked_Page::getPage($pagename);
        $content = $page->getProcessor()->transform($page->getText(), $format);
        $pageRepo->logPageView($page->pageName());
        return $content;
    }

    /**
     * Updates content of a wiki page. If the page does not exist it is
     * created.
     *
     * @param string $pagename Page to edit
     * @param string $text Page content
     * @param string $changelog Description of the change
     *
     * @throws Wicked_Exception
     */
    public function edit($pagename, $text, $changelog = '')
    {
        // TODO: Move to constructor injection
        $pageRepo = $GLOBALS['injector']->getInstance(PageRepositoryInterface::class);

        $page = Wicked_Page::getPage($pagename);
        if (!$page->allows(Wicked::MODE_EDIT)) {
            throw new Wicked_Exception(sprintf(_("You don't have permission to edit \"%s\"."), $pagename));
        }
        if ($GLOBALS['conf']['wicked']['require_change_log']
            && empty($changelog)) {
            throw new Wicked_Exception(_("You must provide a change log."));
        }

        try {
            $content = $page->getText();
        } catch (Wicked_Exception $e) {
            // Maybe the page does not exists, if not create it
            if ($pageRepo->pageExists($pagename)) {
                throw $e;
            }
            $pageRepo->createPage($pagename, $text);
            return;
        }

        if (trim($text) == trim($content)) {
            throw new Wicked_Exception(_("No changes made"));
        }

        $page->updateText($text, $changelog);
    }

    /**
     * Get a list of templates provided by Wicked.  A template is any page
     * whose name begins with "Template"
     *
     * @return arrary  Array on success.
     * @throws Wicked_Exception
     */
    public function listTemplates()
    {
        // TODO: Move to constructor injection
        $searchRepo = $GLOBALS['injector']->getInstance(SearchRepositoryInterface::class);

        $templates = $searchRepo->getMatchingPages('Template', PageMatchType::Ends);
        $list = [['category' => _("Wiki Templates"),
            'templates' => []]];
        foreach ($templates as $page) {
            $list[0]['templates'][] = ['id' => $page['page_name'],
                'name' => $page['page_name']];
        }
        return $list;
    }

    /**
     * Get a template specified by its name.  This is effectively an alias for
     * getPageSource() since Wicked templates are also normal pages.
     * Wicked templates are pages that include "Template" at the beginning of
     * the name.
     *
     * @param string $name  The name of the template to fetch
     *
     * @return string  Template data.
     * @throws Wicked_Exception
     */
    public function getTemplate($name)
    {
        return $this->getPageSource($name);
    }

    /**
     * Get the wiki source of a page specified by its name.
     *
     * @param string $name     The name of the page to fetch
     * @param string $version  Page version
     *
     * @return string  Page data.
     * @throws Wicked_Exception
     */
    public function getPageSource($pagename, $version = null)
    {
        $page = Wicked_Page::getPage($pagename, $version);

        if (!$page->allows(Wicked::MODE_CONTENT)) {
            throw new Wicked_Exception(_("Permission denied."));
        }

        if (!$page->isValid()) {
            throw new Wicked_Exception(_("Invalid page requested."));
        }

        return $page->getText();
    }

    /**
     * Process a completed template to update the named Wiki page.  This
     * method is basically a passthrough to edit().
     *
     * @param string $name   Name of the new or modified page
     * @param string $data   Text content of the populated template
     *
     * @throws Wicked_Exception
     */
    public function saveTemplate($name, $data)
    {
        $this->edit($name, $data, 'Template Auto-fill', false);
    }

    /**
     * Returns the most recently changed pages.
     *
     * @param integer $days  The number of days to look back.
     *
     * @return array  Pages.
     * @throws Wicked_Exception
     */
    public function getRecentChanges($days = 3)
    {
        $info = [];
        foreach ($GLOBALS['wicked']->getRecentChanges($days) as $page) {
            $info[$page['page_name']] = [
                'page_version' => $page['page_version'],
                'page_checksum' => md5($page['page_text']),
                'version_created' => $page['version_created'],
                'change_author' => $page['change_author'],
                'change_log' => $page['change_log'],
            ];
        }

        return $info;
    }

    /**
     * Returns tags for a page.
     *
     * @param string $pagename  Page name.
     *
     * @return array  Tag names.
     */
    public function listTags($pagename)
    {
        try {
            $pageData = $GLOBALS['wicked']->retrieveByName($pagename);
            $pageUid = $pageData['page_uid'] ?? '';
            if ($pageUid === '') {
                return [];
            }
            $tagService = $GLOBALS['injector']->getInstance(TagService::class);

            return array_values($tagService->getTags($pageUid));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Sets tags on a page, replacing any existing tags.
     *
     * @param string $pagename  Page name.
     * @param array  $tags      Tag names.
     *
     * @throws Wicked_Exception
     */
    public function tagPage($pagename, $tags)
    {
        $page = Wicked_Page::getPage($pagename);
        if (!$page->allows(Wicked::MODE_EDIT)) {
            throw new Wicked_Exception(sprintf(
                _("You don't have permission to edit \"%s\"."),
                $pagename,
            ));
        }

        $pageData = $GLOBALS['wicked']->retrieveByName($pagename);
        $pageUid = $pageData['page_uid'] ?? '';
        if ($pageUid === '') {
            throw new Wicked_Exception(_("Page has no UID assigned."));
        }
        $tagService = $GLOBALS['injector']->getInstance(TagService::class);
        if (!$tagService->isAvailable()) {
            throw new Wicked_Exception(_("Tagging is not available."));
        }
        $tagService->replaceTags(
            $pageUid,
            $tags,
            $GLOBALS['registry']->getAuth() ?: '',
        );
    }

    /**
     * Searches for pages matching the given tags.
     *
     * @param array $tags  Tag names to search for.
     *
     * @return array  Page names matching all given tags.
     */
    public function searchByTag($tags)
    {
        try {
            $tagService = $GLOBALS['injector']->getInstance(TagService::class);
            $uids = $tagService->search($tags);
            if (empty($uids)) {
                return [];
            }

            $pages = [];
            $allPages = $GLOBALS['wicked']->getAllPages();
            $uidToName = [];
            foreach ($allPages as $page) {
                if (isset($page['page_uid'])) {
                    $uidToName[$page['page_uid']] = $page['page_name'];
                }
            }
            foreach ($uids as $uid) {
                if (isset($uidToName[$uid])) {
                    $pages[] = $uidToName[$uid];
                }
            }

            return $pages;
        } catch (Throwable) {
            return [];
        }
    }
}

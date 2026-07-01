<?php

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Tyler Colbert <tyler@colberts.us>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @package  Wicked
 */

/**
 * Wicked storage implementation for the Horde_Db database abstraction layer.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Tyler Colbert <tyler@colberts.us>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @package  Wicked
 */
class Wicked_Driver_Sql extends Wicked_Driver
{
    /**
     * Handle for the current database connection.
     *
     * @var Horde_Db_Adapter
     */
    protected $_db;

    /**
     * In-request memo for {@see self::getPages()}. Stores the
     * page_id => page_name map (DB rows only, without the "special"
     * pseudo-pages), primed either from the shared PSR-16 cache or
     * from the DB. Cleared on every write path.
     *
     * @var array|null
     */
    protected $_pageNames;

    /**
     * In-request memo for {@see self::getAllPages()}. Stores full page-row
     * arrays and therefore must be kept separate from {@see self::$_pageNames}
     * (which stores an id => name map). Both memos are cleared on every
     * write path.
     *
     * @var array|null
     */
    protected $_allPages;

    /**
     * Optional shared PSR-16 cache backend for cross-request caching of
     * {@see self::getAllPages()}. Null-safe: when unset, the driver falls
     * back to in-request memoization only.
     *
     * Resolved from the site-configured PSR-16 backend (see
     * {@see \Horde\Core\Factory\SimpleCacheFactory}) — HashTable/Redis,
     * APCu, File, SQL, or NullStorage. The driver treats this as an
     * opaque {@see \Psr\SimpleCache\CacheInterface}: the concrete
     * storage never leaks into wicked.
     *
     * @var \Psr\SimpleCache\CacheInterface|null
     */
    protected $_cache;

    /**
     * TTL (seconds) for {@see self::CACHE_KEY_ALLPAGES}. Passed as the
     * third argument to PSR-16 set(); 0 means "backend default".
     */
    protected int $_allPagesLifetime = 300;

    /**
     * TTL (seconds) for {@see self::CACHE_KEY_PAGENAMES}. Kept in lockstep
     * with {@see self::$_allPagesLifetime}: both caches derive from the
     * same page-table snapshot and share the same invalidation trigger,
     * so different TTLs would only produce brief windows of skew.
     */
    protected int $_pageNamesLifetime = 300;

    /**
     * Cache key for the full page-name list. Prefixed with 'wicked.'
     * because the injected PSR-16 cache is a *site-shared* keyspace
     * (Redis on typical Horde installs); no per-app namespace is
     * applied by the backend.
     */
    private const CACHE_KEY_ALLPAGES = 'wicked.driver.allpages';

    /**
     * Cache key for the id => name map returned by
     * {@see self::getPages()}. Small (~30 KB for ~900 pages) and hit on
     * essentially every page view via
     * {@see \Wicked_Driver::getPageId()} / {@see \Wicked_Driver::pageExists()},
     * so it is the hottest driver-side cache entry by call volume.
     */
    private const CACHE_KEY_PAGENAMES = 'wicked.driver.pagenames';

    /**
     * Constructor.
     *
     * @param array $params  A hash containing connection parameters. May
     *                       include:
     *                       - 'cache': a PSR-16 CacheInterface for
     *                         cross-request caching of getAllPages() and
     *                         getPages().
     *                       - 'allpages_lifetime': int seconds, TTL for
     *                         the getAllPages() cache entry. Also used
     *                         as the TTL for the getPages() id => name
     *                         map when 'pagenames_lifetime' is not
     *                         explicitly set (both derive from the same
     *                         page-table snapshot).
     *                       - 'pagenames_lifetime': int seconds, TTL for
     *                         the getPages() id => name map. Defaults to
     *                         'allpages_lifetime'.
     */
    public function __construct($params = [])
    {
        if (!isset($params['db'])) {
            throw new InvalidArgumentException('Missing db parameter.');
        }
        $this->_db = $params['db'];
        unset($params['db']);

        if (isset($params['cache'])) {
            $this->_cache = $params['cache'];
            unset($params['cache']);
        }

        if (isset($params['allpages_lifetime'])) {
            $this->_allPagesLifetime = (int) $params['allpages_lifetime'];
            $this->_pageNamesLifetime = (int) $params['allpages_lifetime'];
            unset($params['allpages_lifetime']);
        }

        if (isset($params['pagenames_lifetime'])) {
            $this->_pageNamesLifetime = (int) $params['pagenames_lifetime'];
            unset($params['pagenames_lifetime']);
        }

        $params = array_merge([
            'table' => 'wicked_pages',
            'historytable' => 'wicked_history',
            'attachmenttable' => 'wicked_attachments',
            'attachmenthistorytable' => 'wicked_attachment_history',
        ], $params);
        parent::__construct($params);
    }

    /**
     * Retrieves the page of a particular name from the database.
     *
     * @param string $pagename The name of the page to retrieve.
     *
     * @return array
     * @throws Wicked_Exception
     */
    public function retrieveByName($pagename)
    {
        $pages = $this->_retrieve(
            $this->_params['table'],
            ['page_name = ?', [$this->_convertToDriver($pagename)]]
        );

        if (!empty($pages[0])) {
            return $pages[0];
        }

        throw new Wicked_Exception($pagename . ' not found');
    }

    /**
     * Retrieves a historic version of a page.
     *
     * @param string $pagename  The name of the page to retrieve.
     * @param string $version   The version to retrieve.
     *
     * @return array  The page hash.
     * @throws Wicked_Exception
     */
    public function retrieveHistory($pagename, $version)
    {
        if (!preg_match('/^\d+$/', $version)) {
            throw new Wicked_Exception('invalid version number');
        }

        return $this->_retrieve(
            $this->_params['historytable'],
            ['page_name = ? AND page_version = ?',
                [$this->_convertToDriver($pagename), (int) $version]]
        );
    }

    public function getPageById($id)
    {
        return $this->_retrieve(
            $this->_params['table'],
            ['page_id = ?', [(int) $id]]
        );
    }

    /**
     * Returns all pages from the database.
     *
     * Results are memoized for the current request and, when a PSR-16
     * cache backend is configured, cached across requests as well. The
     * cross-request cache is invalidated by
     * {@see self::_invalidatePageCaches()} on every mutating write
     * that flows through this driver instance.
     *
     * The cached payload is the raw row set: it is user-independent.
     * Per-page permission filtering happens downstream in Wicked_Page
     * and must not be embedded here.
     *
     * @return array  All pages.
     */
    public function getAllPages()
    {
        if ($this->_allPages !== null) {
            return $this->_allPages;
        }
        if ($this->_cache !== null) {
            $cached = $this->_cache->get(self::CACHE_KEY_ALLPAGES);
            if ($cached !== null) {
                return $this->_allPages = $cached;
            }
        }
        $this->_allPages = $this->_retrieve(
            $this->_params['table'],
            '',
            'page_name'
        );
        $this->_cache?->set(
            self::CACHE_KEY_ALLPAGES,
            $this->_allPages,
            $this->_allPagesLifetime,
        );
        return $this->_allPages;
    }

    /**
     * Drops every driver-side cache entry that reflects the page set:
     *   - the in-request memos ({@see self::$_allPages} and
     *     {@see self::$_pageNames}),
     *   - the shared {@see self::CACHE_KEY_ALLPAGES} entry, and
     *   - the shared {@see self::CACHE_KEY_PAGENAMES} entry.
     *
     * Called from every write path that mutates the page set.
     */
    private function _invalidatePageCaches(): void
    {
        $this->_allPages = null;
        $this->_pageNames = null;
        if ($this->_cache !== null) {
            $this->_cache->deleteMultiple([
                self::CACHE_KEY_ALLPAGES,
                self::CACHE_KEY_PAGENAMES,
            ]);
        }
    }

    public function getHistory($pagename)
    {
        return $this->_retrieve(
            $this->_params['historytable'],
            ['page_name = ?', [$this->_convertToDriver($pagename)]],
            'page_version DESC'
        );
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
        $where = ['version_created > ?', [time() - (86400 * $days)]];
        $result = $this->_retrieve(
            $this->_params['table'],
            $where,
            'version_created DESC'
        );
        $result2 = $this->_retrieve(
            $this->_params['historytable'],
            $where,
            'version_created DESC'
        );
        return array_merge($result, $result2);
    }

    /**
     * Returns the most recently changed pages.
     *
     * @param integer $limit  The number of most recent pages to return.
     *
     * @return array  Pages.
     * @throws Wicked_Exception
     */
    public function mostRecent($limit = 10)
    {
        $result = $this->_retrieve(
            $this->_params['table'],
            '',
            'version_created DESC',
            $limit
        );
        $result2 = $this->_retrieve(
            $this->_params['historytable'],
            '',
            'version_created DESC',
            $limit
        );
        $result = array_merge($result, $result2);
        usort(
            $result,
            function ($a, $b) {
                return $b['version_created'] - $a['version_created'];
            }
        );
        return array_slice($result, 0, $limit);
    }

    /**
     * Returns the most popular pages.
     *
     * @param integer $limit  The number of most popular pages to return.
     *
     * @return array  Pages.
     * @throws Wicked_Exception
     */
    public function mostPopular($limit = 10)
    {
        return $this->_retrieve(
            $this->_params['table'],
            '',
            'page_hits DESC',
            $limit
        );
    }

    /**
     * Returns the least popular pages.
     *
     * @param integer $limit  The number of least popular pages to return.
     *
     * @return array  Pages.
     * @throws Wicked_Exception
     */
    public function leastPopular($limit = 10)
    {
        return $this->_retrieve(
            $this->_params['table'],
            '',
            'page_hits ASC',
            $limit
        );
    }

    /**
     * Finds pages with matches in the title.
     *
     * @param string $searchtext  The search expression (Google-like).
     * @param boolean $begin      Search only at the begin of the titles?
     *
     * @return array  A list of pages.
     * @throws Wicked_Exception
     */
    public function searchTitles($searchtext, $begin = false)
    {
        $searchtext = $this->_convertToDriver($searchtext);
        try {
            $where = $this->_db->buildClause(
                'page_name',
                'LIKE',
                $searchtext,
                false,
                ['begin' => $begin]
            );
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }
        return $this->_retrieve($this->_params['table'], $where);
    }

    /**
     * Finds pages with matches in text or title.
     *
     * @param string $searchtext  The search expression (Google-like).
     * @param boolean $title      Search both page title and text?
     *
     * @return array  A list of pages.
     * @throws Wicked_Exception
     */
    public function searchText($searchtext, $title = true)
    {
        $searchtext = $this->_convertToDriver($searchtext);

        try {
            $textClause = Horde_Db_SearchParser::parse('page_text', $searchtext);
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }

        if ($title) {
            try {
                $nameClause = Horde_Db_SearchParser::parse('page_name', $searchtext);
            } catch (Horde_Db_Exception $e) {
                throw new Wicked_Exception($e);
            }

            $where = '(' . $nameClause . ') OR (' . $textClause . ')';
        } else {
            $where = $textClause;
        }

        return $this->_retrieve($this->_params['table'], $where);
    }

    public function getBackLinks($pagename)
    {
        try {
            $where = $this->_db->buildClause(
                'page_text',
                'LIKE',
                $this->_convertToDriver($pagename)
            );
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }
        $pages = $this->_retrieve($this->_params['table'], $where);

        /* We've cast a wide net, so now we filter out pages which don't
         * actually refer to $pagename. */
        /* @todo this should match the current wiki engine's syntax. */
        $patterns = ['/\(\(' . preg_quote($pagename, '/') . '(?:\|[^)]+)?\)\)/'];
        if (preg_match('/^' . Wicked::REGEXP_WIKIWORD . '$/', $pagename)) {
            $patterns[] = '/\b' . preg_quote($pagename, '/') . '\b/';
        }

        foreach ($pages as $key => $page) {
            $match = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $page['page_text'])) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                unset($pages[$key]);
            }
        }

        return $pages;
    }

    public function getMatchingPages(
        $searchtext,
        $matchType = Wicked_Page::MATCH_ANY
    ) {
        $searchtext = strtolower($searchtext ?? '');

        try {
            /* Short circuit the simple case. */
            if ($matchType == Wicked_Page::MATCH_ANY) {
                return $this->_retrieve(
                    $this->_params['table'],
                    'LOWER(page_name) LIKE ' . $this->_db->quote('%' . $searchtext . '%')
                );
            }

            $clauses = [];
            if ($matchType & Wicked_Page::MATCH_LEFT) {
                $clauses[] = 'LOWER(page_name) LIKE ' . $this->_db->quote($searchtext . '%');
            }
            if ($matchType & Wicked_Page::MATCH_RIGHT) {
                $clauses[] = 'LOWER(page_name) LIKE ' . $this->_db->quote('%' . $searchtext);
            }
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }

        if (!$clauses) {
            return [];
        }

        return $this->_retrieve(
            $this->_params['table'],
            implode(' OR ', $clauses)
        );
    }

    public function getLikePages($pagename)
    {
        if (Horde_String::isUpper($pagename, 'UTF-8')) {
            $firstword = $pagename;
            $lastword = null;
        } else {
            /* Get the first and last word of the page name. */
            $count = preg_match_all('/[A-Z][a-z0-9]*/', $pagename, $matches);
            if (!$count) {
                return [];
            }
            $matches = $matches[0];

            $firstword = $matches[0];
            $lastword = $matches[$count - 1];

            if (strlen($firstword) == 1 && strlen($matches[1]) == 1) {
                for ($i = 1; $i < $count; $i++) {
                    $firstword .= $matches[$i];
                    if (isset($matches[$i + 1]) && strlen($matches[$i + 1]) > 1) {
                        break;
                    }
                }
            }

            if (strlen($lastword) == 1 && strlen($matches[$count - 2]) == 1) {
                for ($i = $count - 2; $i > 0; $i--) {
                    $lastword = $matches[$i] . $lastword;
                    if (isset($matches[$i - 1]) && strlen($matches[$i - 1]) > 1) {
                        break;
                    }
                }
            }
        }

        try {
            $where = $this->_db->buildClause('page_name', 'LIKE', $firstword);
            if (!empty($lastword) && $lastword != $firstword) {
                $where .= ' OR ' . $this->_db->buildClause('page_name', 'LIKE', $lastword);
            }
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }

        return $this->_retrieve($this->_params['table'], $where);
    }

    /**
     * Retrieves data on files attached to a page.
     *
     * @param string $pageId        This is the Id of the page for which we'd
     *                              like to find attached files.
     * @param boolean $allversions  Whether to include all versions. If false
     *                              or omitted, only the most recent version
     *                              of each attachment is returned.
     * @return array  An array of key/value arrays describing the attached
     *                files.
     * @throws Wicked_Exception
     */
    public function getAttachedFiles($pageId, $allversions = false)
    {
        $where = ['page_id = ?', [(int) $pageId]];
        $data = $this->_retrieve($this->_params['attachmenttable'], $where);

        if ($allversions) {
            $more_data = $this->_retrieve(
                $this->_params['attachmenthistorytable'],
                $where
            );
            $data = array_merge($data, $more_data);
        }

        foreach (array_keys($data) as $key) {
            $data[$key]['attachment_name'] = $this->_convertFromDriver($data[$key]['attachment_name']);
        }

        usort(
            $data,
            function ($a, $b) {
                if ($res = strcmp($a['attachment_name'], $b['attachment_name'])) {
                    return $res;
                }
                return ($a['attachment_version'] - $b['attachment_version']);
            }
        );

        return $data;
    }

    public function getAllAttachments(): array
    {
        $data = $this->_retrieve($this->_params['attachmenttable'], '');
        foreach (array_keys($data) as $key) {
            $data[$key]['attachment_name'] = $this->_convertFromDriver($data[$key]['attachment_name']);
        }

        return $data;
    }

    /**
     * Removes a single version or all versions of an attachment from
     * $pageId.
     *
     * @param integer $pageId     The Id of the page the file is attached to.
     * @param string $attachment  The name of the file.
     * @param string $version     If specified, the version to delete. If null,
     *                            then all versions of $attachment will be
     *                            removed.
     *
     * @throws Wicked_Exception
     */
    public function removeAttachment($pageId, $attachment, $version = null)
    {
        /* Try to delete from the VFS first. */
        parent::removeAttachment($pageId, $attachment, $version);

        /* First try against the current attachments table. */
        $sql = 'DELETE FROM ' . $this->_params['attachmenttable']
            . ' WHERE page_id = ? AND attachment_name = ?';
        $params = [(int) $pageId, $attachment];
        if (!is_null($version)) {
            $sql .= ' AND attachment_version = ?';
            $params[] = (int) $version;
        }

        try {
            $this->_db->beginDbTransaction();
            $result = $this->_db->delete($sql, $params);

            /* Now try against the attachment history table. $params is
             * unchanged. */
            $sql = 'DELETE FROM ' . $this->_params['attachmenthistorytable']
                . ' WHERE page_id = ? AND attachment_name = ?';
            if (!is_null($version)) {
                $sql .= ' AND attachment_version = ?';
            }
            $this->_db->delete($sql, $params);
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Removes all attachments from a page.
     *
     * @param integer $pageId  A page ID.
     *
     * @throws Wicked_Exception
     */
    public function removeAllAttachments($pageId)
    {
        /* Try to delete from the VFS first. */
        $result = parent::removeAllAttachments($pageId);

        $params = [(int) $pageId];
        try {
            $this->_db->beginDbTransaction();
            /* First try against the current attachments table. */
            $result = $this->_db->delete(
                'DELETE FROM ' . $this->_params['attachmenttable']
                . ' WHERE page_id = ?',
                $params
            );

            /* Now try against the attachment history table. $params is
             * unchanged. */
            $this->_db->delete(
                'DELETE FROM ' . $this->_params['attachmenthistorytable']
                . ' WHERE page_id = ?',
                $params
            );
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Handles the driver-specific portion of attaching a file.
     *
     * Wicked_Driver::attachFile() calls down to this method for the driver-
     * specific portion, and then uses VFS to store the attachment.
     *
     * @param array $file  See Wicked_Driver::attachFile().
     *
     * @return integer  The new version of the file attached.
     * @throws Wicked_Exception
     */
    protected function _attachFile($file)
    {
        if ($file['change_author'] === false) {
            $file['change_author'] = null;
        }

        $attachments = $this->_retrieve(
            $this->_params['attachmenttable'],
            ['page_id = ? AND attachment_name = ?',
                [(int) $file['page_id'], $file['attachment_name']]]
        );

        if ($attachments) {
            $version = $attachments[0]['attachment_version'] + 1;

            try {
                $this->_db->beginDbTransaction();
                $this->_db->insert(
                    sprintf(
                        'INSERT INTO %s (page_id, attachment_name, attachment_version, attachment_created, change_author, change_log) SELECT page_id, attachment_name, attachment_version, attachment_created, change_author, change_log FROM %s WHERE page_id = ? AND attachment_name = ?',
                        $this->_params['attachmenthistorytable'],
                        $this->_params['attachmenttable']
                    ),
                    [(int) $file['page_id'],
                        $file['attachment_name']]
                );

                $this->_db->update(
                    sprintf(
                        'UPDATE %s SET attachment_version = ?, change_log = ?, change_author = ?, attachment_created = ? WHERE page_id = ? AND attachment_name = ?',
                        $this->_params['attachmenttable']
                    ),
                    [(int) $version,
                        $this->_convertToDriver($file['change_log']),
                        $this->_convertToDriver($file['change_author']),
                        time(),
                        (int) $file['page_id'],
                        $this->_convertToDriver($file['attachment_name'])]
                );
                $this->_db->commitDbTransaction();
            } catch (Horde_Db_Exception $e) {
                $this->_db->rollbackDbTransaction();
                throw new Wicked_Exception($e);
            }
        } else {
            $version = 1;
            try {
                $this->_db->insert(
                    sprintf(
                        'INSERT INTO %s (page_id, attachment_version, change_log, change_author, attachment_created, attachment_name) VALUES (?, 1, ?, ?, ?, ?)',
                        $this->_params['attachmenttable']
                    ),
                    [(int) $file['page_id'],
                        $this->_convertToDriver($file['change_log']),
                        $this->_convertToDriver($file['change_author']),
                        time(),
                        $this->_convertToDriver($file['attachment_name'])]
                );
            } catch (Horde_Db_Exception $e) {
                throw new Wicked_Exception($e);
            }
        }

        return $version;
    }

    /**
     * Logs a page view.
     *
     * @param string $pagename  The page that was viewed.
     *
     * @throws Wicked_Exception
     */
    public function logPageView($pagename)
    {
        try {
            return $this->_db->update(
                'UPDATE ' . $this->_params['table']
                . ' SET page_hits = page_hits + 1 WHERE page_name = ?',
                [$this->_convertToDriver($pagename)]
            );
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Logs an attachment download.
     *
     * @param integer $pageid     The page with the attachment.
     * @param string $attachment  The attachment name.
     *
     * @throws Wicked_Exception
     */
    public function logAttachmentDownload($pageid, $attachment)
    {
        try {
            return $this->_db->update(
                'UPDATE ' . $this->_params['attachmenttable']
                . ' SET attachment_hits = attachment_hits + 1'
                . ' WHERE page_id = ? AND attachment_name = ?',
                [(int) $pageid, $this->_convertToDriver($attachment)]
            );
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Creates a new page.
     *
     * @param string $pagename  The new page's name.
     * @param string $text      The new page's text.
     *
     * @throws Wicked_Exception
     */
    public function newPage($pagename, $text)
    {
        // Invalidate cached page lists when creating new page
        $this->_invalidatePageCaches();

        if (!strlen($pagename)) {
            throw new Wicked_Exception(_("Page name must not be empty"));
        }

        if ($GLOBALS['browser']->isRobot()) {
            throw new Wicked_Exception(_("Robots are not allowed to create pages"));
        }

        $author = $GLOBALS['registry']->getAuth();
        if ($author === false) {
            $author = null;
        }

        /* Attempt the insertion/update query. */
        try {
            $page_id = $this->_db->insert(
                'INSERT INTO ' . $this->_params['table']
                . ' (page_name, page_text, version_created, page_version,'
                . ' page_hits, change_author) VALUES (?, ?, ?, 1, 0, ?)',
                [$this->_convertToDriver($pagename),
                    $this->_convertToDriver($text),
                    time(),
                    $author]
            );
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }

        /* Send notification. */
        $url = Wicked::url($pagename, true, -1);
        Wicked::mail(
            "Created page: $url\n\n$text\n",
            ['Subject' => '[' . $GLOBALS['registry']->get('name')
                         . '] created: ' . $pagename]
        );

        return $page_id;
    }

    /**
     * Renames a page, keeping the page's history.
     *
     * @param string $pagename  The name of the page to rename.
     * @param string $newname   The page's new name.
     *
     * @throws Wicked_Exception
     */
    public function renamePage($pagename, $newname)
    {
        // Invalidate cached page lists when renaming page
        $this->_invalidatePageCaches();

        try {
            $this->_db->beginDbTransaction();
            $this->_db->update(
                'UPDATE ' . $this->_params['table']
                . ' SET page_name = ? WHERE page_name = ?',
                [$this->_convertToDriver($newname),
                    $this->_convertToDriver($pagename)]
            );

            $this->_db->update(
                'UPDATE ' . $this->_params['historytable']
                . ' SET page_name = ? WHERE page_name = ?',
                [$this->_convertToDriver($newname),
                    $this->_convertToDriver($pagename)]
            );
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }

        $changelog = sprintf(_("Renamed page from %s"), $pagename);
        $newPage = $this->retrieveByName($newname);

        return $this->updateText($newname, $newPage['page_text'], $changelog);
    }

    public function updateText($pagename, $text, $changelog)
    {
        // Invalidate cached page lists when updating page
        $this->_invalidatePageCaches();

        if (!$this->pageExists($pagename)) {
            return $this->newPage($pagename, $text);
        }

        /* Copy the old version into the page history. */
        Horde::log('Page ' . $pagename . ' saved with user agent ' . $GLOBALS['browser']->getAgentString(), 'DEBUG');

        $author = $GLOBALS['registry']->getAuth();
        if ($author === false) {
            $author = null;
        }

        try {
            $this->_db->beginDbTransaction();
            $this->_db->insert(
                sprintf(
                    'INSERT INTO %s (page_id, page_name, page_text, page_version, version_created, change_author, change_log) SELECT page_id, page_name, page_text, page_version, version_created, change_author, change_log FROM %s WHERE page_name = ?',
                    $this->_params['historytable'],
                    $this->_params['table']
                ),
                [$this->_convertToDriver($pagename)]
            );

            /* Now move on to updating the record. */
            $this->_db->update(
                'UPDATE ' . $this->_params['table']
                . ' SET change_author = ?, page_text = ?, change_log = ?,'
                . ' version_created = ?, page_version = page_version + 1'
                . ' WHERE page_name = ?',
                [$author,
                    $this->_convertToDriver($text),
                    $this->_convertToDriver($changelog),
                    time(),
                    $this->_convertToDriver($pagename)]
            );
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Returns a map of page_id => page_name for every wiki page.
     *
     * Backed by three layers:
     *   1. {@see self::$_pageNames} — in-request memo, no round-trip.
     *   2. The injected PSR-16 cache (Redis / APCu / etc.) under
     *      {@see self::CACHE_KEY_PAGENAMES}.
     *   3. SELECT page_id, page_name FROM wicked_pages.
     *
     * Because getPageId() and pageExists() are invoked on essentially
     * every wiki page render, this is the hottest driver read. Keeping
     * it in a small (~30 KB) shared cache separate from the ~2 MB
     * getAllPages() blob is deliberate — see the class docblock.
     *
     * @param bool $special   Include the pseudo "special" pages (AllPages,
     *                        RecentChanges, MostPopular, etc.) that live
     *                        as files under lib/Page/ rather than in the
     *                        database. Special pages are keyed by name
     *                        instead of numeric id.
     * @param bool $no_cache  When true, invalidate the shared cache
     *                        before re-reading. Preserves the old
     *                        "force a re-read" semantics for callers
     *                        that want to see their just-written row.
     *
     * @return array  page_id => page_name (+ SpecialName => SpecialName
     *                when $special is true).
     */
    public function getPages($special = true, $no_cache = false)
    {
        if ($no_cache) {
            $this->_pageNames = null;
            $this->_cache?->delete(self::CACHE_KEY_PAGENAMES);
        }

        if ($this->_pageNames === null) {
            if ($this->_cache !== null) {
                $cached = $this->_cache->get(self::CACHE_KEY_PAGENAMES);
                if ($cached !== null) {
                    $this->_pageNames = $cached;
                }
            }
        }

        if ($this->_pageNames === null) {
            try {
                $result = $this->_db->selectAssoc(
                    'SELECT page_id, page_name FROM ' . $this->_params['table']
                );
            } catch (Horde_Db_Exception $e) {
                throw new Wicked_Exception($e);
            }
            $this->_pageNames = $this->_convertFromDriver($result);
            $this->_cache?->set(
                self::CACHE_KEY_PAGENAMES,
                $this->_pageNames,
                $this->_pageNamesLifetime,
            );
        }

        if ($special) {
            return $this->_pageNames + $this->getSpecialPages();
        }

        return $this->_pageNames;
    }

    /**
     */
    public function removeVersion($pagename, $version)
    {
        $values = [$this->_convertToDriver($pagename), (int) $version];

        /* We need to know if we're deleting the current version. */
        try {
            $result = $this->_db->selectValue(
                'SELECT 1 FROM ' . $this->_params['table']
                . ' WHERE page_name = ? AND page_version = ?',
                $values
            );
        } catch (Horde_Db_Exception $e) {
            $result = false;
        }

        if (!$result) {
            /* Removing a historical revision - we can just slice it out of the
             * history table. $values is unchanged. */
            try {
                $this->_db->delete(
                    'DELETE FROM ' . $this->_params['historytable']
                    . ' WHERE page_name = ? and page_version = ?',
                    $values
                );
            } catch (Horde_Db_Exception $e) {
                throw new Wicked_Exception($e);
            }
            return;
        }

        /* We're deleting the current version. Have to promote the next-most
         * revision from the history table. */
        try {
            $query = 'SELECT * FROM ' . $this->_params['historytable']
                . ' WHERE page_name = ? ORDER BY page_version DESC';
            $query = $this->_db->addLimitOffset($query, ['limit' => 1]);
            $revision = $this->_db->selectOne(
                $query,
                [$this->_convertToDriver($pagename)]
            );

            /* Replace the current version of the page with the version being
             * promoted. */
            $this->_db->beginDbTransaction();
            $this->_db->update(
                'UPDATE ' . $this->_params['table'] . ' SET'
                . ' page_text = ?, page_version = ?,'
                . ' version_created = ?, change_author = ?, change_log = ?'
                . ' WHERE page_name = ?',
                [$revision['page_text'],
                    (int) $revision['page_version'],
                    (int) $revision['version_created'],
                    $revision['change_author'],
                    $revision['change_log'],
                    $this->_convertToDriver($pagename)]
            );

            /* Finally, remove the version that we promoted from the history
             * table. */
            $this->_db->delete(
                'DELETE FROM ' . $this->_params['historytable']
                . ' WHERE page_name = ? and page_version = ?',
                [$this->_convertToDriver($pagename),
                    (int) $revision['page_version']]
            );
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }
    }

    /**
     */
    public function removeAllVersions($pagename)
    {
        /* Remove attachments and do other cleanup. */
        parent::removeAllVersions($pagename);

        $this->_invalidatePageCaches();

        try {
            $this->_db->beginDbTransaction();
            $this->_db->delete(
                'DELETE FROM ' . $this->_params['table']
                . ' WHERE page_name = ?',
                [$this->_convertToDriver($pagename)]
            );

            $this->_db->delete(
                'DELETE FROM ' . $this->_params['historytable']
                . ' WHERE page_name = ?',
                [$this->_convertToDriver($pagename)]
            );
            $this->_db->commitDbTransaction();
        } catch (Horde_Db_Exception $e) {
            $this->_db->rollbackDbTransaction();
            throw new Wicked_Exception($e);
        }
    }

    /**
     * Retrieves a set of pages matching an SQL WHERE clause.
     *
     * @param string $table        Table to retrieve pages from.
     * @param array|string $where  Where clause for sql statement (without the
     *                             'WHERE'). If an array the 1st element is the
     *                             clause with placeholder, the 2nd element the
     *                             values.
     * @param string $orderBy      Order results by this column.
     * @param integer $limit       Maximum number of pages to fetch.
     *
     * @return array  A list of page hashes.
     * @throws Wicked_Exception
     */
    protected function _retrieve($table, $where, $orderBy = null, $limit = null)
    {
        $query = 'SELECT * FROM ' . $table;
        $values = [];
        if (!empty($where)) {
            $query .= ' WHERE ';
            if (is_array($where)) {
                $query .= $where[0];
                $values = $where[1];
            } else {
                $query .= $where;
            }
        }
        if (!empty($orderBy)) {
            $query .= ' ORDER BY ' . $orderBy;
        }
        if (!empty($limit)) {
            try {
                $query = $this->_db->addLimitOffset($query, ['limit' => $limit]);
            } catch (Horde_Db_Exception $e) {
                throw new Wicked_Exception($e);
            }
        }

        try {
            $result = $this->_db->select($query, $values);
        } catch (Horde_Db_Exception $e) {
            throw new Wicked_Exception($e);
        }

        $pages = [];
        foreach ($result as $row) {
            if (isset($row['page_name'])) {
                $row['page_name'] = $this->_convertFromDriver($row['page_name']);
            }
            if (isset($row['page_text'])) {
                $row['page_text'] = $this->_convertFromDriver($row['page_text']);
            }
            if (isset($row['change_log'])) {
                $row['change_log'] = $this->_convertFromDriver($row['change_log']);
            }
            $pages[] = $row;
        }

        return $pages;
    }

    /**
     * Returns the charset used by the backend.
     *
     * @return string  The backend's charset
     */
    public function getCharset()
    {
        return $this->_db->getOption('charset');
    }

    /**
     * Converts a value from the driver's charset to the default charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed  The converted value.
     */
    protected function _convertFromDriver($value)
    {
        return Horde_String::convertCharset($value, $this->getCharset(), 'UTF-8');
    }

    /**
     * Converts a value from the default charset to the driver's charset.
     *
     * @param mixed $value  A value to convert.
     *
     * @return mixed  The converted value.
     */
    protected function _convertToDriver($value)
    {
        return Horde_String::convertCharset($value, 'UTF-8', $this->getCharset());
    }
}

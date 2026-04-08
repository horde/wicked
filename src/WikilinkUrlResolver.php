<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked;

/**
 * Resolves wiki page names to URL strings
 *
 * Abstraction over URL generation to decouple the wiki engine from
 * Horde's static Wicked::url() method, enabling testing and
 * alternative URL schemes.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
interface WikilinkUrlResolver
{
    /**
     * Resolve a wiki page name to a URL string
     *
     * @param string $page Wiki page name
     *
     * @return string URL for the page
     */
    public function resolve(string $page): string;
}

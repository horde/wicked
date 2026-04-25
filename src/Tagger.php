<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked;

use Horde_Core_Tagger;
use Throwable;

class Tagger extends Horde_Core_Tagger
{
    protected $_app = 'wicked';

    protected $_types = ['page'];

    /**
     * Searches for pages matching the given tags.
     *
     * @param array $tags    Tag names or IDs to search for.
     * @param array $filter  Additional filters (unused for now).
     *
     * @return array  Array of page UIDs matching all given tags.
     */
    public function search($tags, $filter = [])
    {
        $args = [];

        try {
            $args['tagId'] = $GLOBALS['injector']
                ->getInstance('Content_Tagger')
                ->ensureTags($tags);
        } catch (Throwable) {
            return [];
        }

        $args['typeId'] = $this->_type_ids['page'];

        return array_values(
            $GLOBALS['injector']
                ->getInstance('Content_Tagger')
                ->getObjects($args)
        );
    }
}

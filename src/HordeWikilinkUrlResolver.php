<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked;

use Wicked;

/**
 * URL resolver using Horde's Wicked::url() helper
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class HordeWikilinkUrlResolver implements WikilinkUrlResolver
{
    public function resolve(string $page): string
    {
        return (string) Wicked::url($page);
    }
}

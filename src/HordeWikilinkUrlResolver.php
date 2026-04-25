<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked;

use Horde\Core\Uri\UriBuilderInterface;

/**
 * URL resolver that builds wiki page URLs from the registry webroot.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class HordeWikilinkUrlResolver implements WikilinkUrlResolver
{
    public function __construct(
        private readonly UriBuilderInterface $uriBuilder,
    ) {}

    public function resolve(string $page): string
    {
        return $this->uriBuilder
            ->withAppWebroot('wicked')
            ->withPart(str_replace('%2F', '/', urlencode($page)))
            ->getPath();
    }
}

<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Service;

use Horde_View_Topbar;

/**
 * Injectable topbar search configurator for Wicked.
 *
 * Sets up the Horde topbar search widget to submit wiki page searches.
 * Replaces the static Wicked::setTopbar().
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class TopbarSearch
{
    public function __construct(
        private readonly Horde_View_Topbar $topbar,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function apply(): void
    {
        $this->topbar->search = true;
        $this->topbar->searchAction = $this->urlGenerator->urlFor('Pages', ['page' => 'Search']);
        $this->topbar->searchParameters = ['page' => 'Search'];
    }
}

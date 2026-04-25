<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain\Repository;

use Horde\Wicked\Domain\PageMatchType;
use Horde\Wicked\Domain\SearchRepositoryInterface;
use Wicked_Driver;

final class DriverSearchRepository implements SearchRepositoryInterface
{
    public function __construct(
        private readonly Wicked_Driver $driver,
    ) {}

    /**
     * @return list<array>
     */
    public function searchTitles(string $searchText, bool $begin = false): array
    {
        return $this->driver->searchTitles($searchText, $begin);
    }

    /**
     * @return list<array>
     */
    public function searchText(string $searchText): array
    {
        return $this->driver->searchText($searchText);
    }

    /**
     * @return list<array>
     */
    public function getBackLinks(string $pageName): array
    {
        return $this->driver->getBackLinks($pageName);
    }

    /**
     * @return list<array>
     */
    public function getLikePages(string $pageName): array
    {
        return $this->driver->getLikePages($pageName);
    }

    /**
     * @return list<array>
     */
    public function getMatchingPages(string $searchText, PageMatchType $matchType = PageMatchType::Any): array
    {
        return $this->driver->getMatchingPages($searchText, $matchType->value);
    }
}

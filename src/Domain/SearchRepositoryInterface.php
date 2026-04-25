<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

interface SearchRepositoryInterface
{
    /**
     * @return list<array>
     */
    public function searchTitles(string $searchText, bool $begin = false): array;

    /**
     * @return list<array>
     */
    public function searchText(string $searchText): array;

    /**
     * @return list<array>
     */
    public function getBackLinks(string $pageName): array;

    /**
     * @return list<array>
     */
    public function getLikePages(string $pageName): array;

    /**
     * @return list<array>
     */
    public function getMatchingPages(string $searchText, PageMatchType $matchType = PageMatchType::Any): array;
}

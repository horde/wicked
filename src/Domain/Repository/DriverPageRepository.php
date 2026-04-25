<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain\Repository;

use Horde\Wicked\Domain\PageRepositoryInterface;
use Horde\Wicked\Domain\WikiPage;
use Horde\Wicked\Domain\WikiPageHistory;
use Wicked_Driver;
use Wicked_Exception;

final class DriverPageRepository implements PageRepositoryInterface
{
    public function __construct(
        private readonly Wicked_Driver $driver,
    ) {}

    public function getByName(string $pageName): WikiPage
    {
        return WikiPage::fromDriverArray($this->driver->retrieveByName($pageName));
    }

    public function getById(int $id): WikiPage
    {
        $pages = $this->driver->getPageById($id);

        if (empty($pages[0])) {
            throw new Wicked_Exception('Page with id ' . $id . ' not found');
        }

        return WikiPage::fromDriverArray($pages[0]);
    }

    public function getByUid(string $uid): WikiPage
    {
        return WikiPage::fromDriverArray($this->driver->retrieveByUid($uid));
    }

    public function getHistoryVersion(string $pageName, int $version): WikiPageHistory
    {
        $rows = $this->driver->retrieveHistory($pageName, (string) $version);

        if (empty($rows[0])) {
            throw new Wicked_Exception('History version ' . $version . ' of ' . $pageName . ' not found');
        }

        return WikiPageHistory::fromDriverArray($rows[0]);
    }

    /**
     * @return list<WikiPageHistory>
     */
    public function getHistory(string $pageName): array
    {
        return array_map(
            WikiPageHistory::fromDriverArray(...),
            $this->driver->getHistory($pageName),
        );
    }

    /**
     * @return list<WikiPage>
     */
    public function getAllPages(): array
    {
        return array_map(
            WikiPage::fromDriverArray(...),
            $this->driver->getAllPages(),
        );
    }

    /**
     * @return list<WikiPage>
     */
    public function getRecentChanges(int $days = 3): array
    {
        return array_map(
            WikiPage::fromDriverArray(...),
            $this->driver->getRecentChanges($days),
        );
    }

    /**
     * @return list<WikiPage>
     */
    public function getMostPopular(int $limit = 10): array
    {
        return array_map(
            WikiPage::fromDriverArray(...),
            $this->driver->mostPopular($limit),
        );
    }

    /**
     * @return list<WikiPage>
     */
    public function getLeastPopular(int $limit = 10): array
    {
        return array_map(
            WikiPage::fromDriverArray(...),
            $this->driver->leastPopular($limit),
        );
    }

    public function pageExists(string $pageName): bool
    {
        return $this->driver->pageExists($pageName);
    }

    public function getPageId(string $pageName): int|false
    {
        return $this->driver->getPageId($pageName);
    }

    public function createPage(string $pageName, string $text): int
    {
        return $this->driver->newPage($pageName, $text);
    }

    public function updateText(string $pageName, string $text, string $changelog): void
    {
        $this->driver->updateText($pageName, $text, $changelog);
    }

    public function renamePage(string $pageName, string $newName): void
    {
        $this->driver->renamePage($pageName, $newName);
    }

    public function removeVersion(string $pageName, int $version): void
    {
        $this->driver->removeVersion($pageName, $version);
    }

    public function removeAllVersions(string $pageName): void
    {
        $this->driver->removeAllVersions($pageName);
    }

    public function logPageView(string $pageName): void
    {
        $this->driver->logPageView($pageName);
    }
}

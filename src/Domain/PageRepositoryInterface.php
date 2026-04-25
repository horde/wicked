<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

interface PageRepositoryInterface
{
    public function getByName(string $pageName): WikiPage;

    public function getById(int $id): WikiPage;

    public function getByUid(string $uid): WikiPage;

    public function getHistoryVersion(string $pageName, int $version): WikiPageHistory;

    /**
     * @return list<WikiPageHistory>
     */
    public function getHistory(string $pageName): array;

    /**
     * @return list<WikiPage>
     */
    public function getAllPages(): array;

    /**
     * @return list<WikiPage>
     */
    public function getRecentChanges(int $days = 3): array;

    /**
     * @return list<WikiPage>
     */
    public function getMostPopular(int $limit = 10): array;

    /**
     * @return list<WikiPage>
     */
    public function getLeastPopular(int $limit = 10): array;

    public function pageExists(string $pageName): bool;

    public function getPageId(string $pageName): int|false;

    public function createPage(string $pageName, string $text): int;

    public function updateText(string $pageName, string $text, string $changelog): void;

    public function renamePage(string $pageName, string $newName): void;

    public function removeVersion(string $pageName, int $version): void;

    public function removeAllVersions(string $pageName): void;

    public function logPageView(string $pageName): void;

    public function updatePageFormat(string $pageName, ?string $format): void;
}

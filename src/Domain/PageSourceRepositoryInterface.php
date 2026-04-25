<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

interface PageSourceRepositoryInterface
{
    public function getByPageUid(string $pageUid): ?PageSource;

    /** @return list<PageSource> */
    public function findByRepository(string $repository): array;

    public function save(PageSource $source): void;

    public function delete(string $pageUid): void;

    public function updateSyncState(string $pageUid, string $commitSha): void;
}

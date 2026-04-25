<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain\Repository;

use Horde\Wicked\Domain\PageSource;
use Horde\Wicked\Domain\PageSourceRepositoryInterface;
use Horde_Db_Adapter;

final class DriverPageSourceRepository implements PageSourceRepositoryInterface
{
    public function __construct(
        private readonly Horde_Db_Adapter $db,
    ) {}

    public function getByPageUid(string $pageUid): ?PageSource
    {
        $row = $this->db->selectOne(
            'SELECT * FROM wicked_page_sources WHERE page_uid = ?',
            [$pageUid],
        );

        if ($row === false) {
            return null;
        }

        return PageSource::fromDriverArray($row);
    }

    public function findByRepository(string $repository): array
    {
        $rows = $this->db->select(
            'SELECT * FROM wicked_page_sources WHERE repository = ?',
            [$repository],
        );

        return array_map(PageSource::fromDriverArray(...), $rows);
    }

    public function save(PageSource $source): void
    {
        $existing = $this->db->selectOne(
            'SELECT 1 FROM wicked_page_sources WHERE page_uid = ?',
            [$source->pageUid],
        );

        if ($existing !== false) {
            $this->db->update(
                'UPDATE wicked_page_sources'
                    . ' SET source_type = ?, repository = ?, file_path = ?, ref = ?'
                    . ' WHERE page_uid = ?',
                [
                    $source->sourceType,
                    $source->repository,
                    $source->filePath,
                    $source->ref,
                    $source->pageUid,
                ],
            );
        } else {
            $this->db->insert(
                'INSERT INTO wicked_page_sources'
                    . ' (page_uid, source_type, repository, file_path, ref)'
                    . ' VALUES (?, ?, ?, ?, ?)',
                [
                    $source->pageUid,
                    $source->sourceType,
                    $source->repository,
                    $source->filePath,
                    $source->ref,
                ],
            );
        }
    }

    public function delete(string $pageUid): void
    {
        $this->db->delete(
            'DELETE FROM wicked_page_sources WHERE page_uid = ?',
            [$pageUid],
        );
    }

    public function updateSyncState(string $pageUid, string $commitSha): void
    {
        $this->db->update(
            'UPDATE wicked_page_sources'
                . ' SET last_commit_sha = ?, last_synced_at = ?'
                . ' WHERE page_uid = ?',
            [$commitSha, date('Y-m-d H:i:s'), $pageUid],
        );
    }
}

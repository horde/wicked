<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

final readonly class PageSource
{
    public function __construct(
        public string $pageUid,
        public string $sourceType,
        public string $repository,
        public string $filePath,
        public string $ref,
        public ?string $lastSyncedAt = null,
        public ?string $lastCommitSha = null,
    ) {}

    public static function fromDriverArray(array $data): self
    {
        return new self(
            pageUid: (string) $data['page_uid'],
            sourceType: (string) $data['source_type'],
            repository: (string) $data['repository'],
            filePath: (string) $data['file_path'],
            ref: (string) ($data['ref'] ?? 'main'),
            lastSyncedAt: $data['last_synced_at'] ?? null,
            lastCommitSha: $data['last_commit_sha'] ?? null,
        );
    }
}

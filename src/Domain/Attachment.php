<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

final readonly class Attachment
{
    public function __construct(
        public int $pageId,
        public string $name,
        public int $version,
        public int $created,
        public int $hits,
        public ?string $changeAuthor,
        public ?string $changeLog,
        public ?string $changeIdentityId = null,
    ) {}

    /**
     * Create from a driver array as returned by getAttachedFiles().
     *
     * Expected keys: page_id, attachment_name, attachment_version,
     *                attachment_created, attachment_hits, change_author,
     *                change_log, change_identity_id
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            pageId: (int) $data['page_id'],
            name: (string) ($data['attachment_name'] ?? ''),
            version: (int) ($data['attachment_version'] ?? 0),
            created: (int) ($data['attachment_created'] ?? 0),
            hits: (int) ($data['attachment_hits'] ?? 0),
            changeAuthor: $data['change_author'] ?? null,
            changeLog: $data['change_log'] ?? null,
            changeIdentityId: $data['change_identity_id'] ?? null,
        );
    }
}

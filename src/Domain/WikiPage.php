<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

final readonly class WikiPage
{
    public function __construct(
        public int $id,
        public string $uid,
        public string $name,
        public string $text,
        public int $version,
        public int $hits,
        public int $versionCreated,
        public ?string $changeAuthor,
        public ?string $changeLog,
        public ?string $changeIdentityId = null,
    ) {}

    /**
     * Create from a driver array as returned by retrieveByName().
     *
     * Expected keys: page_id, page_uid, page_name, page_text, page_version,
     *                page_hits, version_created, change_author, change_log,
     *                change_identity_id
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['page_id'],
            uid: (string) ($data['page_uid'] ?? ''),
            name: (string) ($data['page_name'] ?? ''),
            text: (string) ($data['page_text'] ?? ''),
            version: (int) ($data['page_version'] ?? 0),
            hits: (int) ($data['page_hits'] ?? 0),
            versionCreated: (int) ($data['version_created'] ?? 0),
            changeAuthor: $data['change_author'] ?? null,
            changeLog: $data['change_log'] ?? null,
            changeIdentityId: $data['change_identity_id'] ?? null,
        );
    }
}

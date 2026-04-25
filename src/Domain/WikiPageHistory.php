<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

final readonly class WikiPageHistory
{
    public function __construct(
        public int $id,
        public string $uid,
        public string $name,
        public string $text,
        public int $version,
        public int $versionCreated,
        public ?string $changeAuthor,
        public ?string $changeLog,
        public ?string $changeIdentityId = null,
        public ?string $pageFormat = null,
    ) {}

    /**
     * Create from a driver array as returned by retrieveHistory().
     *
     * Expected keys: page_id, page_uid, page_name, page_text, page_version,
     *                version_created, change_author, change_log,
     *                change_identity_id, page_format
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['page_id'],
            uid: (string) ($data['page_uid'] ?? ''),
            name: (string) ($data['page_name'] ?? ''),
            text: (string) ($data['page_text'] ?? ''),
            version: (int) ($data['page_version'] ?? 0),
            versionCreated: (int) ($data['version_created'] ?? 0),
            changeAuthor: $data['change_author'] ?? null,
            changeLog: $data['change_log'] ?? null,
            changeIdentityId: $data['change_identity_id'] ?? null,
            pageFormat: $data['page_format'] ?? null,
        );
    }

    public function isOld(): bool
    {
        return true;
    }
}

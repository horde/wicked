<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

interface AttachmentRepositoryInterface
{
    /**
     * @return list<Attachment>
     */
    public function getAttachedFiles(int $pageId, bool $allVersions = false): array;

    /**
     * @return list<Attachment>
     */
    public function getAllAttachments(): array;

    public function attachFile(array $file, string $data): int;

    public function removeAttachment(int $pageId, string $attachment, ?int $version = null): void;

    public function removeAllAttachments(int $pageId): void;

    public function getAttachmentContents(int $pageId, string $filename, int $version): string;

    public function logAttachmentDownload(int $pageId, string $attachment): void;
}

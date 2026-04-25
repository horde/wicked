<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain\Repository;

use Horde\Wicked\Domain\Attachment;
use Horde\Wicked\Domain\AttachmentRepositoryInterface;
use Wicked_Driver;

final class DriverAttachmentRepository implements AttachmentRepositoryInterface
{
    public function __construct(
        private readonly Wicked_Driver $driver,
    ) {}

    /**
     * @return list<Attachment>
     */
    public function getAttachedFiles(int $pageId, bool $allVersions = false): array
    {
        return array_map(
            Attachment::fromDriverArray(...),
            $this->driver->getAttachedFiles($pageId, $allVersions),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function getAllAttachments(): array
    {
        return array_map(
            Attachment::fromDriverArray(...),
            $this->driver->getAllAttachments(),
        );
    }

    public function attachFile(array $file, string $data): int
    {
        $this->driver->attachFile($file, $data);

        $attachments = $this->driver->getAttachedFiles(
            (int) $file['page_id'],
            false,
        );

        foreach ($attachments as $att) {
            if ($att['attachment_name'] === $file['attachment_name']) {
                return (int) $att['attachment_version'];
            }
        }

        return 1;
    }

    public function removeAttachment(int $pageId, string $attachment, ?int $version = null): void
    {
        $this->driver->removeAttachment($pageId, $attachment, $version);
    }

    public function removeAllAttachments(int $pageId): void
    {
        $this->driver->removeAllAttachments($pageId);
    }

    public function getAttachmentContents(int $pageId, string $filename, int $version): string
    {
        return $this->driver->getAttachmentContents($pageId, $filename, $version);
    }

    public function logAttachmentDownload(int $pageId, string $attachment): void
    {
        $this->driver->logAttachmentDownload($pageId, $attachment);
    }
}

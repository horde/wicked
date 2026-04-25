<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain\Repository;

use Horde\Wicked\Domain\Attachment;
use Horde\Wicked\Domain\Repository\DriverAttachmentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wicked_Driver;
use Wicked_Driver_Sql;

#[CoversClass(DriverAttachmentRepository::class)]
class DriverAttachmentRepositoryTest extends TestCase
{
    private function mockDriver(): Wicked_Driver
    {
        return $this->createMock(Wicked_Driver_Sql::class);
    }

    private function sampleAttachmentRow(array $overrides = []): array
    {
        return array_merge([
            'page_id' => 10,
            'attachment_name' => 'readme.pdf',
            'attachment_version' => 2,
            'attachment_created' => 1700000000,
            'attachment_hits' => 50,
            'change_author' => 'uploader',
            'change_log' => 'Updated document',
            'change_identity_id' => 'id-uuid-789',
        ], $overrides);
    }

    public function testGetAttachedFilesReturnsMappedArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getAttachedFiles')
            ->with(10, false)
            ->willReturn([
                $this->sampleAttachmentRow(['attachment_name' => 'a.pdf']),
                $this->sampleAttachmentRow(['attachment_name' => 'b.pdf']),
            ]);

        $repo = new DriverAttachmentRepository($driver);
        $attachments = $repo->getAttachedFiles(10);

        $this->assertCount(2, $attachments);
        $this->assertInstanceOf(Attachment::class, $attachments[0]);
        $this->assertInstanceOf(Attachment::class, $attachments[1]);
        $this->assertSame('a.pdf', $attachments[0]->name);
        $this->assertSame('b.pdf', $attachments[1]->name);
    }

    public function testGetAttachedFilesPassesAllVersions(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getAttachedFiles')
            ->with(10, true)
            ->willReturn([$this->sampleAttachmentRow()]);

        $repo = new DriverAttachmentRepository($driver);
        $attachments = $repo->getAttachedFiles(10, true);

        $this->assertCount(1, $attachments);
        $this->assertInstanceOf(Attachment::class, $attachments[0]);
    }

    public function testGetAllAttachmentsReturnsMappedArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getAllAttachments')
            ->willReturn([
                $this->sampleAttachmentRow(),
                $this->sampleAttachmentRow(['attachment_name' => 'other.txt']),
            ]);

        $repo = new DriverAttachmentRepository($driver);
        $attachments = $repo->getAllAttachments();

        $this->assertCount(2, $attachments);
        $this->assertInstanceOf(Attachment::class, $attachments[0]);
        $this->assertInstanceOf(Attachment::class, $attachments[1]);
    }

    public function testRemoveAttachmentDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('removeAttachment')
            ->with(10, 'file.pdf', 2);

        $repo = new DriverAttachmentRepository($driver);
        $repo->removeAttachment(10, 'file.pdf', 2);
    }

    public function testRemoveAttachmentWithNullVersion(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('removeAttachment')
            ->with(10, 'file.pdf', null);

        $repo = new DriverAttachmentRepository($driver);
        $repo->removeAttachment(10, 'file.pdf');
    }

    public function testRemoveAllAttachmentsDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('removeAllAttachments')
            ->with(10);

        $repo = new DriverAttachmentRepository($driver);
        $repo->removeAllAttachments(10);
    }

    public function testGetAttachmentContentsDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getAttachmentContents')
            ->with(10, 'readme.pdf', 2)
            ->willReturn('file contents here');

        $repo = new DriverAttachmentRepository($driver);

        $this->assertSame('file contents here', $repo->getAttachmentContents(10, 'readme.pdf', 2));
    }

    public function testLogAttachmentDownloadDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('logAttachmentDownload')
            ->with(10, 'readme.pdf');

        $repo = new DriverAttachmentRepository($driver);
        $repo->logAttachmentDownload(10, 'readme.pdf');
    }
}

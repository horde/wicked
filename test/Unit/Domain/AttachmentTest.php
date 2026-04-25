<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\Attachment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Attachment::class)]
class AttachmentTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $attachment = Attachment::fromDriverArray([
            'page_id' => 10,
            'attachment_name' => 'readme.pdf',
            'attachment_version' => 2,
            'attachment_created' => 1700000000,
            'attachment_hits' => 50,
            'change_author' => 'uploader',
            'change_log' => 'Updated document',
            'change_identity_id' => 'id-uuid-789',
        ]);

        $this->assertSame(10, $attachment->pageId);
        $this->assertSame('readme.pdf', $attachment->name);
        $this->assertSame(2, $attachment->version);
        $this->assertSame(1700000000, $attachment->created);
        $this->assertSame(50, $attachment->hits);
        $this->assertSame('uploader', $attachment->changeAuthor);
        $this->assertSame('Updated document', $attachment->changeLog);
        $this->assertSame('id-uuid-789', $attachment->changeIdentityId);
    }

    public function testFromDriverArrayWithMissingOptionalFields(): void
    {
        $attachment = Attachment::fromDriverArray([
            'page_id' => 1,
        ]);

        $this->assertSame(1, $attachment->pageId);
        $this->assertSame('', $attachment->name);
        $this->assertSame(0, $attachment->version);
        $this->assertSame(0, $attachment->created);
        $this->assertSame(0, $attachment->hits);
        $this->assertNull($attachment->changeAuthor);
        $this->assertNull($attachment->changeLog);
        $this->assertNull($attachment->changeIdentityId);
    }

    public function testChangeIdentityIdProperty(): void
    {
        $attachment = Attachment::fromDriverArray([
            'page_id' => 5,
            'change_identity_id' => 'identity-uuid-abc',
        ]);

        $this->assertSame('identity-uuid-abc', $attachment->changeIdentityId);
    }
}

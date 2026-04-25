<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\WikiPageHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WikiPageHistory::class)]
class WikiPageHistoryTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $history = WikiPageHistory::fromDriverArray([
            'page_id' => 42,
            'page_uid' => 'abc-123-def',
            'page_name' => 'TestPage',
            'page_text' => 'Old content',
            'page_version' => 2,
            'version_created' => 1699000000,
            'change_author' => 'editor',
            'change_log' => 'Fixed typo',
            'change_identity_id' => 'id-uuid-456',
        ]);

        $this->assertSame(42, $history->id);
        $this->assertSame('abc-123-def', $history->uid);
        $this->assertSame('TestPage', $history->name);
        $this->assertSame('Old content', $history->text);
        $this->assertSame(2, $history->version);
        $this->assertSame(1699000000, $history->versionCreated);
        $this->assertSame('editor', $history->changeAuthor);
        $this->assertSame('Fixed typo', $history->changeLog);
        $this->assertSame('id-uuid-456', $history->changeIdentityId);
    }

    public function testFromDriverArrayWithMissingOptionalFields(): void
    {
        $history = WikiPageHistory::fromDriverArray([
            'page_id' => 1,
        ]);

        $this->assertSame(1, $history->id);
        $this->assertSame('', $history->uid);
        $this->assertSame('', $history->name);
        $this->assertSame('', $history->text);
        $this->assertSame(0, $history->version);
        $this->assertSame(0, $history->versionCreated);
        $this->assertNull($history->changeAuthor);
        $this->assertNull($history->changeLog);
        $this->assertNull($history->changeIdentityId);
    }

    public function testIsOldReturnsTrue(): void
    {
        $history = WikiPageHistory::fromDriverArray([
            'page_id' => 1,
        ]);

        $this->assertTrue($history->isOld());
    }
}

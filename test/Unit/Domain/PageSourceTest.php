<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\PageSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageSource::class)]
class PageSourceTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $source = PageSource::fromDriverArray([
            'page_uid' => 'abc-123',
            'source_type' => 'github',
            'repository' => 'horde/wicked',
            'file_path' => 'doc/README.md',
            'ref' => 'main',
            'last_synced_at' => '2026-04-20 10:00:00',
            'last_commit_sha' => 'deadbeef123',
        ]);

        $this->assertSame('abc-123', $source->pageUid);
        $this->assertSame('github', $source->sourceType);
        $this->assertSame('horde/wicked', $source->repository);
        $this->assertSame('doc/README.md', $source->filePath);
        $this->assertSame('main', $source->ref);
        $this->assertSame('2026-04-20 10:00:00', $source->lastSyncedAt);
        $this->assertSame('deadbeef123', $source->lastCommitSha);
    }

    public function testFromDriverArrayDefaultsRefToMain(): void
    {
        $source = PageSource::fromDriverArray([
            'page_uid' => 'uid-1',
            'source_type' => 'github',
            'repository' => 'owner/repo',
            'file_path' => 'path.md',
        ]);

        $this->assertSame('main', $source->ref);
    }

    public function testFromDriverArrayNullableSyncFields(): void
    {
        $source = PageSource::fromDriverArray([
            'page_uid' => 'uid-2',
            'source_type' => 'github',
            'repository' => 'owner/repo',
            'file_path' => 'path.md',
            'ref' => 'develop',
        ]);

        $this->assertNull($source->lastSyncedAt);
        $this->assertNull($source->lastCommitSha);
    }
}

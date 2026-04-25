<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain\Repository;

use Horde\Wicked\Domain\Repository\DriverPageRepository;
use Horde\Wicked\Domain\WikiPage;
use Horde\Wicked\Domain\WikiPageHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wicked_Driver;
use Wicked_Driver_Sql;
use Wicked_Exception;

#[CoversClass(DriverPageRepository::class)]
class DriverPageRepositoryTest extends TestCase
{
    private function mockDriver(): Wicked_Driver
    {
        return $this->createMock(Wicked_Driver_Sql::class);
    }

    private function samplePageRow(array $overrides = []): array
    {
        return array_merge([
            'page_id' => 42,
            'page_uid' => 'uid-abc-123',
            'page_name' => 'TestPage',
            'page_text' => 'Hello world',
            'page_version' => 3,
            'page_hits' => 100,
            'version_created' => 1700000000,
            'change_author' => 'admin',
            'change_log' => 'Initial revision',
            'change_identity_id' => 'id-uuid-456',
        ], $overrides);
    }

    private function sampleHistoryRow(array $overrides = []): array
    {
        return array_merge([
            'page_id' => 42,
            'page_uid' => 'uid-abc-123',
            'page_name' => 'TestPage',
            'page_text' => 'Old content',
            'page_version' => 2,
            'version_created' => 1699000000,
            'change_author' => 'editor',
            'change_log' => 'Fixed typo',
            'change_identity_id' => 'id-uuid-456',
        ], $overrides);
    }

    public function testGetByNameReturnsWikiPage(): void
    {
        $driver = $this->mockDriver();
        $driver->method('retrieveByName')
            ->with('TestPage')
            ->willReturn($this->samplePageRow());

        $repo = new DriverPageRepository($driver);
        $page = $repo->getByName('TestPage');

        $this->assertInstanceOf(WikiPage::class, $page);
        $this->assertSame(42, $page->id);
        $this->assertSame('uid-abc-123', $page->uid);
        $this->assertSame('TestPage', $page->name);
        $this->assertSame('Hello world', $page->text);
        $this->assertSame(3, $page->version);
        $this->assertSame(100, $page->hits);
    }

    public function testGetByIdReturnsWikiPage(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getPageById')
            ->with(42)
            ->willReturn([0 => $this->samplePageRow()]);

        $repo = new DriverPageRepository($driver);
        $page = $repo->getById(42);

        $this->assertInstanceOf(WikiPage::class, $page);
        $this->assertSame(42, $page->id);
    }

    public function testGetByIdThrowsWhenNotFound(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getPageById')->willReturn([]);

        $repo = new DriverPageRepository($driver);

        $this->expectException(Wicked_Exception::class);
        $repo->getById(999);
    }

    public function testGetByUidReturnsWikiPage(): void
    {
        $driver = $this->mockDriver();
        $driver->method('retrieveByUid')
            ->with('uid-abc-123')
            ->willReturn($this->samplePageRow());

        $repo = new DriverPageRepository($driver);
        $page = $repo->getByUid('uid-abc-123');

        $this->assertInstanceOf(WikiPage::class, $page);
        $this->assertSame('uid-abc-123', $page->uid);
    }

    public function testGetHistoryVersionReturnsWikiPageHistory(): void
    {
        $driver = $this->mockDriver();
        $driver->method('retrieveHistory')
            ->with('TestPage', '2')
            ->willReturn([0 => $this->sampleHistoryRow()]);

        $repo = new DriverPageRepository($driver);
        $history = $repo->getHistoryVersion('TestPage', 2);

        $this->assertInstanceOf(WikiPageHistory::class, $history);
        $this->assertSame(2, $history->version);
        $this->assertSame('Old content', $history->text);
    }

    public function testGetHistoryVersionThrowsWhenNotFound(): void
    {
        $driver = $this->mockDriver();
        $driver->method('retrieveHistory')->willReturn([]);

        $repo = new DriverPageRepository($driver);

        $this->expectException(Wicked_Exception::class);
        $repo->getHistoryVersion('NoSuchPage', 1);
    }

    public function testGetHistoryReturnsMappedArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getHistory')->willReturn([
            $this->sampleHistoryRow(['page_version' => 1]),
            $this->sampleHistoryRow(['page_version' => 2]),
        ]);

        $repo = new DriverPageRepository($driver);
        $history = $repo->getHistory('TestPage');

        $this->assertCount(2, $history);
        $this->assertInstanceOf(WikiPageHistory::class, $history[0]);
        $this->assertInstanceOf(WikiPageHistory::class, $history[1]);
        $this->assertSame(1, $history[0]->version);
        $this->assertSame(2, $history[1]->version);
    }

    public function testGetAllPagesReturnsMappedArray(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getAllPages')->willReturn([
            $this->samplePageRow(['page_id' => 1, 'page_name' => 'PageOne']),
            $this->samplePageRow(['page_id' => 2, 'page_name' => 'PageTwo']),
        ]);

        $repo = new DriverPageRepository($driver);
        $pages = $repo->getAllPages();

        $this->assertCount(2, $pages);
        $this->assertInstanceOf(WikiPage::class, $pages[0]);
        $this->assertInstanceOf(WikiPage::class, $pages[1]);
        $this->assertSame('PageOne', $pages[0]->name);
        $this->assertSame('PageTwo', $pages[1]->name);
    }

    public function testGetRecentChangesPassesDays(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getRecentChanges')
            ->with(5)
            ->willReturn([$this->samplePageRow()]);

        $repo = new DriverPageRepository($driver);
        $pages = $repo->getRecentChanges(5);

        $this->assertCount(1, $pages);
        $this->assertInstanceOf(WikiPage::class, $pages[0]);
    }

    public function testGetMostPopularCallsMostPopular(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('mostPopular')
            ->with(5)
            ->willReturn([$this->samplePageRow()]);

        $repo = new DriverPageRepository($driver);
        $pages = $repo->getMostPopular(5);

        $this->assertCount(1, $pages);
        $this->assertInstanceOf(WikiPage::class, $pages[0]);
    }

    public function testGetLeastPopularCallsLeastPopular(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('leastPopular')
            ->with(3)
            ->willReturn([$this->samplePageRow()]);

        $repo = new DriverPageRepository($driver);
        $pages = $repo->getLeastPopular(3);

        $this->assertCount(1, $pages);
        $this->assertInstanceOf(WikiPage::class, $pages[0]);
    }

    public function testPageExistsDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->method('pageExists')
            ->with('TestPage')
            ->willReturn(true);

        $repo = new DriverPageRepository($driver);

        $this->assertTrue($repo->pageExists('TestPage'));
    }

    public function testGetPageIdDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->method('getPageId')
            ->with('TestPage')
            ->willReturn(42);

        $repo = new DriverPageRepository($driver);

        $this->assertSame(42, $repo->getPageId('TestPage'));
    }

    public function testCreatePageDelegatesToNewPage(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('newPage')
            ->with('NewPage', 'Content here')
            ->willReturn(99);

        $repo = new DriverPageRepository($driver);

        $this->assertSame(99, $repo->createPage('NewPage', 'Content here'));
    }

    public function testUpdateTextDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('updateText')
            ->with('TestPage', 'Updated text', 'changelog entry');

        $repo = new DriverPageRepository($driver);
        $repo->updateText('TestPage', 'Updated text', 'changelog entry');
    }

    public function testRenamePageDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('renamePage')
            ->with('OldName', 'NewName');

        $repo = new DriverPageRepository($driver);
        $repo->renamePage('OldName', 'NewName');
    }

    public function testRemoveVersionDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('removeVersion')
            ->with('TestPage', 2);

        $repo = new DriverPageRepository($driver);
        $repo->removeVersion('TestPage', 2);
    }

    public function testRemoveAllVersionsDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('removeAllVersions')
            ->with('TestPage');

        $repo = new DriverPageRepository($driver);
        $repo->removeAllVersions('TestPage');
    }

    public function testLogPageViewDelegatesToDriver(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('logPageView')
            ->with('TestPage');

        $repo = new DriverPageRepository($driver);
        $repo->logPageView('TestPage');
    }
}

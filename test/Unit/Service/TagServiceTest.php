<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Service;

use Horde\Wicked\Service\TagService;
use Horde\Wicked\Tagger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TagService::class)]
class TagServiceTest extends TestCase
{
    private function mockTagger(): Tagger
    {
        return $this->createMock(Tagger::class);
    }

    // --- Null tagger (graceful degradation) ---

    public function testIsAvailableWithNullTagger(): void
    {
        $service = new TagService(null);

        $this->assertFalse($service->isAvailable());
    }

    public function testGetTagsWithNullTagger(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->getTags('some-uid'));
    }

    public function testGetTagsWithEmptyUid(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->getTags(''));
    }

    public function testReplaceTagsWithNullTaggerIsNoop(): void
    {
        $service = new TagService(null);

        $service->replaceTags('some-uid', ['tag1'], 'owner');

        $this->assertTrue(true);
    }

    public function testSearchWithNullTagger(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->search(['tag1', 'tag2']));
    }

    public function testSearchWithEmptyTags(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->search([]));
    }

    public function testTagWithNullTaggerIsNoop(): void
    {
        $service = new TagService(null);

        $service->tag('some-uid', ['tag1'], 'owner');

        $this->assertTrue(true);
    }

    public function testTagWithEmptyTagsIsNoop(): void
    {
        $service = new TagService(null);

        $service->tag('some-uid', [], 'owner');

        $this->assertTrue(true);
    }

    public function testGetTagsByPagesWithNullTagger(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->getTagsByPages(['uid1', 'uid2']));
    }

    public function testGetCloudWithNullTagger(): void
    {
        $service = new TagService(null);

        $this->assertSame([], $service->getCloud());
    }

    // --- Tagger available (delegation & exception handling) ---

    public function testIsAvailableWithTagger(): void
    {
        $service = new TagService($this->mockTagger());

        $this->assertTrue($service->isAvailable());
    }

    public function testTagDelegatesToTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('tag')
            ->with('page-uid-123', ['php', 'wiki'], 'admin');

        $service = new TagService($tagger);
        $service->tag('page-uid-123', ['php', 'wiki'], 'admin');
    }

    public function testTagWithEmptyTagsSkipsTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->never())->method('tag');

        $service = new TagService($tagger);
        $service->tag('page-uid-123', [], 'admin');
    }

    public function testGetTagsDelegatesToTagger(): void
    {
        $expected = [1 => 'php', 2 => 'wiki'];

        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('getTags')
            ->with('page-uid-123', 'page')
            ->willReturn($expected);

        $service = new TagService($tagger);

        $this->assertSame($expected, $service->getTags('page-uid-123'));
    }

    public function testGetTagsWithEmptyUidSkipsTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->never())->method('getTags');

        $service = new TagService($tagger);

        $this->assertSame([], $service->getTags(''));
    }

    public function testGetTagsReturnsEmptyOnException(): void
    {
        $tagger = $this->mockTagger();
        $tagger->method('getTags')
            ->willThrowException(new RuntimeException('Connection lost'));

        $service = new TagService($tagger);

        $this->assertSame([], $service->getTags('page-uid-123'));
    }

    public function testReplaceTagsDelegatesToTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('replaceTags')
            ->with('page-uid-123', ['php', 'docs'], 'admin', 'page');

        $service = new TagService($tagger);
        $service->replaceTags('page-uid-123', ['php', 'docs'], 'admin');
    }

    public function testReplaceTagsWithEmptyUidSkipsTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->never())->method('replaceTags');

        $service = new TagService($tagger);
        $service->replaceTags('', ['php'], 'admin');
    }

    public function testReplaceTagsSwallowsException(): void
    {
        $tagger = $this->mockTagger();
        $tagger->method('replaceTags')
            ->willThrowException(new RuntimeException('DB error'));

        $service = new TagService($tagger);
        $service->replaceTags('page-uid-123', ['php'], 'admin');

        $this->assertTrue(true);
    }

    public function testGetTagsByPagesDelegatesToTagger(): void
    {
        $expected = ['uid1' => [1 => 'php'], 'uid2' => [2 => 'wiki']];

        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('getTags')
            ->with(['uid1', 'uid2'], 'page')
            ->willReturn($expected);

        $service = new TagService($tagger);

        $this->assertSame($expected, $service->getTagsByPages(['uid1', 'uid2']));
    }

    public function testGetTagsByPagesWithEmptyArraySkipsTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->never())->method('getTags');

        $service = new TagService($tagger);

        $this->assertSame([], $service->getTagsByPages([]));
    }

    public function testGetTagsByPagesReturnsEmptyOnException(): void
    {
        $tagger = $this->mockTagger();
        $tagger->method('getTags')
            ->willThrowException(new RuntimeException('Timeout'));

        $service = new TagService($tagger);

        $this->assertSame([], $service->getTagsByPages(['uid1']));
    }

    public function testSearchDelegatesToTagger(): void
    {
        $expected = ['uid-1', 'uid-2'];

        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('search')
            ->with(['php', 'wiki'])
            ->willReturn($expected);

        $service = new TagService($tagger);

        $this->assertSame($expected, $service->search(['php', 'wiki']));
    }

    public function testSearchWithEmptyTagsSkipsTagger(): void
    {
        $tagger = $this->mockTagger();
        $tagger->expects($this->never())->method('search');

        $service = new TagService($tagger);

        $this->assertSame([], $service->search([]));
    }

    public function testSearchReturnsEmptyOnException(): void
    {
        $tagger = $this->mockTagger();
        $tagger->method('search')
            ->willThrowException(new RuntimeException('Not found'));

        $service = new TagService($tagger);

        $this->assertSame([], $service->search(['php']));
    }

    public function testGetCloudDelegatesToTagger(): void
    {
        $expected = [
            ['tag_id' => 1, 'tag_name' => 'php', 'count' => 10],
            ['tag_id' => 2, 'tag_name' => 'wiki', 'count' => 5],
        ];

        $tagger = $this->mockTagger();
        $tagger->expects($this->once())
            ->method('getCloud')
            ->with(null, 15)
            ->willReturn($expected);

        $service = new TagService($tagger);

        $this->assertSame($expected, $service->getCloud(15));
    }

    public function testGetCloudReturnsEmptyOnException(): void
    {
        $tagger = $this->mockTagger();
        $tagger->method('getCloud')
            ->willThrowException(new RuntimeException('DB gone'));

        $service = new TagService($tagger);

        $this->assertSame([], $service->getCloud());
    }
}

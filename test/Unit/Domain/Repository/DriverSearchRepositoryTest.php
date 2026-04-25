<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain\Repository;

use Horde\Wicked\Domain\PageMatchType;
use Horde\Wicked\Domain\Repository\DriverSearchRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wicked_Driver;
use Wicked_Driver_Sql;

#[CoversClass(DriverSearchRepository::class)]
class DriverSearchRepositoryTest extends TestCase
{
    private function mockDriver(): Wicked_Driver
    {
        return $this->createMock(Wicked_Driver_Sql::class);
    }

    public function testSearchTitlesDelegatesToDriver(): void
    {
        $expected = [['page_name' => 'TestPage']];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('searchTitles')
            ->with('Test', true)
            ->willReturn($expected);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame($expected, $repo->searchTitles('Test', true));
    }

    public function testSearchTextDelegatesToDriver(): void
    {
        $expected = [['page_name' => 'TestPage', 'page_text' => 'matched']];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('searchText')
            ->with('matched')
            ->willReturn($expected);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame($expected, $repo->searchText('matched'));
    }

    public function testGetBackLinksDelegatesToDriver(): void
    {
        $expected = [['page_name' => 'LinkingPage']];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getBackLinks')
            ->with('TargetPage')
            ->willReturn($expected);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame($expected, $repo->getBackLinks('TargetPage'));
    }

    public function testGetLikePagesDelegatesToDriver(): void
    {
        $expected = [['page_name' => 'SimilarPage']];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getLikePages')
            ->with('TestPage')
            ->willReturn($expected);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame($expected, $repo->getLikePages('TestPage'));
    }

    public function testGetMatchingPagesConvertsEnumToInt(): void
    {
        $expected = [['page_name' => 'LeftMatch']];

        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getMatchingPages')
            ->with('test', 1)
            ->willReturn($expected);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame($expected, $repo->getMatchingPages('test', PageMatchType::Left));
    }

    public function testGetMatchingPagesDefaultsToAny(): void
    {
        $driver = $this->mockDriver();
        $driver->expects($this->once())
            ->method('getMatchingPages')
            ->with('test', 4)
            ->willReturn([]);

        $repo = new DriverSearchRepository($driver);

        $this->assertSame([], $repo->getMatchingPages('test'));
    }
}

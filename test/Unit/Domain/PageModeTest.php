<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\PageMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(PageMode::class)]
class PageModeTest extends TestCase
{
    public function testFromInt(): void
    {
        $this->assertSame(PageMode::Display, PageMode::from(0));
        $this->assertSame(PageMode::Edit, PageMode::from(1));
        $this->assertSame(PageMode::Remove, PageMode::from(2));
        $this->assertSame(PageMode::History, PageMode::from(3));
        $this->assertSame(PageMode::Diff, PageMode::from(4));
        $this->assertSame(PageMode::Locking, PageMode::from(7));
        $this->assertSame(PageMode::Unlocking, PageMode::from(8));
        $this->assertSame(PageMode::Create, PageMode::from(9));
        $this->assertSame(PageMode::Content, PageMode::from(10));
        $this->assertSame(PageMode::Block, PageMode::from(11));
    }

    public function testInvalidValueThrows(): void
    {
        $this->expectException(ValueError::class);
        PageMode::from(99);
    }
}

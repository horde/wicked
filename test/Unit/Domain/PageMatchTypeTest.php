<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\PageMatchType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(PageMatchType::class)]
class PageMatchTypeTest extends TestCase
{
    public function testFromInt(): void
    {
        $this->assertSame(PageMatchType::Left, PageMatchType::from(1));
        $this->assertSame(PageMatchType::Right, PageMatchType::from(2));
        $this->assertSame(PageMatchType::Ends, PageMatchType::from(3));
        $this->assertSame(PageMatchType::Any, PageMatchType::from(4));
    }

    public function testInvalidValueThrows(): void
    {
        $this->expectException(ValueError::class);
        PageMatchType::from(99);
    }
}

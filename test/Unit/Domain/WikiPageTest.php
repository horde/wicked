<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Domain;

use Horde\Wicked\Domain\WikiPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WikiPage::class)]
class WikiPageTest extends TestCase
{
    public function testFromDriverArray(): void
    {
        $page = WikiPage::fromDriverArray([
            'page_id' => 42,
            'page_uid' => 'abc-123-def',
            'page_name' => 'TestPage',
            'page_text' => 'Hello world',
            'page_version' => 3,
            'page_hits' => 100,
            'version_created' => 1700000000,
            'change_author' => 'admin',
            'change_log' => 'Initial revision',
            'change_identity_id' => 'id-uuid-456',
        ]);

        $this->assertSame(42, $page->id);
        $this->assertSame('abc-123-def', $page->uid);
        $this->assertSame('TestPage', $page->name);
        $this->assertSame('Hello world', $page->text);
        $this->assertSame(3, $page->version);
        $this->assertSame(100, $page->hits);
        $this->assertSame(1700000000, $page->versionCreated);
        $this->assertSame('admin', $page->changeAuthor);
        $this->assertSame('Initial revision', $page->changeLog);
        $this->assertSame('id-uuid-456', $page->changeIdentityId);
    }

    public function testFromDriverArrayWithMissingOptionalFields(): void
    {
        $page = WikiPage::fromDriverArray([
            'page_id' => 1,
        ]);

        $this->assertSame(1, $page->id);
        $this->assertSame('', $page->uid);
        $this->assertSame('', $page->name);
        $this->assertSame('', $page->text);
        $this->assertSame(0, $page->version);
        $this->assertSame(0, $page->hits);
        $this->assertSame(0, $page->versionCreated);
        $this->assertNull($page->changeAuthor);
        $this->assertNull($page->changeLog);
        $this->assertNull($page->changeIdentityId);
    }

    public function testUidProperty(): void
    {
        $page = WikiPage::fromDriverArray([
            'page_id' => 5,
            'page_uid' => 'unique-page-identifier',
        ]);

        $this->assertSame('unique-page-identifier', $page->uid);
    }

    public function testChangeIdentityIdProperty(): void
    {
        $page = WikiPage::fromDriverArray([
            'page_id' => 5,
            'change_identity_id' => 'identity-uuid-789',
        ]);

        $this->assertSame('identity-uuid-789', $page->changeIdentityId);
    }
}

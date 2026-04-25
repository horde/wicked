<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Listener;

use Horde\Satisfiend\Event\WebhookReceivedEvent;
use Horde\Wicked\Domain\PageRepositoryInterface;
use Horde\Wicked\Domain\PageSource;
use Horde\Wicked\Domain\PageSourceRepositoryInterface;
use Horde\Wicked\Domain\WikiPage;
use Horde\Wicked\Listener\GitHubSyncListener;
use Horde\Wicked\Service\GitHubFileFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitHubSyncListener::class)]
class GitHubSyncListenerTest extends TestCase
{
    private PageSourceRepositoryInterface $sourceRepo;
    private PageRepositoryInterface $pageRepo;
    private GitHubFileFetcher $fetcher;
    private GitHubSyncListener $listener;

    protected function setUp(): void
    {
        $this->sourceRepo = $this->createMock(PageSourceRepositoryInterface::class);
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->fetcher = $this->createMock(GitHubFileFetcher::class);

        $this->listener = new GitHubSyncListener(
            $this->sourceRepo,
            $this->pageRepo,
            $this->fetcher,
        );
    }

    public function testIgnoresNonGitHubEvents(): void
    {
        $event = $this->makeEvent(providerType: 'gitea', eventType: 'push');

        $this->sourceRepo->expects($this->never())->method('findByRepository');

        ($this->listener)($event);
    }

    public function testIgnoresNonSyncableEventTypes(): void
    {
        $event = $this->makeEvent(eventType: 'issues', action: 'opened');

        $this->sourceRepo->expects($this->never())->method('findByRepository');

        ($this->listener)($event);
    }

    public function testIgnoresUnmergedPullRequest(): void
    {
        $payload = json_encode([
            'pull_request' => ['merged' => false],
        ]);
        $event = $this->makeEvent(
            eventType: 'pull_request',
            action: 'closed',
            payload: $payload,
        );

        $this->sourceRepo->expects($this->never())->method('findByRepository');

        ($this->listener)($event);
    }

    public function testSkipsWhenNoSourcesTrackRepo(): void
    {
        $event = $this->makeEvent(eventType: 'push');

        $this->sourceRepo->method('findByRepository')
            ->with('owner/repo')
            ->willReturn([]);

        $this->fetcher->expects($this->never())->method('fetch');

        ($this->listener)($event);
    }

    public function testPushSyncsWhenFileChanged(): void
    {
        $payload = json_encode([
            'commits' => [
                ['added' => [], 'modified' => ['doc/README.md']],
            ],
            'head_commit' => ['id' => 'abc123'],
        ]);
        $event = $this->makeEvent(eventType: 'push', payload: $payload);

        $source = new PageSource(
            pageUid: 'uid-1',
            sourceType: 'github',
            repository: 'owner/repo',
            filePath: 'doc/README.md',
            ref: 'main',
        );

        $page = WikiPage::fromDriverArray([
            'page_id' => 1,
            'page_uid' => 'uid-1',
            'page_name' => 'MyPage',
        ]);

        $this->sourceRepo->method('findByRepository')->willReturn([$source]);
        $this->pageRepo->method('getByUid')->with('uid-1')->willReturn($page);
        $this->fetcher->method('fetch')
            ->with('owner/repo', 'doc/README.md', 'main')
            ->willReturn('# Updated content');

        $this->pageRepo->expects($this->once())
            ->method('updateText')
            ->with('MyPage', '# Updated content', $this->stringContains('Synced from'));

        $this->sourceRepo->expects($this->once())
            ->method('updateSyncState')
            ->with('uid-1', 'abc123');

        ($this->listener)($event);
    }

    public function testPushSkipsWhenFileNotChanged(): void
    {
        $payload = json_encode([
            'commits' => [
                ['added' => ['other.md'], 'modified' => []],
            ],
            'head_commit' => ['id' => 'abc123'],
        ]);
        $event = $this->makeEvent(eventType: 'push', payload: $payload);

        $source = new PageSource(
            pageUid: 'uid-1',
            sourceType: 'github',
            repository: 'owner/repo',
            filePath: 'doc/README.md',
            ref: 'main',
        );

        $this->sourceRepo->method('findByRepository')->willReturn([$source]);
        $this->fetcher->expects($this->never())->method('fetch');

        ($this->listener)($event);
    }

    public function testMergedPullRequestSyncs(): void
    {
        $payload = json_encode([
            'pull_request' => [
                'merged' => true,
                'merge_commit_sha' => 'merge123',
            ],
        ]);
        $event = $this->makeEvent(
            eventType: 'pull_request',
            action: 'closed',
            payload: $payload,
        );

        $source = new PageSource(
            pageUid: 'uid-2',
            sourceType: 'github',
            repository: 'owner/repo',
            filePath: 'docs/guide.md',
            ref: 'main',
        );

        $page = WikiPage::fromDriverArray([
            'page_id' => 2,
            'page_uid' => 'uid-2',
            'page_name' => 'Guide',
        ]);

        $this->sourceRepo->method('findByRepository')->willReturn([$source]);
        $this->pageRepo->method('getByUid')->with('uid-2')->willReturn($page);
        $this->fetcher->method('fetch')->willReturn('# Guide content');

        $this->pageRepo->expects($this->once())
            ->method('updateText')
            ->with('Guide', '# Guide content', $this->stringContains('Synced from'));

        $this->sourceRepo->expects($this->once())
            ->method('updateSyncState')
            ->with('uid-2', 'merge123');

        ($this->listener)($event);
    }

    public function testSkipsWhenFetchReturnsNull(): void
    {
        $payload = json_encode([
            'commits' => [
                ['added' => ['doc/README.md'], 'modified' => []],
            ],
            'head_commit' => ['id' => 'abc123'],
        ]);
        $event = $this->makeEvent(eventType: 'push', payload: $payload);

        $source = new PageSource(
            pageUid: 'uid-1',
            sourceType: 'github',
            repository: 'owner/repo',
            filePath: 'doc/README.md',
            ref: 'main',
        );

        $this->sourceRepo->method('findByRepository')->willReturn([$source]);
        $this->fetcher->method('fetch')->willReturn(null);

        $this->pageRepo->expects($this->never())->method('updateText');

        ($this->listener)($event);
    }

    private function makeEvent(
        string $providerType = 'github',
        string $eventType = 'push',
        string $action = '',
        string $repository = 'owner/repo',
        string $payload = '{}',
    ): WebhookReceivedEvent {
        return new WebhookReceivedEvent(
            slug: 'test',
            providerType: $providerType,
            eventType: $eventType,
            action: $action,
            repository: $repository,
            actor: 'user',
            nodeId: 'node-1',
            deliveryId: 'delivery-1',
            payload: $payload,
        );
    }
}

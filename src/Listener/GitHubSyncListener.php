<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Listener;

use Horde\Satisfiend\Event\WebhookReceivedEvent;
use Horde\Wicked\Domain\PageRepositoryInterface;
use Horde\Wicked\Domain\PageSourceRepositoryInterface;
use Horde\Wicked\Service\GitHubFileFetcher;
use Throwable;

final class GitHubSyncListener
{
    public function __construct(
        private readonly PageSourceRepositoryInterface $sourceRepo,
        private readonly PageRepositoryInterface $pageRepo,
        private readonly GitHubFileFetcher $fetcher,
    ) {}

    public function __invoke(WebhookReceivedEvent $event): void
    {
        if ($event->providerType !== 'github') {
            return;
        }

        if (!$this->shouldSync($event)) {
            return;
        }

        $sources = $this->sourceRepo->findByRepository($event->repository);
        if (empty($sources)) {
            return;
        }

        $payload = json_decode($event->payload);

        foreach ($sources as $source) {
            if ($event->eventType === 'push' && !$this->fileChanged($payload, $source->filePath)) {
                continue;
            }

            $content = $this->fetcher->fetch(
                $source->repository,
                $source->filePath,
                $source->ref,
            );
            if ($content === null) {
                continue;
            }

            try {
                $page = $this->pageRepo->getByUid($source->pageUid);
            } catch (Throwable) {
                continue;
            }

            $this->pageRepo->updateText(
                $page->name,
                $content,
                sprintf('Synced from %s:%s', $source->repository, $source->filePath),
            );

            $this->sourceRepo->updateSyncState(
                $source->pageUid,
                $this->extractCommitSha($payload),
            );
        }
    }

    private function shouldSync(WebhookReceivedEvent $event): bool
    {
        if ($event->eventType === 'push') {
            return true;
        }

        if ($event->eventType === 'pull_request' && $event->action === 'closed') {
            $payload = json_decode($event->payload);
            return (bool) ($payload->pull_request->merged ?? false);
        }

        return false;
    }

    private function fileChanged(object $payload, string $filePath): bool
    {
        foreach ($payload->commits ?? [] as $commit) {
            $changed = array_merge(
                $commit->added ?? [],
                $commit->modified ?? [],
            );
            if (in_array($filePath, $changed, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractCommitSha(object $payload): string
    {
        return $payload->head_commit->id
            ?? $payload->pull_request->merge_commit_sha
            ?? '';
    }
}

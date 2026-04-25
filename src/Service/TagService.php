<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Service;

use Horde\Wicked\Tagger;
use Throwable;

final class TagService
{
    public function __construct(
        private readonly ?Tagger $tagger,
    ) {}

    public function isAvailable(): bool
    {
        return $this->tagger !== null;
    }

    public function tag(string $pageUid, array $tags, string $owner): void
    {
        if ($this->tagger === null || empty($tags)) {
            return;
        }
        $this->tagger->tag($pageUid, $tags, $owner);
    }

    /**
     * @return array<int, string>  Tag names keyed by tag ID.
     */
    public function getTags(string $pageUid): array
    {
        if ($this->tagger === null || $pageUid === '') {
            return [];
        }
        try {
            return $this->tagger->getTags($pageUid, 'page');
        } catch (Throwable) {
            return [];
        }
    }

    public function replaceTags(string $pageUid, array $tags, string $owner): void
    {
        if ($this->tagger === null || $pageUid === '') {
            return;
        }
        try {
            $this->tagger->replaceTags($pageUid, $tags, $owner, 'page');
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string> $pageUids
     *
     * @return array<string, array<int, string>>  Tags keyed by page UID.
     */
    public function getTagsByPages(array $pageUids): array
    {
        if ($this->tagger === null || empty($pageUids)) {
            return [];
        }
        try {
            return $this->tagger->getTags($pageUids, 'page');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string> $tags  Tag names to search for.
     *
     * @return array<string>  Page UIDs matching all given tags.
     */
    public function search(array $tags): array
    {
        if ($this->tagger === null || empty($tags)) {
            return [];
        }
        try {
            return $this->tagger->search($tags);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<array{tag_id: int, tag_name: string, count: int}>
     */
    public function getCloud(int $limit = 20): array
    {
        if ($this->tagger === null) {
            return [];
        }
        try {
            return $this->tagger->getCloud(null, $limit);
        } catch (Throwable) {
            return [];
        }
    }
}

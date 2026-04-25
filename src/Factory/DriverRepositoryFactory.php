<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Factory;

use Horde\Wicked\Domain\AttachmentRepositoryInterface;
use Horde\Wicked\Domain\PageRepositoryInterface;
use Horde\Wicked\Domain\Repository\DriverAttachmentRepository;
use Horde\Wicked\Domain\Repository\DriverPageRepository;
use Horde\Wicked\Domain\Repository\DriverSearchRepository;
use Horde\Wicked\Domain\SearchRepositoryInterface;
use Wicked_Driver;

final class DriverRepositoryFactory
{
    private ?DriverPageRepository $pages = null;
    private ?DriverSearchRepository $search = null;
    private ?DriverAttachmentRepository $attachments = null;

    public function __construct(
        private readonly Wicked_Driver $driver,
    ) {}

    public function pages(): PageRepositoryInterface
    {
        return $this->pages ??= new DriverPageRepository($this->driver);
    }

    public function search(): SearchRepositoryInterface
    {
        return $this->search ??= new DriverSearchRepository($this->driver);
    }

    public function attachments(): AttachmentRepositoryInterface
    {
        return $this->attachments ??= new DriverAttachmentRepository($this->driver);
    }
}

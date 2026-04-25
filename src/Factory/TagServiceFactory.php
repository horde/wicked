<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Factory;

use Horde\Wicked\Service\TagService;
use Horde\Wicked\Tagger;
use Horde_Injector;
use Throwable;

final class TagServiceFactory
{
    public function create(Horde_Injector $injector): TagService
    {
        try {
            $tagger = new Tagger();

            return new TagService($tagger);
        } catch (Throwable) {
            return new TagService(null);
        }
    }
}

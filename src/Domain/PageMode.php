<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Domain;

enum PageMode: int
{
    case Display = 0;
    case Edit = 1;
    case Remove = 2;
    case History = 3;
    case Diff = 4;
    case Locking = 7;
    case Unlocking = 8;
    case Create = 9;
    case Content = 10;
    case Block = 11;
}

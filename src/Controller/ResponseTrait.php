<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Wicked;

/**
 * Shared response helpers for Wicked PSR-15 controllers.
 *
 * Composes Core's HtmlResponseTrait and RedirectResponseTrait for standard
 * response building, and adds renderChrome() for legacy Horde_PageOutput
 * chrome wrapping.
 *
 * Expects the using class to have properties:
 *   - Horde_Notification_Handler $notification
 *   - Horde_PageOutput           $pageOutput
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
trait ResponseTrait
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    /**
     * Render page content inside the Horde chrome (topbar, header, footer).
     *
     * The callable $renderBody is expected to echo its output.
     */
    private function renderChrome(string $title, callable $renderBody): string
    {
        Wicked::setTopbar();

        ob_start();
        $this->pageOutput->header(['title' => $title]);
        $this->notification->notify(['listeners' => 'status']);
        $renderBody();
        $this->pageOutput->footer();

        return ob_get_clean();
    }
}

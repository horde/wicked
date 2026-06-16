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
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Horde;

/**
 * Shared response helpers for Wicked PSR-15 controllers.
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

    private function renderChrome(string $title, callable $renderBody): string
    {
        // Use Horde's buffer tracking so PageOutput::header() doesn't call
        // flush() in PSR-15 responses before ResponseWriterWeb writes headers.
        Horde::startBuffer();
        $this->pageOutput->header(['title' => $title]);
        $this->notification->notify(['listeners' => 'status']);
        $renderBody();
        $this->pageOutput->footer();

        return Horde::endBuffer();
    }

    protected function downloadResponse(
        string $content,
        string $filename,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        $streamFactory = new StreamFactory();
        $response = new Response();

        return $response
            ->withBody($streamFactory->createStream($content))
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($content))
            ->withStatus(200);
    }
}

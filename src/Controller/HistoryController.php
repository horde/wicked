<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde_Notification_Handler;
use Horde_PageOutput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wicked;
use Wicked_Exception;
use Wicked_Page;

/**
 * PSR-15 controller for page history display.
 *
 * Replaces the legacy history.php entry point.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class HistoryController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private Horde_Notification_Handler $notification,
        private Horde_PageOutput $pageOutput,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $pageName = $queryParams['page'] ?? 'Wiki/Home';
        $version = $queryParams['version'] ?? null;
        $referrer = $queryParams['referrer'] ?? null;

        try {
            $page = Wicked_Page::getPage($pageName, $version, $referrer);
        } catch (Wicked_Exception $e) {
            $this->notification->push(
                _("Internal error viewing requested page"),
                'horde.error'
            );
            return $this->redirect(
                (string) Wicked::url('Wiki/Home', true)
            );
        }

        if (!$page->allows(Wicked::MODE_HISTORY)) {
            return $this->redirect(
                (string) Wicked::url($page->pageName(), true)
                    ->add('actionID', 'history')
            );
        }

        $html = $this->renderChrome(
            sprintf(_("History: %s"), $page->pageName()),
            function () use ($page) {
                echo $page->render(Wicked::MODE_HISTORY);
            }
        );

        return $this->htmlResponse($html);
    }
}

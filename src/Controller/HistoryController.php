<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde\Wicked\Service\TopbarSearch;
use Horde\Wicked\Service\UrlGenerator;
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
 * Routes: History (primary: /history), LegacyHistory (secondary: /history.php)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class HistoryController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly UrlGenerator $urlGenerator,
        private readonly TopbarSearch $topbarSearch,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $queryParams = $request->getQueryParams();
        $pageName = $route['page'] ?? ($queryParams['page'] ?? 'Wiki/Home');
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
                $this->urlGenerator->urlFor('Pages', ['page' => 'Wiki/Home'])
            );
        }

        if (!$page->allows(Wicked::MODE_HISTORY)) {
            return $this->redirect(
                $this->urlGenerator->urlFor('Pages', ['page' => $page->pageName()])
                . '?actionID=history'
            );
        }

        $this->topbarSearch->apply();

        $html = $this->renderChrome(
            sprintf(_("History: %s"), $page->pageName()),
            function () use ($page) {
                echo $page->render(Wicked::MODE_HISTORY);
            }
        );

        return $this->htmlResponse($html);
    }
}

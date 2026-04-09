<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wicked;
use Wicked_Exception;
use Wicked_Page;

/**
 * PSR-15 controller for page diff display.
 *
 * Replaces the legacy diff.php entry point.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class DiffController implements RequestHandlerInterface
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
        $v1 = $queryParams['v1'] ?? '';
        $v2 = $queryParams['v2'] ?? '';
        $pageName = $queryParams['page'] ?? 'Wiki/Home';

        // At least one version must be specified
        if ($v1 === '' && $v2 === '') {
            return $this->redirect(
                (string) Horde::url('history.php', true)
                    ->add('page', $pageName)
            );
        }

        // Normalize: v2 should be the higher version.
        // Empty string = current (highest). '?' = previous (lowest).
        if ($v1 === '' || ($v2 !== '' && version_compare($v1, $v2) > 0) || $v2 === '?') {
            [$v1, $v2] = [$v2, $v1];
        }

        try {
            $page = Wicked_Page::getPage($pageName, $v2 !== '' ? $v2 : null);
        } catch (Wicked_Exception $e) {
            $this->notification->push(
                sprintf(
                    _("Internal error viewing requested page: %s"),
                    $e->getMessage()
                ),
                'horde.error'
            );
            return $this->redirect(
                (string) Wicked::url('Wiki/Home', true)
            );
        }

        if ($v1 === '?') {
            $v1 = $page->previousVersion();
        }

        if (!$page->allows(Wicked::MODE_DIFF)) {
            return $this->redirect(
                (string) Wicked::url($page->pageName(), true)
                    ->add('actionID', 'diff')
            );
        }

        $html = $this->renderChrome(
            sprintf(
                _("Diff for %s between %s and %s"),
                $page->pageName(),
                $v1,
                $page->version()
            ),
            function () use ($page, $v1) {
                $page->render(Wicked::MODE_DIFF, $v1);
            }
        );

        return $this->htmlResponse($html);
    }
}

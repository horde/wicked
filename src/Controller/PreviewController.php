<?php

declare(strict_types=1);

/**
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde_Injector;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde\Wicked\WickedEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 controller for edit preview.
 *
 * Replaces the legacy preview.php entry point. Transforms raw wiki text
 * and renders a preview.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class PreviewController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private Horde_Notification_Handler $notification,
        private Horde_PageOutput $pageOutput,
        private WickedEngine $engine,
        private Horde_Injector $injector,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody() ?? [];

        // page_text can come from POST or GET
        $text = $parsedBody['page_text'] ?? ($queryParams['page_text'] ?? '');
        if ($text === '') {
            return $this->htmlResponse('');
        }

        $pageName = $parsedBody['page'] ?? ($queryParams['page'] ?? '');

        $view = $this->injector->createInstance('Horde_View');
        $view->text = $this->engine->transform($text);

        $html = $this->renderChrome(
            sprintf(_("Edit %s"), $pageName),
            function () use ($view) {
                echo $view->render('edit/preview');
            }
        );

        return $this->htmlResponse($html);
    }
}

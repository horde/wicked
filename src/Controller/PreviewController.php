<?php

declare(strict_types=1);

/**
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde\Wicked\Service\TopbarSearch;
use Horde\Wicked\WickedEngine;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 controller for edit preview.
 *
 * Routes: Preview (primary: /preview), LegacyPreview (secondary: /preview.php)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class PreviewController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly WickedEngine $engine,
        private readonly Horde_View $view,
        private readonly TopbarSearch $topbarSearch,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = (array) ($request->getParsedBody() ?? []);

        $text = $parsedBody['page_text'] ?? ($queryParams['page_text'] ?? '');
        if ($text === '') {
            return $this->htmlResponse('');
        }

        $pageName = $parsedBody['page'] ?? ($queryParams['page'] ?? '');

        $this->view->text = $this->engine->transform($text);

        $html = $this->renderChrome(
            sprintf(_("Edit %s"), $pageName),
            function () {
                $this->topbarSearch->apply();
                $this->view->addTemplatePath($this->registry->get('templates', 'wicked'));
                echo $this->view->render('edit/preview');
            }
        );

        return $this->htmlResponse($html);
    }
}

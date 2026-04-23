<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde\Core\Session\HordeSession;
use Horde\Wicked\Service\TopbarSearch;
use Horde\Wicked\Service\UrlGenerator;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wicked;
use Wicked_Driver;
use Wicked_Exception;
use Wicked_Page;
use Wicked_Page_StandardPage;

/**
 * PSR-15 controller for wiki page display.
 *
 * Routes: Pages (primary: /*page), LegacyDisplay (secondary: /display.php)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class PageController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly HordeSession $session,
        private readonly Wicked_Driver $driver,
        private readonly Horde_Registry $registry,
        private readonly UrlGenerator $urlGenerator,
        private readonly TopbarSearch $topbarSearch,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $queryParams = $request->getQueryParams();
        $postParams = (array) $request->getParsedBody();
        $merged = array_merge($queryParams, $postParams);
        $pageName = rtrim($route['page'] ?? ($merged['page'] ?? 'Wiki/Home'), '/');
        $actionID = $merged['actionID'] ?? null;
        $version = $merged['version'] ?? null;
        $referrer = $merged['referrer'] ?? null;
        $params = $merged['params'] ?? ($merged['searchfield'] ?? null);

        try {
            $page = Wicked_Page::getPage($pageName, $version, $referrer);
        } catch (Wicked_Exception $e) {
            $this->notification->push(
                _("Internal error viewing requested page"),
                'horde.error'
            );
            $page = Wicked_Page::getPage('');
            $actionID = null;
        }

        switch ($actionID) {
            case 'lock':
                return $this->lock($page);

            case 'unlock':
                return $this->unlock($page);

            case 'history':
                if ($page->allows(Wicked::MODE_HISTORY)) {
                    return $this->redirect(
                        $this->urlGenerator->urlFor('History', ['page' => $page->pageName()])
                    );
                }
                $this->notification->push(
                    _("This page does not have a history"),
                    'horde.error'
                );
                break;

            case 'special':
                $redirectUrl = $page->handleAction();
                if ($redirectUrl !== null) {
                    return $this->redirect($redirectUrl);
                }
                break;

            case 'export':
                return $this->export($page, $queryParams);

            default:
                $this->driver->logPageView($page->pageName());
                break;
        }

        if (!$page->allows(Wicked::MODE_DISPLAY)) {
            if ($page->pageName() === 'Wiki/Home') {
                throw new Wicked_Exception(
                    _("You don't have permission to view this page.")
                );
            }
            $this->notification->push(
                _("You don't have permission to view this page."),
                'horde.error'
            );
            $page = Wicked_Page::getPage('');
        }

        $page->preDisplay(Wicked::MODE_DISPLAY, $params);

        if ($page instanceof Wicked_Page_StandardPage && !$page->isValid()) {
            $this->notification->push(
                sprintf(_("Page \"%s\" does not exist."), $pageName),
                'horde.warning'
            );
            return $this->redirect(
                $this->urlGenerator->urlFor('Pages', ['page' => 'Wiki/Home'])
            );
        }

        if ($page->isLocked()) {
            $this->notification->push(
                sprintf(
                    _("This page is locked by %s for %d Minutes."),
                    $page->getLockRequestor(),
                    $page->getLockTime()
                ),
                'horde.message'
            );
        }

        $html = $this->renderChrome($page->pageTitle(), function () use ($page, $params) {
            $this->topbarSearch->apply();

            $this->pageOutput->addLinkTag([
                'href' => $this->urlGenerator->absoluteUrlFor('Pages', ['page' => 'opensearch.php']),
                'rel' => 'search',
                'title' => $this->registry->get('name')
                    . ' (' . $this->urlGenerator->absoluteUrlFor('Pages', ['page' => 'Wiki/Home']) . ')',
                'type' => 'application/opensearchdescription+xml',
            ]);

            try {
                echo $page->render(Wicked::MODE_DISPLAY, $params);
            } catch (Wicked_Exception $e) {
                $this->notification->push($e);
            }
        });

        $history = $this->session->getScoped('wicked', 'history') ?? [];
        if (!is_array($history)) {
            $history = [];
        }
        if (
            $page instanceof Wicked_Page_StandardPage
            && (!isset($history[0]) || $history[0] !== $page->pageName())
        ) {
            array_unshift($history, $page->pageName());
            $this->session->setScoped('wicked', 'history', $history);
        }
        if (count($history) > 10) {
            array_pop($history);
            $this->session->setScoped('wicked', 'history', $history);
        }

        return $this->htmlResponse($html);
    }

    private function lock(Wicked_Page $page): ResponseInterface
    {
        if (!$page->allows(Wicked::MODE_LOCKING)) {
            $this->notification->push(
                _("You are not allowed to lock this page"),
                'horde.error'
            );
        } else {
            try {
                $page->lock();
            } catch (Wicked_Exception $e) {
                $this->notification->push(
                    sprintf(_("Page failed to lock: %s"), $e->getMessage()),
                    'horde.error'
                );
            }
        }

        return $this->redirect(
            $this->urlGenerator->urlFor('Pages', ['page' => $page->pageName()])
        );
    }

    private function unlock(Wicked_Page $page): ResponseInterface
    {
        if (!$page->allows(Wicked::MODE_UNLOCKING)) {
            $this->notification->push(
                _("You are not allowed to unlock this page"),
                'horde.error'
            );
        } else {
            try {
                $page->unlock();
                $this->notification->push(_("Page unlocked"), 'horde.success');
            } catch (Wicked_Exception $e) {
                $this->notification->push(
                    sprintf(
                        _("Page failed to unlock: %s"),
                        $e->getMessage()
                    ),
                    'horde.error'
                );
            }
        }

        return $this->redirect(
            $this->urlGenerator->urlFor('Pages', ['page' => $page->pageName()])
        );
    }

    private function export(Wicked_Page $page, array $queryParams): ResponseInterface
    {
        if (!$page->allows(Wicked::MODE_DISPLAY)) {
            $this->notification->push(
                _("You don't have permission to view this page."),
                'horde.error'
            );
            if ($page->pageName() === 'Wiki/Home') {
                throw new Wicked_Exception(
                    _("You don't have permission to view this page.")
                );
            }
            return $this->redirect(
                $this->urlGenerator->urlFor('Pages', ['page' => 'Wiki/Home'])
            );
        }

        switch ($queryParams['format'] ?? 'plain') {
            case 'html':
                $format = 'Xhtml';
                $ext = '.html';
                $mime = 'text/html';
                break;
            case 'tex':
                $format = 'Latex';
                $ext = '.tex';
                $mime = 'text/x-tex';
                break;
            case 'rst':
                $format = 'Rst';
                $ext = '.rst';
                $mime = 'text/plain';
                break;
            case 'plain':
            default:
                $format = 'Plain';
                $ext = '.txt';
                $mime = 'text/plain';
                break;
        }

        try {
            $wiki = $page->getProcessor($format);
            $text = $wiki->transform($page->getText(), $format);
        } catch (Wicked_Exception $e) {
            $this->notification->push($e);
            return $this->redirect(
                $this->urlGenerator->urlFor('Pages', ['page' => $page->pageName()])
            );
        }

        return $this->downloadResponse(
            $text,
            $page->pageTitle() . $ext,
            $mime,
        );
    }
}

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
use Horde_Session;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wicked;
use Wicked_Driver;
use Wicked_Exception;
use Wicked_Page;
use Wicked_Page_StandardPage;

/**
 * PSR-15 controller for wiki page display.
 *
 * Replaces the legacy display.php entry point. Resolves the wiki page
 * from the route match, dispatches actions (lock, unlock, export, etc.),
 * and renders the page content into a PSR-7 Response.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class PageController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private Horde_Notification_Handler $notification,
        private Horde_PageOutput $pageOutput,
        private Horde_Session $session,
        private Wicked_Driver $driver,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $pageName = rtrim($route['page'] ?? 'Wiki/Home', '/');
        $queryParams = $request->getQueryParams();
        $actionID = $queryParams['actionID'] ?? null;
        $version = $queryParams['version'] ?? null;
        $referrer = $queryParams['referrer'] ?? null;
        $params = $queryParams['params'] ?? ($queryParams['searchfield'] ?? null);

        // Resolve the wiki page
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

        // Dispatch action
        switch ($actionID) {
            case 'lock':
                return $this->lock($page);

            case 'unlock':
                return $this->unlock($page);

            case 'history':
                if ($page->allows(Wicked::MODE_HISTORY)) {
                    return $this->redirect(
                        (string) Horde::url('history.php')
                            ->add('page', $page->pageName())
                    );
                }
                $this->notification->push(
                    _("This page does not have a history"),
                    'horde.error'
                );
                break;

            case 'special':
                $page->handleAction();
                break;

            case 'export':
                return $this->export($page, $queryParams);

            default:
                $this->driver->logPageView($page->pageName());
                break;
        }

        // Permission check
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

        // Non-existent page: redirect to home with a warning
        if ($page instanceof Wicked_Page_StandardPage && !$page->isValid()) {
            $this->notification->push(
                sprintf(_("Page \"%s\" does not exist."), $pageName),
                'horde.warning'
            );
            return $this->redirect(
                (string) Wicked::url('Wiki/Home', true)
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

        // Capture rendered output
        Wicked::addFeedLink();
        $html = $this->renderChrome($page->pageTitle(), function () use ($page, $params) {
            try {
                echo $page->render(Wicked::MODE_DISPLAY, $params);
            } catch (Wicked_Exception $e) {
                $this->notification->push($e);
            }
        });

        // Session history tracking
        $history = $this->session->get('wicked', 'history', Horde_Session::TYPE_ARRAY);
        if (
            $page instanceof Wicked_Page_StandardPage
            && (!isset($history[0]) || $history[0] !== $page->pageName())
        ) {
            array_unshift($history, $page->pageName());
            $this->session->set('wicked', 'history', $history);
        }
        if (count($history) > 10) {
            array_pop($history);
            $this->session->set('wicked', 'history', $history);
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
            (string) Wicked::url($page->pageName())
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
            (string) Wicked::url($page->pageName())
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
                (string) Wicked::url('Wiki/Home', true)
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
                (string) Wicked::url($page->pageName())
            );
        }

        $filename = $page->pageTitle() . $ext;
        $body = $this->streamFactory->createStream($text);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', $mime)
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            )
            ->withHeader('Content-Length', (string) strlen($text))
            ->withBody($body);
    }
}

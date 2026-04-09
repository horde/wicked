<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wicked;
use Wicked_Driver;
use Wicked_Exception;

/**
 * PSR-15 controller for admin attachment management.
 *
 * Lists all wiki attachments, shows which pages they belong to,
 * and provides delete functionality. Access restricted to admins.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class AdminAttachmentsController implements RequestHandlerInterface
{
    use ResponseTrait;

    public function __construct(
        private Horde_Notification_Handler $notification,
        private Horde_PageOutput $pageOutput,
        private Wicked_Driver $driver,
        private Horde_Registry $registry,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAdmin()) {
            $this->notification->push(
                _("You are not allowed to access the admin area."),
                'horde.error'
            );
            return $this->redirect(
                (string) Wicked::url('Wiki/Home', true)
            );
        }

        $queryParams = $request->getQueryParams();
        $actionID = $queryParams['actionID'] ?? null;

        if ($actionID === 'delete') {
            return $this->deleteAttachment($queryParams);
        }

        return $this->listAttachments();
    }

    private function isAdmin(): bool
    {
        return $this->registry->isAdmin()
            || $this->registry->isAdmin(['permission' => 'wicked:admin'])
            || $this->registry->isAdmin(['permission' => 'wicked:admin:attachments']);
    }

    private function deleteAttachment(array $params): ResponseInterface
    {
        $pageId = (int) ($params['page_id'] ?? 0);
        $attachment = $params['attachment'] ?? '';
        $webroot = rtrim((string) $this->registry->get('webroot', 'wicked'), '/');

        if ($attachment === '') {
            $this->notification->push(_("No attachment specified."), 'horde.error');
            return $this->redirect($webroot . '/admin/attachments');
        }

        try {
            $this->driver->removeAttachment($pageId, $attachment);
            $this->notification->push(
                sprintf(_("Attachment \"%s\" deleted."), $attachment),
                'horde.success'
            );
        } catch (Wicked_Exception $e) {
            $this->notification->push(
                sprintf(_("Failed to delete attachment: %s"), $e->getMessage()),
                'horde.error'
            );
        }

        return $this->redirect($webroot . '/admin/attachments');
    }

    private function listAttachments(): ResponseInterface
    {
        $attachments = $this->driver->getAllAttachments();
        $webroot = rtrim((string) $this->registry->get('webroot', 'wicked'), '/');

        // Build page_id → page_name lookup
        $pageNames = [];
        foreach ($attachments as $att) {
            $pid = (int) $att['page_id'];
            if (!isset($pageNames[$pid])) {
                if ($pid === 0) {
                    $pageNames[$pid] = null;
                } else {
                    $rows = $this->driver->getPageById($pid);
                    $pageNames[$pid] = $rows[0]['page_name'] ?? null;
                }
            }
        }

        $html = $this->renderChrome(
            _("Admin: Attachments"),
            function () use ($attachments, $pageNames, $webroot) {
                $this->pageOutput->addScriptFile('tables.js', 'horde');
                $this->renderAttachmentTable($attachments, $pageNames, $webroot);
            }
        );

        return $this->htmlResponse($html);
    }

    private function renderAttachmentTable(
        array $attachments,
        array $pageNames,
        string $webroot,
    ): void {
        echo '<h1 class="header">' . htmlspecialchars(_("Attachment Management")) . '</h1>';

        if (empty($attachments)) {
            echo '<p>' . htmlspecialchars(_("No attachments found.")) . '</p>';
            return;
        }

        echo '<table class="horde-table sortable">';
        echo '<thead><tr>';
        echo '<th>' . htmlspecialchars(_("Attachment")) . '</th>';
        echo '<th>' . htmlspecialchars(_("Page")) . '</th>';
        echo '<th>' . htmlspecialchars(_("Page ID")) . '</th>';
        echo '<th>' . htmlspecialchars(_("Version")) . '</th>';
        echo '<th>' . htmlspecialchars(_("Action")) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($attachments as $att) {
            $name = htmlspecialchars($att['attachment_name']);
            $pid = (int) $att['page_id'];
            $pageName = $pageNames[$pid] ?? null;
            $version = (int) ($att['attachment_version'] ?? 0);

            $downloadUrl = $this->registry->downloadUrl(
                $att['attachment_name'],
                [
                    'page' => $pid,
                    'file' => $att['attachment_name'],
                    'version' => $version,
                ]
            );

            echo '<tr>';
            echo '<td><a href="' . $downloadUrl . '">' . $name . '</a></td>';

            if ($pageName !== null) {
                $pageUrl = htmlspecialchars($webroot . '/' . str_replace('%2F', '/', urlencode($pageName)));
                echo '<td><a href="' . $pageUrl . '">' . htmlspecialchars($pageName) . '</a></td>';
            } else {
                echo '<td><em>' . htmlspecialchars(_("Orphaned (no page)")) . '</em></td>';
            }

            echo '<td>' . $pid . '</td>';
            echo '<td>' . $version . '</td>';

            $deleteUrl = htmlspecialchars(
                $webroot . '/admin/attachments?actionID=delete'
                . '&page_id=' . $pid
                . '&attachment=' . urlencode($att['attachment_name'])
            );
            echo '<td><a href="' . $deleteUrl . '" '
                . 'onclick="return confirm(\'' . htmlspecialchars(_("Really delete this attachment?"), ENT_QUOTES) . '\')"'
                . '>' . htmlspecialchars(_("Delete")) . '</a></td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p>' . sprintf(_("Total: %d attachments"), count($attachments)) . '</p>';
    }
}

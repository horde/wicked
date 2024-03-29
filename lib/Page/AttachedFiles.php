<?php
/**
 * Copyright 2003-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Jan Schneider <jan@horde.org>
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @package  Wicked
 */

/**
 * Displays and handles attached files.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Jan Schneider <jan@horde.org>
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @package  Wicked
 */
class Wicked_Page_AttachedFiles extends Wicked_Page
{
    /**
     * Display modes supported by this page.
     *
     * @var array
     */
    public $supportedModes = array(
        Wicked::MODE_CONTENT => true,
        Wicked::MODE_EDIT => true,
        Wicked::MODE_REMOVE => true,
        Wicked::MODE_DISPLAY => true);

    /**
     * The page for which we'd like to manipulate attachments.
     *
     * @var string
     */
    protected $_referrer = null;

    /**
     * Constructor.
     */
    public function __construct($referrer)
    {
        $this->_referrer = $referrer;
    }

    /**
     * Returns the current user's permissions for the referring page.
     *
     * @param  string $pageName  The page name (unused in this method).
     *
     * @return integer  The permissions bitmask.
     */
    public function getPermissions($pageName = null)
    {
        return parent::getPermissions($this->referrer());
    }

    /**
     * Returns this page rendered in content mode.
     *
     * @throws Wicked_Exception
     */
    public function content()
    {
        global $wicked, $notification, $registry;

        if (!$wicked->pageExists($this->referrer())) {
            throw new Wicked_Exception(sprintf(_("Referrer \"%s\" does not exist."),
                                               $this->referrer()));
        }

        $referrer_id = $wicked->getPageId($this->referrer());
        $attachments = $wicked->getAttachedFiles($referrer_id, true);

        foreach ($attachments as $idx => $attach) {
            $attachments[$idx]['timestamp'] = $attach['attachment_created'];
            $attachments[$idx]['date'] = strftime(
                $GLOBALS['prefs']->getValue('date_format'),
                $attach['attachment_created']
            );

            $attachments[$idx]['url'] = $registry->downloadUrl(
                $attach['attachment_name'],
                array('page' => $referrer_id,
                      'file' => $attach['attachment_name'],
                      'version' => $attach['attachment_version']));

            $attachments[$idx]['delete_form'] = $this->allows(Wicked::MODE_REMOVE);

            $this->_page['change_author'] = $attachments[$idx]['change_author'];
            $attachments[$idx]['change_author'] = $this->author();
        }

        return $attachments;
    }

    /**
     * Returns this page rendered in Display mode.
     *
     * @throws Wicked_Exception
     */
    public function display()
    {
        global $registry, $wicked, $notification, $conf;

        try {
            $attachments = $this->content();
        } catch (Wicked_Exception $e) {
            $notification->push(sprintf(_("Error retrieving attachments: %s"),
                                        $e->getMessage()),
                                'horde.error');
            throw $e;
        }

        $GLOBALS['page_output']->addScriptFile('tables.js', 'horde');
        $view = $GLOBALS['injector']->createInstance('Horde_View');

        $view->pageName = $this->pageName();
        $view->formAction = Wicked::url('AttachedFiles');
        $view->deleteButton = Horde_Themes::img('delete.png');
        $view->referrerLink = Wicked::url($this->referrer());

        $refreshIcon = Horde::link($this->pageUrl())
            . Horde::img('reload.png',
                         sprintf(_("Reload \"%s\""), $this->pageTitle()))
            . '</a>';
        $view->refreshIcon = $refreshIcon;
        $view->attachments = $attachments;

        /* Get an array of unique filenames for the update form. */
        $files = array();
        foreach ($attachments as $attachment) {
            $files[$attachment['attachment_name']] = true;
        }
        $files = array_keys($files);
        sort($files);
        $view->files = $files;
        $view->canUpdate = $this->allows(Wicked::MODE_EDIT) && count($files);
        $view->canAttach = $this->allows(Wicked::MODE_EDIT);
        $view->requireChangelog = $conf['wicked']['require_change_log'];

        $view->requiredMarker = Horde::img('required.png', '*');
        $view->referrer = $this->referrer();
        $view->formInput = Horde_Util::formInput();

        echo $view->render('display/AttachedFiles');
    }

    public function pageName()
    {
        return 'AttachedFiles';
    }

    public function pageTitle()
    {
        return sprintf(_("Attached Files: %s"), $this->referrer());
    }

    public function referrer()
    {
        return $this->_referrer;
    }

    /**
     * Retrieves the form fields and processes the attachment.
     */
    public function handleAction()
    {
        global $notification, $wicked, $registry, $conf;

        // Only allow POST commands.
        $cmd = Horde_Util::getPost('cmd');
        $version = Horde_Util::getFormData('version');
        $is_update = (bool)Horde_Util::getFormData('is_update');
        $filename = Horde_Util::getFormData('filename');
        $change_log = Horde_Util::getFormData('change_log');

        // See if we're supposed to delete an attachment.
        if ($cmd == 'delete' && $filename && $version) {
            if (!$this->allows(Wicked::MODE_REMOVE)) {
                $notification->push(_("You do not have permission to delete attachments from this page."), 'horde.error');
                return;
            }

            try {
                $wicked->removeAttachment($wicked->getPageId($this->referrer()),
                                          $filename, $version);
                $notification->push(
                    sprintf(_("Successfully deleted version %s of \"%s\" from \"%s\""),
                            $version, $filename, $this->referrer()),
                    'horde.success');
            } catch (Wicked_Exception $e) {
                $notification->push($result->getMessage(), 'horde.error');
            }
            return;
        }

        if (empty($filename)) {
            $filename = Horde_Util::dispelMagicQuotes($_FILES['attachment_file']['name']);
        }

        try {
            $GLOBALS['browser']->wasFileUploaded('attachment_file', _("attachment"));
        } catch (Horde_Browser_Exception $e) {
            $notification->push($e, 'horde.error');
            return;
        }

        if (strpos($filename, ' ') !== false) {
            $notification->push(
                _("Attachments with spaces can't be embedded into a page."),
                'horde.warning');
        }

        $data = file_get_contents($_FILES['attachment_file']['tmp_name']);
        if ($data === false) {
            $notification->push(_("Can't read uploaded file."), 'horde.error');
            return;
        }

        if (!$this->allows(Wicked::MODE_EDIT)) {
            $notification->push(
                sprintf(_("You do not have permission to edit \"%s\""),
                        $this->referrer()),
                'horde.error');
            return;
        }

        if ($conf['wicked']['require_change_log'] && empty($change_log)) {
            $notification->push(
                _("You must enter a change description to attach this file."),
                'horde.error');
            return;
        }

        $referrer_id = $wicked->getPageId($this->referrer());
        try {
            $attachments = $wicked->getAttachedFiles($referrer_id);
        } catch (Wicked_Exception $e) {
            $notification->push(sprintf(_("Error retrieving attachments: %s"),
                                        $e->getMessage()),
                                'horde.error');
            return;
        }

        $found = false;
        foreach ($attachments as $attach) {
            if ($filename == $attach['attachment_name']) {
                $found = true;
                break;
            }
        }

        if ($is_update) {
            if (!$found) {
                $notification->push(
                    sprintf(_("Can't update \"%s\": no such attachment."),
                            $filename),
                    'horde.error');
                return;
            }
        } else {
            if ($found) {
                $notification->push(
                    sprintf(_("There is already an attachment named \"%s\"."),
                            $filename),
                    'horde.error');
                return;
            }
        }

        $file = array('page_id'         => $referrer_id,
                      'attachment_name' => $filename,
                      'change_log'      => $change_log);

        try {
            $wicked->attachFile($file, $data);
        } catch (Wicked_Exception $e) {
            $notification->push($e);
            Horde::log($e);
            throw $e;
        }

        if ($is_update) {
            $message = sprintf(_("Updated attachment \"%s\" on page \"%s\"."),
                               $filename, $this->referrer());
        } else {
            $message = sprintf(_("New attachment \"%s\" to page \"%s\"."),
                               $filename, $this->referrer());
        }
        $notification->push($message, 'horde.success');

        $url = Wicked::url($this->referrer(), true, -1);
        Wicked::mail($message . ' ' . _("View page: ") . $url . "\n",
                     array('Subject' => '[' . $registry->get('name')
                           . '] attachment: ' . $this->referrer() . ', '
                           . $filename));
    }

}

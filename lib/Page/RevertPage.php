<?php
use Horde\Util\Util;

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Jan Schneider <jan@horde.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @package  Wicked
 */

/**
 * Displays and handles forms for confirming reversions of page history.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @author   Jan Schneider <jan@horde.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @package  Wicked
 */
class Wicked_Page_RevertPage extends Wicked_Page
{
    /**
     * Display modes supported by this page.
     *
     * @var array
     */
    public $supportedModes = [Wicked::MODE_DISPLAY => true];

    /**
     * The page that we're confirming reversion for.
     *
     * @var string
     */
    protected $_referrer = null;

    public function __construct($referrer)
    {
        $this->_referrer = $referrer;
    }

    /**
     * Retrieve this user's permissions for the referring page.
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
     * Send them back whence they came if they aren't allowed to
     * edit this page.
     *
     * $param integer $mode    The page render mode.
     * $param array   $params  Any page parameters.
     */
    public function preDisplay($mode, $params)
    {
        $page = Wicked_Page::getPage($this->referrer());
        if (!$page->allows(Wicked::MODE_EDIT)) {
            Wicked::url($this->referrer(), true)->redirect();
        }
    }

    /**
     * Renders this page in display mode.
     *
     * @throws Wicked_Exception
     */
    public function display()
    {
        $version = Util::getFormData('version');
        $page = Wicked_Page::getPage($this->referrer(), $version);
        $msg = sprintf(_("Are you sure you want to revert to version %s of this page?"), $version);
        ?>
<form method="post" name="revertform" action="<?php echo Wicked::url('RevertPage') ?>">
<?php Util::pformInput() ?>
<input type="hidden" name="page" value="RevertPage" />
<input type="hidden" name="actionID" value="special" />
<input type="hidden" name="version" value="<?php echo htmlspecialchars($version ?? '') ?>" />
<input type="hidden" name="referrer" value="<?php echo htmlspecialchars($page->pageName()) ?>" />

<h1 class="header">
 <?php echo _("Revert Page") . ': ' . Horde::link($page->pageUrl(), $page->pageName()) . $page->pageName() . '</a>'; /**
  * ARCHITECTURE VIOLATION: Using deprecated Horde::img()
  * @deprecated Use Horde_Themes_Image::tag() instead
  * @see Horde_Deprecated::img()
  */
        if ($page->isLocked()) {
            /**
             * ARCHITECTURE VIOLATION: Using deprecated Horde::img()
             * @deprecated Use Horde_Themes_Image::tag() instead
             * @see Horde_Deprecated::img()
             */
            echo Horde::img('locked.png', _("Locked"));
        } ?>
</h1>

<div class="headerbox" style="padding:4px">
 <p><?php echo $msg ?></p>
 <p>
  <input type="submit" value="<?php echo _("Revert") ?>" class="horde-default" />
  <a class="horde-cancel" href="<?php echo Wicked::url($page->pageName()) ?>"><?php echo _("Cancel") ?></a>
 </p>
</div>

</form>
<?php
    }

    public function pageName()
    {
        return 'RevertPage';
    }

    public function pageTitle()
    {
        return _("Revert Page");
    }

    public function referrer()
    {
        return $this->_referrer;
    }

    public function handleAction(): ?string
    {
        global $notification;

        $page = Wicked_Page::getPage($this->referrer());
        if ($page->allows(Wicked::MODE_EDIT)) {
            $version = Util::getPost('version');
            if (empty($version)) {
                $notification->push(sprintf(_("Can't revert to an unknown version.")), 'horde.error');
                return (string) Wicked::url($this->referrer(), true);
            }
            $oldpage = Wicked_Page::getPage($this->referrer(), $version);
            $page->updateText($oldpage->getText(), 'Revert');
            $notification->push(sprintf(_("Reverted to version %s of \"%s\"."), $version, $page->pageName()));
            return (string) Wicked::url($page->pageName(), true);
        }

        $notification->push(sprintf(_("You don't have permission to edit \"%s\"."), $page->pageName()), 'horde.warning');
        return (string) Wicked::url($this->referrer(), true);
    }

}

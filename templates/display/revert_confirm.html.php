<form method="post" name="revertform" action="<?php echo $this->h($this->formAction) ?>">
<?php echo $this->formInput ?>
<input type="hidden" name="version" value="<?php echo $this->h($this->version ?? '') ?>" />
<input type="hidden" name="referrer" value="<?php echo $this->h($this->pageName) ?>" />

<h1 class="header">
 <?php echo _("Revert Page") ?>: <a href="<?php echo $this->h($this->pageUrl) ?>"><?php echo $this->h($this->pageName) ?></a>
<?php if ($this->isLocked): ?>
 <img src="<?php echo $this->h($this->lockedIcon) ?>" alt="<?php echo _("Locked") ?>" />
<?php endif ?>
</h1>

<div class="headerbox" style="padding:4px">
 <p><?php echo $this->h($this->message) ?></p>
 <p>
  <input type="submit" value="<?php echo _("Revert") ?>" class="horde-default" />
  <a class="horde-cancel" href="<?php echo $this->h($this->cancelUrl) ?>"><?php echo _("Cancel") ?></a>
 </p>
</div>

</form>

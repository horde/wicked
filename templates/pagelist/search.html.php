<h1 class="header"><?php echo _("Search") ?></h1>

<form method="get" action="<?php echo Wicked::url('Search') ?>">
  <input type="hidden" name="page" value="Search" />
  <div class="horde-content">
    <label for="wicked-search-input"><?php echo _("Search pages for") ?>:</label>
    <input type="text" id="wicked-search-input" name="searchfield" value="<?php echo htmlspecialchars($this->searchtext ?? '') ?>" size="40" />
    <input type="submit" class="horde-default" value="<?php echo _("Search") ?>" />
  </div>
</form>

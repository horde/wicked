<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */

use Horde\Wicked\WickedEngine;

/**
 * Special page showing the text formatting help for the active wiki engine.
 *
 * Loads the syntax reference from data/{Format}/Wiki/TextFormat and renders
 * it through WickedEngine. Cannot be edited — the content always reflects
 * the currently configured wiki format.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class Wicked_Page_TextFormat extends Wicked_Page
{
    public $supportedModes = [
        Wicked::MODE_DISPLAY => true,
        Wicked::MODE_BLOCK => true,
    ];

    /** @var string Format name resolved from config */
    private string $format;

    /** @var string|null Raw text loaded from data file */
    private ?string $text = null;

    private const FORMAT_DIRS = [
        'yawiki' => 'Default',
        'default' => 'Default',
    ];

    public function __construct(?string $referrer = null)
    {
        $this->_referrer = $referrer;
        $this->format = $GLOBALS['conf']['wicked']['format'] ?? 'yawiki';
    }

    public function pageName(): string
    {
        return 'Wiki/TextFormat';
    }

    public function pageTitle(): string
    {
        return sprintf(_("Text Formatting (%s)"), ucfirst($this->format));
    }

    /**
     * Special pages that only support DISPLAY/BLOCK cannot be edited,
     * removed, locked, etc. The base allows() defers to supports()
     * which checks supportedModes, so this is already correct.
     * Override explicitly for clarity.
     */
    public function allows($mode): bool
    {
        if ($mode === Wicked::MODE_EDIT
            || $mode === Wicked::MODE_REMOVE
            || $mode === Wicked::MODE_LOCKING
            || $mode === Wicked::MODE_UNLOCKING
        ) {
            return false;
        }

        return parent::allows($mode);
    }

    public function displayContents($isBlock): string
    {
        $view = $GLOBALS['injector']->createInstance('Horde_View');
        $view->addHelper('Wicked_View_Helper_Navigation');
        $view->name = $this->pageName();

        $text = $this->loadText();
        if ($text === null) {
            $view->text = '<p>' . htmlspecialchars(
                sprintf(
                    _("No text formatting reference available for the \"%s\" format."),
                    $this->format
                )
            ) . '</p>';
        } else {
            $engine = $GLOBALS['injector']->get(WickedEngine::class);
            $view->text = $engine->transform($text);
        }

        return $view->render('display/standard');
    }

    private function loadText(): ?string
    {
        if ($this->text !== null) {
            return $this->text;
        }

        $dir = self::FORMAT_DIRS[strtolower($this->format)]
            ?? ucfirst(strtolower($this->format));

        $path = WICKED_BASE . '/data/' . $dir . '/Wiki/TextFormat';
        if (!is_file($path)) {
            return null;
        }

        $this->text = file_get_contents($path);

        return $this->text;
    }
}

<?php

namespace Horde\Wicked;

use Horde\Text\Wiki\WikiRendererBase;
use Horde_Mime_Part;
use Horde_Mime_Viewer;

/**
 * @package Wicked
 */
class XhtmlRendererCode2 extends WikiRendererBase
{
    /**
     * Renders a token into text matching the requested format.
     *
     * @param array $options The "options" portion of the token (second
     * element).
     *
     * @return string The text rendered from the token options.
     */
    public function token($options)
    {
        $part = new Horde_Mime_Part();
        $part->setContents($options['text']);
        $part->setType('application/x-extension-' . $options['attr']['type']);
        $viewer = Horde_Mime_Viewer::factory(
            'Horde_Core_Mime_Viewer_Syntaxhighlighter',
            $part,
            ['registry' => $GLOBALS['registry']]
        );
        $data = $viewer->render('inline');
        $data = reset($data);
        return $data['data'];
    }
}

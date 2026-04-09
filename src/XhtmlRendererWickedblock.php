<?php

namespace Horde\Wicked;

use Horde\Text\Wiki\WikiRendererBase;
use Horde_Core_Factory_BlockCollection;

/**
 * @package Wicked
 */
class XhtmlRendererWickedblock extends WikiRendererBase
{
    public function __construct(WickedEngine $engine, public readonly Horde_Core_Factory_BlockCollection $blocks)
    {
        parent::__construct($engine);
        // Configure the PageExists callback
        $this->conf['exists_callback'] = function ($page) {
            return $this->storageDriver->pageExists($page);
        };
    }

    /**
    * Renders a token into text matching the requested format.
    *
    * @access public
    *
    * @param array $options The "options" portion of the token (second
    * element).
    *
    * @return string The text rendered from the token options.
    */
    public function token($options)
    {
        try {
            $blockCollection = $this->blocks->create();
            $block = $blockCollection->getBlock(
                $options['app'],
                $options['app'] . '_Block_' . $options['block'],
                $options['args']
            );
            $blockContent = $block->getContent();
            return $blockContent;
        } catch (Horde_Exception $e) {
            return $e->getMessage();
        }

    }
}

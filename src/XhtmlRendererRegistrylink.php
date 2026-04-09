<?php

namespace Horde\Wicked;

use Horde\Text\Wiki\WikiRendererBase;
use Horde_Registry;
use Horde_Url;
use Horde_Exception;

/**
 * @package Wicked
 */
class XhtmlRendererRegistrylink extends WikiRendererBase
{
    public function __construct(WickedEngine $engine, public readonly Horde_Registry $registry)
    {
        parent::__construct($engine);
        // Configure the PageExists callback
        $this->conf['exists_callback'] = function ($page) {
            return $this->storageDriver->pageExists($page);
        };
    }

    public function token($options)
    {
        try {
            $link = new Horde_Url($this->registry->link($options['method'], $options['args']));
        } catch (Horde_Exception $e) {
            return $e->getMessage();
        }

        return $link->link() . $options['title'] . '</a>';
    }
}

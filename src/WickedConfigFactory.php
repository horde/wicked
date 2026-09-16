<?php

declare(strict_types=1);
/**
 * Wicked configuration class factory
 *
 * Creates instances of the WickedConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(WickedConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Wicked;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class WickedConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): WickedConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('wicked');
        return new WickedConfig($state->toArray());
    }
}

<?php

declare(strict_types=1);
/**
 * Wicked configuration class
 *
 * Provides access to the Wicked configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(WickedConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Wicked;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: WickedConfigFactory::class, method: 'create')]
class WickedConfig extends State {}

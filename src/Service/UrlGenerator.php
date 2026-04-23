<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Service;

use Horde\Routes\Mapper;
use Horde\Routes\Utils;

/**
 * Injectable URL generator wrapping Horde\Routes\Utils.
 *
 * Provides named-route URL generation for Wicked controllers, replacing
 * static Wicked::url() calls.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class UrlGenerator
{
    private Utils $utils;

    public function __construct(
        private readonly Mapper $mapper,
        private readonly string $webroot,
    ) {
        $this->mapper->environ['SCRIPT_NAME'] = rtrim($webroot, '/');
        $this->utils = new Utils($this->mapper);
    }

    /**
     * Generate a relative URL for a named route.
     */
    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->utils->urlFor($routeName, $params);
    }

    /**
     * Generate a fully qualified (absolute) URL for a named route.
     */
    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $params['qualified'] = true;

        return $this->utils->urlFor($routeName, $params);
    }
}

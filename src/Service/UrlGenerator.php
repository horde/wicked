<?php

declare(strict_types=1);

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Service;

use Horde\Core\Uri\RoutesProvider;

/**
 * Injectable URL generator wrapping RoutesProvider.
 *
 * Provides named-route URL generation for Wicked controllers.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class UrlGenerator
{
    public function __construct(
        private readonly RoutesProvider $provider,
        private readonly string $webroot,
        private readonly array $environ = [],
    ) {}

    /**
     * Generate a relative URL for a named route.
     */
    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->provider->generateNamedPath($routeName, $params) ?? '';
    }

    /**
     * Generate a fully qualified (absolute) URL for a named route.
     */
    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $path = $this->provider->generateNamedPath($routeName, $params);
        if ($path === null) {
            return '';
        }

        $host = $this->environ['HTTP_HOST']
            ?? $this->environ['SERVER_NAME']
            ?? 'localhost';

        $scheme = (!empty($this->environ['HTTPS']) && $this->environ['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        return $scheme . '://' . $host . $path;
    }
}

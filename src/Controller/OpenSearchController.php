<?php

declare(strict_types=1);

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Controller;

use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Horde\Wicked\Service\UrlGenerator;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 controller for OpenSearch description XML.
 *
 * Routes: OpenSearch (primary: /opensearch)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class OpenSearchController implements RequestHandlerInterface
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $baseUrl = $this->urlGenerator->absoluteUrlFor('Pages', ['page' => '']);
        $name = $this->registry->get('name', 'wicked')
            . ' (' . $baseUrl . ')';

        $iconPath = $this->registry->get('themesfs', 'wicked')
            . '/default/graphics/wicked.png';
        $icon = file_exists($iconPath)
            ? base64_encode(file_get_contents($iconPath))
            : '';

        $searchUrl = htmlspecialchars(
            $this->urlGenerator->absoluteUrlFor('Pages', ['page' => 'Search']),
            ENT_XML1,
            'UTF-8',
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">' . "\n"
            . '  <ShortName>' . htmlspecialchars($name, ENT_XML1, 'UTF-8') . '</ShortName>' . "\n"
            . '  <SearchForm>' . htmlspecialchars($baseUrl, ENT_XML1, 'UTF-8') . '</SearchForm>' . "\n"
            . '  <Url type="text/html"' . "\n"
            . '       method="get"' . "\n"
            . '       template="' . $searchUrl . '?params={searchTerms}"/>' . "\n";

        if ($icon !== '') {
            $xml .= '  <Image height="16" width="16">data:image/png;base64,' . $icon . '</Image>' . "\n";
        }

        $xml .= '  <InputEncoding>UTF-8</InputEncoding>' . "\n"
            . '</OpenSearchDescription>' . "\n";

        $streamFactory = new StreamFactory();
        $response = new Response();

        return $response
            ->withBody($streamFactory->createStream($xml))
            ->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withStatus(200);
    }
}

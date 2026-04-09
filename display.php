<?php
/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * Legacy entry point — delegates to the PSR-15 PageController.
 *
 * @author Tyler Colbert <tyler@colberts.us>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('wicked');

use Horde\Http\ResponseFactory;
use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Wicked\Controller\PageController;

// Build a ServerRequest from PHP globals
$requestBuilder = new RequestBuilder(
    new RequestFactory(),
    new StreamFactory(),
    new UriFactory(),
);
$request = $requestBuilder->withGlobalVariables()->build();

// Map the legacy ?page= query param to the route attribute the controller expects
$pageName = Horde_Util::getFormData('page') ?? 'Wiki/Home';
$request = $request->withAttribute('route', ['page' => $pageName]);

// Construct the controller with its dependencies
$controller = new PageController(
    new ResponseFactory(),
    new StreamFactory(),
    $GLOBALS['notification'],
    $GLOBALS['page_output'],
    $GLOBALS['session'],
    $GLOBALS['wicked'],
);

$response = $controller->handle($request);

// Emit the PSR-7 response
(new ResponseWriterWeb())->writeResponse($response);

<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * Legacy entry point — delegates to the PSR-15 AdminAttachmentsController.
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('wicked');

use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Wicked\Controller\AdminAttachmentsController;

$request = (new RequestBuilder(new RequestFactory(), new StreamFactory(), new UriFactory()))
    ->withGlobalVariables()->build();

$controller = new AdminAttachmentsController(
    $GLOBALS['notification'],
    $GLOBALS['page_output'],
    $GLOBALS['wicked'],
    $GLOBALS['registry'],
);

(new ResponseWriterWeb())->writeResponse($controller->handle($request));

<?php

/**
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * Legacy entry point — delegates to the PSR-15 PreviewController.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('wicked');

use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Wicked\Controller\PreviewController;
use Horde\Wicked\WickedEngine;

$request = (new RequestBuilder(new RequestFactory(), new StreamFactory(), new UriFactory()))
    ->withGlobalVariables()->build();

$controller = new PreviewController(
    $GLOBALS['notification'],
    $GLOBALS['page_output'],
    $GLOBALS['injector']->get(WickedEngine::class),
    $GLOBALS['injector'],
);

(new ResponseWriterWeb())->writeResponse($controller->handle($request));

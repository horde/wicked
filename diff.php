<?php
/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * Legacy entry point — delegates to the PSR-15 DiffController.
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
use Horde\Wicked\Controller\DiffController;

$request = (new RequestBuilder(new RequestFactory(), new StreamFactory(), new UriFactory()))
    ->withGlobalVariables()->build();

$controller = new DiffController(
    $GLOBALS['notification'],
    $GLOBALS['page_output'],
);

(new ResponseWriterWeb())->writeResponse($controller->handle($request));

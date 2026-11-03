<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPL
 * @package Wicked
 * @subpackage UnitTests
 */

use Horde\Injector\Injector;
use Horde\Wicked\WickedConfig;
use PHPUnit\Framework\TestCase;

class Wicked_Unit_Factory_DriverTest extends TestCase
{
    public function testGetDbAcceptsNamespacedInjector(): void
    {
        $db = $this->createMock(Horde_Db_Adapter::class);
        $injector = $this->createMock(Injector::class);
        $injector->expects($this->once())
            ->method('get')
            ->with(WickedConfig::class)
            ->willReturn(new WickedConfig([
                'storage' => [
                    'params' => ['driverconfig' => 'horde'],
                ],
            ]));
        $injector->expects($this->once())
            ->method('getInstance')
            ->with('Horde_Db_Adapter')
            ->willReturn($db);

        $factory = new Wicked_Factory_Driver($injector);

        $this->assertSame($db, $factory->getDb($injector));
    }
}

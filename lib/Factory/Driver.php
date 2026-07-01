<?php

use Horde\Injector\Injector;
use Psr\SimpleCache\CacheInterface;

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */

/**
 * Wicked_Driver factory.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class Wicked_Factory_Driver extends Horde_Core_Factory_Injector
{
    /**
     * @var array
     */
    private $_instances = [];

    /**
     * Return an Wicked_Driver instance.
     *
     * The SQL driver is handed the site-configured PSR-16 cache
     * (Horde\Core\Factory\SimpleCacheFactory) rather than a wicked-owned
     * backend. That backend already honours $conf['cache']['driver'] —
     * HashTable/Redis, APCu, File, SQL, or NullStorage as a no-op fallback —
     * so wicked never needs to know or care what storage it lands on.
     *
     * @param Horde_Injector|Injector $injector  An injector object.
     *
     * @return Wicked_Driver  A driver instance.
     * @throws Wicked_Exception
     */
    public function create(Horde_Injector|Injector $injector)
    {
        $driver = Horde_String::ucfirst($GLOBALS['conf']['storage']['driver']);
        if (empty($driver)) {
            throw new Wicked_Exception('Wicked is not configured');
        }
        $signature = serialize([$driver, $GLOBALS['conf']['storage']['params']['driverconfig']]);
        if (empty($this->_instances[$signature])) {
            $params = [];
            switch ($driver) {
                case 'Sql':
                    $params = [
                        'db' => $this->getDb($injector),
                        'cache' => $injector->getInstance(CacheInterface::class),
                        'allpages_lifetime' => (int) ($GLOBALS['conf']['wicked']['cache']['allpages_lifetime'] ?? 300),
                    ];
                    break;
            }
            $class = 'Wicked_Driver_' . $driver;
            $this->_instances[$signature] = new $class($params);
        }

        return $this->_instances[$signature];
    }

    /**
     * Returns a Horde_Db instance for the SQL backend.
     *
     * @param Horde_Injector|Injector $injector  An injector object.
     *
     * @return Horde_Db_Adapter  A correctly configured Horde_Db_Adapter
     *                           instance.
     * @throws Wicked_Exception
     */
    public function getDb(Horde_Injector $injector)
    {
        try {
            if ($GLOBALS['conf']['storage']['params']['driverconfig'] == 'horde') {
                return $injector->getInstance('Horde_Db_Adapter');
            }
            return $injector->getInstance('Horde_Core_Factory_Db')
                ->create('wicked', 'storage');
        } catch (Horde_Exception $e) {
            throw new Wicked_Exception($e);
        }
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 15:15
 */

namespace AB\Service;


use AB\Manager;

class BaseService implements IService
{
    /**
     * @var Manager
     */
    protected $manager;
    /**
     * @var \AB\Logger
     */
    protected $logger;
    protected $app;

    public function __construct(Manager $manager, array $config)
    {
        $this->logger = $manager->logger;
        $this->manager = $manager;
        $this->app = $config[Manager::RES_CONFIG_APP];
    }

    /**
     * @param Manager $manager
     * @param string $app
     * @return static
     */
    public static function instance(Manager $manager, $app = Manager::COMMON)
    {
        return $manager->getService($app, get_called_class());
    }
}
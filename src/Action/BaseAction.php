<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 11:07
 */

namespace AB\Action;


use AB\Manager;

abstract class BaseAction implements IAction
{
    /**
     * Default is exit on error, override me if needed
     */
    const RET_ERROR = Manager::RET_EXIT;
    const CFG_ERROR_CONTROL = 'error-control';

    protected $manager;
    protected $logger;
    protected $app;
    protected $errorReturnCode;

    public function __construct(Manager $manager, array $config)
    {
        $this->manager = $manager;
        $this->logger = $manager->logger;
        $this->app = $config[Manager::RES_CONFIG_APP];
        $this->errorReturnCode = Manager::readConfig($config, self::CFG_ERROR_CONTROL, static::RET_ERROR);
    }

    /**
     * @param Manager $manager
     * @param string $app
     * @return static
     */
    public static function instance(Manager $manager, $app = Manager::COMMON)
    {
        return $manager->getComponent($app, get_called_class());
    }

    public abstract function run(array $context = []);

}
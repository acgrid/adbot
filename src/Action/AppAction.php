<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 15:10
 */

namespace AB\Action;


use AB\Manager;
use AB\Service\ADB;

abstract class AppAction extends BaseAction
{
    const CFG_PACKAGE_NAME = 'package-name';

    protected $package;
    protected $adb;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $config = $config + $manager->getBaseComponentConfig($this->app);
        $this->package = Manager::readConfig($config, self::CFG_PACKAGE_NAME);

        $this->adb = ADB::instance($manager, $this->app);
    }

    public function stopApp()
    {
        $this->adb->stopActivity($this->package);
    }

}
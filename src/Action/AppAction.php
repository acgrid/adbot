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
use AB\Service\Input;
use AB\Service\LoadingDetection;
use AB\Service\Screen;

abstract class AppAction extends BaseAction
{
    const CFG_PACKAGE_NAME = 'package-name';

    const SHARED_ASSURED_STATUS = 'status';
    /**
     * Java Package name of application
     * @var string
     */
    protected $package;
    /**
     * @var ADB
     */
    protected $adb;
    /**
     * @var LoadingDetection
     */
    protected $detector;
    /**
     * @var Screen
     */
    protected $screen;
    /**
     * @var Input
     */
    protected $input;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $config = $config + $manager->getBaseComponentConfig($this->app);
        $this->package = Manager::readConfig($config, self::CFG_PACKAGE_NAME);
        $this->adb = ADB::instance($manager, $this->app);
        $this->detector = LoadingDetection::instance($manager, $this->app);
        $this->screen = Screen::instance($manager, $this->app);
        $this->input = Input::instance($manager, $this->app);
    }

    public function stopApp()
    {
        $this->adb->stopActivity($this->package);
    }

    public function getData($key, $default = '')
    {
        return $this->manager->getAppShared($this->app, $key, $default);
    }

    public function setData($key, $value)
    {
        return $this->manager->setConstant($key, $value);
    }

}
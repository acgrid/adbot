<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;
use AB\ADB\ADBCmd;

class StopApp implements ActionInterface
{
    const CONFIG_APPNAME = 'package';

    public function run(Manager $context, array $config)
    {
        if(!isset($config[self::CONFIG_APPNAME])) throw new \InvalidArgumentException('No game selected to stop.');
        $adb = ADBCmd::instance($context);
        $adb->stopActivity($config[self::CONFIG_APPNAME]);
    }
}

?>
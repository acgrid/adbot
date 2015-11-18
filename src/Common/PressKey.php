<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;
use AB\ADB\ADBCmd;

class PressKey implements ActionInterface
{
    const CONFIG_EVENTCODE = 'keyevent';

    public function run(Manager $context, array $config)
    {
        if(!isset($config[self::CONFIG_EVENTCODE])) throw new \InvalidArgumentException('A key event is required.');
        $adb = ADBCmd::instance($context);
        $adb->execHost('shell input keyevent %s', $config[self::CONFIG_EVENTCODE]);
        if($adb->returnCode === 0){
            $context->logger->notice('Press Key Event %u OK', [$config[self::CONFIG_EVENTCODE]]);
            return Manager::RET_LOOP;
        }else{
            $context->logger->warning('Press Home Key return code %u.', [$adb->returnCode]);
        }
    }
}

?>
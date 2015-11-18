<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;
use AB\ADB\ADBCmd;

class PressKey implements ActionInterface
{
    const CONFIG_EVENTCODE = 'keyevent';
    public $eventCode;
    
    public function __construct(Manager $context, array $config)
    {
        if(!isset($config[self::CONFIG_EVENTCODE])) throw new \InvalidArgumentException('A key event is required.');
        $this->eventCode = $config[self::CONFIG_EVENTCODE];        
    }

    public function run(Manager $context)
    {
        $adb = ADBCmd::instance($context);
        $adb->execHost('shell input keyevent %s', $this->eventCode);
        if($adb->returnCode === 0){
            $context->logger->notice('Press Home Key OK');
            return Manager::RET_LOOP;
        }else{
            $context->logger->warning('Press Home Key return code %u.', [$adb->returnCode]);
        }
    }
}

?>
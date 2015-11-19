<?php
namespace AB\SGS;

use AB\ActionInterface;
use AB\Manager;
use AB\ADB\ADBCmd;
use AB\OCR\ScreenFacade;

class LaunchApp implements ActionInterface
{
    const CONFIG_MENU = 'menu';
    const CONFIG_APP = 'app';
    
    public function run(Manager $context, array $config)
    {
        $adb = ADBCmd::instance($context);
        $scr = ScreenFacade::instance($context);
        $base = $context->getGameBase(__NAMESPACE__);
        $pos = $base->helperPosition();
        $delay = $base->helperDelay();
        
        if(isset($config[self::CONFIG_MENU]) && ADBCmd::assertRect($config[self::CONFIG_MENU])){
            $position = isset($config[self::CONFIG_MENU]['position']) ? $config[self::CONFIG_MENU]['position'] : null;
            $adb->tapXY($pos->pointInRect($scr->absRect($config[self::CONFIG_MENU], $position)));
            $delay = $delay->delayBoundaryRandom(400, 1000);
        }
        if(!isset($config[self::CONFIG_APP])) throw new \InvalidArgumentException("App Position is not defined!");
        $position = isset($config[self::CONFIG_APP]['position']) ? $config[self::CONFIG_APP]['position'] : null;
        $adb->tapXY($pos->pointInRect($scr->absRect($config[self::CONFIG_APP], $position)));
        $delay = $delay->delayBoundaryRandom(1000, 2000);
    }
}

?>
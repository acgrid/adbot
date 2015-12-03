<?php
namespace AB\SGS;

use AB\GameManagerInterface;
use AB\Manager;
use AB\OCR\ScreenFacade;
use AB\ADB\ADBCmd;

class Base implements GameManagerInterface
{
    private $gameName;
    private $packageName;
    private $delay;
    private $position;
    private $errors;
    private $context;
    private $distance;
    
    const CONFIG_PACKAGE = 'package';
    const CONFIG_ERRORS = 'errors';
    const CONFIG_COLOR_DISTANCE = 'color-distance';
    
    public function __construct(Manager $context, array $config)
    {
        $this->context = $context;
        $this->gameName = substr(__NAMESPACE__, strpos(__NAMESPACE__, '\\') + 1);
        if(!isset($config[self::CONFIG_PACKAGE])) throw new \InvalidArgumentException("Missing package name in configuration for game {$this->gameName}.");
        if(!isset($config[self::CONFIG_ERRORS]) || !is_array($config[self::CONFIG_ERRORS])) throw new \InvalidArgumentException("Missing game error definitions in configuration for game {$this->gameName}.");
        $this->packageName = $config[self::CONFIG_PACKAGE];
        $this->errors = $config[self::CONFIG_ERRORS];
        $this->distance = isset($config[self::CONFIG_COLOR_DISTANCE]) ? intval($config[self::CONFIG_COLOR_DISTANCE]) : 16;
        $context->logger->info('Game %s (package %s) initialization', [$this->gameName, $this->packageName]);
        $this->delay = $context->getService($this->gameName, 'Random\Delay');
        $this->position = $context->getService($this->gameName, 'Random\Position');
    }

    public function getAppPackageName()
    {
        return $this->packageName;
    }

    public function getGameName()
    {
        return $this->gameName;
    }
    
    public function helperDelay()
    {
        return $this->delay;        
    }
    
    public function helperPosition()
    {
        return $this->position;
    }
    
    public function processErrors()
    {
        $scr = ScreenFacade::instance($this->context);
        $adb = ADBCmd::instance($this->context);
        $pos = $this->helperPosition();
        foreach($this->errors as $error){
            if(!isset($error['tests'])) continue;
            if($scr->compareDots($error['tests'], $this->distance)){
                if(isset($error['tap'])) $adb->tapXY($pos->pointInRect($error['tap']));
                return true;
            }
        }
        return false;
    }
}

?>
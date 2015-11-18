<?php
namespace AB\SGS;

use AB\GameManagerInterface;
use AB\Manager;

class Base implements GameManagerInterface
{
    private $gameName;
    private $packageName;
    private $delay;
    private $position;
    
    const CONFIG_PACKAGE = 'package';
    
    public function __construct(Manager $context, array $config)
    {
        $this->gameName = substr(__NAMESPACE__, strpos(__NAMESPACE__, '\\') + 1);
        if(!isset($config[self::CONFIG_PACKAGE])) throw new \InvalidArgumentException("Missing package name in configuration for game {$this->gameName}.");
        $this->packageName = $config[self::CONFIG_PACKAGE];
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
}

?>
<?php
namespace AB;

use AB\Logger\Logger;
use AB\ADB\ADBCmd;

/**
 * @property-read AB\Logger\Logger $logger
 * @property-read string $path
 * @author acgrid
 *
 */
final class Manager
{

    const RET_LOOP = 0;
    const RET_CONTINUE_LOOP = 1;
    const RET_BREAK_LOOP = 2;
    const RET_AGAIN = 3;
    const RET_FINISH = 4;
    const RET_EXIT = 5;

    const CFG_TITLE = 'title';
    const CFG_VERSION = 'version';
    const CFG_CONST = 'constants';
    const CFG_COMM = 'commons';
    const CFG_GAMES = 'games';
    const CFG_ACTIONS = 'actions';
    
    const ACTION_INIT = 'init';
    const ACTION_LOOP = 'loop';
    const ACTION_FINAL = 'final';

    private $CONFIG;
    private $logger;
    private $constants;
    private $actions = []; // [PHASE][index]
    private $services; // [GAME][CLASS_NAME] => array
    private $serviceObjects = []; // [GAME][CLASS_NAME] => object
    private $persistObjects = []; // [CLASS]

    private function __construct(array $config, Logger $logger)
    {
        $this->CONFIG = $config;
        $this->constants = $config[self::CFG_CONST];
        $this->services = $config[self::CFG_COMM];
        foreach($config[self::CFG_GAMES] as $game => $gameConfig) $this->services[$game] = $this->mergeArray($gameConfig, $config[self::CFG_COMM]);
        $this->actions = $config[self::CFG_ACTIONS];
        
        $this->logger = $logger;
    }

    public function __get($name)
    {
        if($name === 'logger') return $this->logger;
        if($name === 'path'){
            static $path;
            return isset($path) ? $path : ($path = dirname(__DIR__));
        }
    }
    
    protected function mergeArray(array $special, array &$common)
    {
        $special = $special + $common;
        foreach(array_keys($special) as $key){
            if(is_array($special[$key]) && is_array($common[$key])) $special[$key] = $this->mergeArray($special[$key], $common[$key]);
        }
        return $special;
    }

    public function getService($game, $class)
    {
        if(!isset($this->serviceObjects[$game][$class])){
            $realClass = __NAMESPACE__ . '\\' . $class;
            if(!class_exists($realClass, true)) throw new \InvalidArgumentException("Class $class does not exist.");
            if(isset($this->services[$game]) && isset($this->services[$game][$class])){
                $this->serviceObjects[$game][$class] = new $realClass($this, $this->services[$game][$class]);
            }else{ // default instance
                $this->serviceObjects[$game][$class] = new $realClass($this);
            }
        }
        return $this->serviceObjects[$game][$class];
    }

    public function getConstant($name, $default = null)
    {
        if(isset($this->constants[$name])) return $this->constants[$name];
        if(isset($default)) return $default;
        throw new \InvalidArgumentException("Undefined constant '$name' required.");
    }
    
    public function setConstant($name, $value)
    {
        $this->constants[$name] = $value;
        return $this;
    }
    
    protected function doAction(array $action)
    {
        /**
         * @var $actionObject ActionInterface
         */
        if(!isset($action['class'])) throw new \InvalidArgumentException("Action without class name encountered.");
        $newInstance = substr($action['class'], 0, 1) === '@';
        $className = __NAMESPACE__ . '\\' . ($newInstance ? substr($action['class'], 1) : $action['class']);
        unset($action['class']);
        if($newInstance || !isset($this->persistObjects[$className])){
            if(!class_exists($className, true)) throw new \InvalidArgumentException("Class '$className' does not exist.");
            $actionObject = new $className($this, $action);
            if(!($actionObject instanceof ActionInterface)) throw new \InvalidArgumentException("Class '$className' does not implement ActionInterface");
            if(!$newInstance) $this->persistObjects[$className] = $actionObject;
        }else{
            $actionObject = $this->persistObjects[$className];
        }
        return $actionObject->run($this, $action);
    }

    public function start()
    {
        if(isset($this->actions[self::ACTION_INIT])){
            $this->logger->notice('Running initialization actions.');
            foreach($this->actions[self::ACTION_INIT] as $action){
                do{
                    $result = $this->doAction($action);
                }while($result === self::RET_AGAIN);
                if($result === self::RET_BREAK_LOOP){
                    break;
                }elseif($result === self::RET_CONTINUE_LOOP){
                    continue;
                }elseif($result === self::RET_EXIT){
                    return $result;
                }
            }
        }
        if(isset($this->actions[self::ACTION_LOOP])){
            $this->logger->notice('Entering main loop.');
            while(true){
                foreach($this->actions[self::ACTION_LOOP] as $action){
                    do{
                        $result = $this->doAction($action);
                    }while($result === self::RET_AGAIN);
                    if($result === self::RET_BREAK_LOOP){
                        break;
                    }elseif($result === self::RET_CONTINUE_LOOP){
                        continue;
                    }elseif($result === self::RET_FINISH){
                        break 2;
                    }elseif($result === self::RET_EXIT){
                        return $result;
                    }
                }
            }
        }
        if(isset($this->actions[self::ACTION_FINAL])){
            $this->logger->notice('Running finaliztion actions.');
            foreach($this->actions[self::ACTION_FINAL] as $action){
                do{
                    $result = $this->doAction($action);
                }while($result === self::RET_AGAIN);
                if($result === self::RET_BREAK_LOOP){
                    break;
                }elseif($result === self::RET_CONTINUE_LOOP){
                    continue;
                }elseif($result === self::RET_EXIT){
                    return $result;
                }
            }
        }
        return self::RET_LOOP;
    }

    public static function run(array $config, Logger $logger)
    {
        foreach([
            self::CFG_ACTIONS,
            self::CFG_COMM,
            self::CFG_CONST,
            self::CFG_GAMES,
            self::CFG_TITLE,
            self::CFG_VERSION
        ] as $cfg_key){
            if(!isset($config[$cfg_key])) throw new \InvalidArgumentException("Error: missing configuration main item '{$cfg_key}'.");
        }
        $logger->info("Preparing for script '%s' version %s.", [$config[self::CFG_TITLE], $config[self::CFG_VERSION]]);
        return (new self($config, $logger))->start();
    }
}

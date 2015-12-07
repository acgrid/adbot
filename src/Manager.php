<?php
namespace AB;

use AB\Action\IAction;

/**
 * Class Manager
 *
 * @package AB
 * @property-read Logger $logger
 * @property-read string $path
 * @property-read string $nsService
 * @property-read string $nsAction
 * @author acgrid
 */
final class Manager
{

    /**
     * Continue to next action as normal process
     */
    const RET_LOOP = 0;
    /**
     * Not used at least.
     * If an infinite redo is favoured, just call action itself. Do not use RET_AGAIN.
     */
    const RET_SELF = 1;
    /**
     * Break rest actions and restart the main loop
     * Equivalent to RET_FINISH in initialization and finalization
     */
    const RET_NEXT_LOOP = 2;
    /**
     * Redo previous action, there is a maximum retries limit
     */
    const RET_AGAIN = 3;
    /**
     * End current section, enter main loop or finalization
     */
    const RET_FINISH = 4;
    /**
     * Abort script, no more actions are executed
     */
    const RET_EXIT = 5;

    const ACTION_RETRY_LIMIT = 3;
    const ACTION_RETRY_DELAY = 1000;

    const CFG_TITLE = 'title';
    const CFG_VERSION = 'version';
    const CFG_CONST = 'constants';
    const CFG_SERVICE = 'services';
    const CFG_COMPONENTS = 'components';
    const CFG_ACTIONS = 'actions';

    /**
     * Namespace and JSON key for common and fail back use
     */
    const COMMON = 'Common';
    const NS_SERVICE = 'Service';
    const NS_ACTION = 'Action';

    const RES_CONFIG_CLASS = 'class';
    const RES_CONFIG_METHOD = 'method';
    const RES_CONFIG_APP = 'app';
    const RES_CONFIG_RETRY = 'retry';
    const RES_CONFIG_RETRY_DELAY = 'retry-delay';
    
    const ACTION_INIT = 'init';
    const ACTION_LOOP = 'loop';
    const ACTION_FINAL = 'final';

    private $CONFIG;
    private $logger;
    private $constants;
    private $components;
    private $actions = []; // [PHASE][index]
    private $services = []; // [GAME][CLASS_NAME] => array
    /**
     * @var array [app][class]
     */
    private $serviceObjects = [];
    private $componentObjects = [];

    private static $serviceClassNameCache = [];
    private static $componentClassNameCache = [];

    private static $serviceNamespace;
    private static $actionNamespace;

    private function __construct(array &$config, Logger $logger)
    {
        $this->CONFIG = $config;
        $this->constants = $config[self::CFG_CONST];
        $this->services = $config[self::CFG_SERVICE];
        $this->components = $config[self::CFG_COMPONENTS];
        $this->actions = $config[self::CFG_ACTIONS];
        $this->logger = $logger;

        self::$serviceNamespace = __NAMESPACE__ . '\\' . self::NS_SERVICE . '\\';
        self::$actionNamespace = __NAMESPACE__ . '\\' . self::NS_ACTION . '\\';
    }

    public function __get($name)
    {
        if($name === 'logger') return $this->logger;
        if($name === 'path'){
            static $path;
            return isset($path) ? $path : ($path = dirname(__DIR__));
        }
        if($name === 'nsService') return self::$serviceNamespace;
        if($name === 'nsAction') return self::$actionNamespace;
        return null;
    }

    public static function readConfig(&$config, $key, $default = null)
    {
        if(isset($config[$key])) return $config[$key];
        if(isset($default)) return $default;
        throw new \RuntimeException("Configuration item '$key' is required.");
    }

    /**
     * Read standard callback or [class, method, ...constructor] array
     * Return [$object, 'method']
     * @param callback|array $config
     * @param string $app
     * @return callback
     */
    public function readCallback($config, $app = self::COMMON)
    {
        if(is_callable($config)) return $config;
        if(!is_array($config) || !isset($config[self::RES_CONFIG_CLASS])) throw new \InvalidArgumentException("Callback definition requires 'class' key.");
        $class = $config[self::RES_CONFIG_CLASS];
        if(!class_exists($class, true)) throw new \InvalidArgumentException("Callback class $class is not found, try use full namespace.");
        unset($config[self::RES_CONFIG_CLASS]);
        if(is_subclass_of($class, self::$serviceNamespace . 'IService', true) || is_subclass_of($class, self::$actionNamespace . 'IAction', true)){
            $config[Manager::RES_CONFIG_APP] = $app;
        }
        $object = new $class($this, $config);
        if(isset($config[self::RES_CONFIG_METHOD])){
            $method = $config[self::RES_CONFIG_METHOD];
            $callback = [$object, $method];
            unset($config[self::RES_CONFIG_METHOD]);
        }else{
            $method = '__invoke';
            $callback = $object;
        }
        if(!method_exists($object, $method)) throw new \InvalidArgumentException("Callback method $class::$method() does not exist.");
        return $callback;
    }


    /**
     * Make a combined array with a common one and special version based by key check
     * Reversed for future use
     *
     * @param array $special
     * @param array $common
     * @return array
     */
    protected static function mergeArray(array $special, array &$common)
    {
        $special = $special + $common;
        foreach(array_keys($special) as $key){
            if(isset($special[$key]) && isset($common[$key]) && is_array($special[$key]) && is_array($common[$key])) $special[$key] = self::mergeArray($special[$key], $common[$key]);
        }
        return $special;
    }

    public function getConstant($name, $default = null)
    {
        if(isset($this->constants[$name])) return $this->constants[$name];
        if(isset($default)) return $default;
        throw new \RuntimeException("Constant '$name' is required.");
    }

    public function setConstant($name, $value)
    {
        $this->constants[$name] = $value;
        return $this;
    }

    protected static function getServiceClassName($class)
    {
        if(isset(self::$serviceClassNameCache[$class])) return self::$serviceClassNameCache[$class];
        $realClass = strpos($class, self::$serviceNamespace) === 0 ? $class : self::$serviceNamespace . $class;
        if(!class_exists($realClass, true)) throw new \InvalidArgumentException("Service $class does not exist.");
        return self::$serviceClassNameCache[$class] = $realClass;
    }

    public function getServiceConfig($app, $class)
    {
        if(isset($this->services[$app]) && isset($this->services[$app][$class])){
            $this->services[$app][$class][self::RES_CONFIG_APP] = $app;
            return $this->services[$app][$class];
        }else{
            return [self::RES_CONFIG_APP => self::COMMON];
        }
    }

    /**
     * @param $app
     * @param $class
     * @return Service\IService
     */
    public function getService($app, $class)
    {
        if(strpos($class, self::$serviceNamespace) === 0) $class = substr($class, strlen(self::$serviceNamespace));
        if(!isset($this->serviceObjects[$app][$class])){
            $realClass = self::getServiceClassName($class);
            $config = $this->getServiceConfig($app, $class);
            if($config[self::RES_CONFIG_APP] === self::COMMON){ // fall back to common object
                if(!isset($this->serviceObjects[self::COMMON][$class])){
                    $this->serviceObjects[self::COMMON][$class] = new $realClass($this, $config);
                }
                $this->serviceObjects[$app][$class] = $this->serviceObjects[self::COMMON][$class];
            }else{
                $this->serviceObjects[$app][$class] = new $realClass($this, $config);
            }
        }
        return $this->serviceObjects[$app][$class];
    }

    protected static function getActionClassName($app, $class)
    {
        if($app != self::COMMON) $class = str_replace(self::COMMON . '\\', "$app\\", $class); // app-specific action first
        $relativeClass = "$app\\$class";
        if(isset(self::$componentClassNameCache[$relativeClass])) return self::$componentClassNameCache[$relativeClass];
        $realClass = self::$actionNamespace . $relativeClass;
        return self::$componentClassNameCache[$relativeClass] = class_exists($realClass, true) ? $realClass : false;
    }

    public function getComponent($app, $class, $temporary = false)
    {
        if(strpos($class, self::$actionNamespace) === 0) $class = substr($class, strlen(self::$actionNamespace));
        if($temporary || !isset($this->componentObjects[$app][$class])){
            $config = isset($this->components[$app]) && isset($this->components[$app][$class]) ? $this->components[$app][$class] :
                (isset($this->components[self::COMMON][$class]) ? $this->components[self::COMMON][$class] : []);
            $config[self::RES_CONFIG_APP] = $app;
            if(($realClass = self::getActionClassName($app, $class)) || ($realClass = self::getActionClassName(self::COMMON, $class))){
                $component = new $realClass($this, $config);
                if($temporary) return $component;
                $this->componentObjects[$app][$class] = $component;
            }else{
                throw new \InvalidArgumentException("Action $class can not be mapped.");
            }
        }
        return $this->componentObjects[$app][$class];
    }

    /**
     * Warning! If class itself contains namespaces, app prefix including Common must be added.
     * Otherwise first namespace will be taken as the app identifier
     * @param $class
     * @return Action\IAction
     */
    public function getAction($class)
    {
        $newInstance = substr($class, 0, 1) === '@';
        if($newInstance) $class = substr($class, 1);
        $app = ($pos = strpos($class, '\\')) === false ? self::COMMON : substr($class, 0, $pos);
        if($pos) $class = substr($class, $pos + 1);
        return $this->getComponent($app, $class, $newInstance);
    }
    
    protected function doAction(&$action)
    {
        $config = [];
        if(is_string($action)){
            $actionObject = $this->getAction($action);
        }elseif(is_array($action) && isset($action[self::RES_CONFIG_CLASS])){
            $actionObject = $this->getAction($action[self::RES_CONFIG_CLASS]);
            unset($action[self::RES_CONFIG_CLASS]);
            $config = &$action;
        }else{
            throw new \InvalidArgumentException("Action class is not defined, either plain string or array with key 'class'.");
        }
        if(!($actionObject instanceof IAction)) throw new \InvalidArgumentException("Class '" . get_class($actionObject) . "' does not implement ActionInterface");
        $retry = 0;
        $retryLimit = self::readConfig($config, self::RES_CONFIG_RETRY, self::ACTION_RETRY_LIMIT);
        $retryDelay = self::readConfig($config, self::RES_CONFIG_RETRY_DELAY, self::ACTION_RETRY_DELAY);
        do{
            if($retry){
                $this->logger->notice('Action %s wanted to retry, %u attempts remaining.', [$action, $retryLimit - $retry]);
                usleep($retryDelay);
            }
            $result = $actionObject->run($config);
        }while($result === self::RET_AGAIN && $retry < $retryLimit);
        return $result;
    }

    public function start()
    {
        $this->logger->notice('Script execution will be started.');
        if(isset($this->actions[self::ACTION_INIT])){
            $this->logger->notice('Running initialization actions.');
            foreach($this->actions[self::ACTION_INIT] as $action){
                switch($result = $this->doAction($action)){
                    case self::RET_NEXT_LOOP:
                    case self::RET_FINISH: break 2;
                    case self::RET_EXIT: return $result;
                    case self::RET_LOOP:
                    default: ;
                }
            }
        }
        if(isset($this->actions[self::ACTION_LOOP])){
            $loop = 1;
            while(true){
                $this->logger->notice('Running main loop cycle %u.', [$loop++]);
                foreach($this->actions[self::ACTION_LOOP] as $action){
                    switch($result = $this->doAction($action)){
                        case self::RET_NEXT_LOOP: break 2;
                        case self::RET_FINISH: break 3;
                        case self::RET_EXIT: return $result;
                        case self::RET_LOOP:
                        default: ;
                    }
                }
            }
        }
        if(isset($this->actions[self::ACTION_FINAL])){
            $this->logger->notice('Running finalization actions.');
            foreach($this->actions[self::ACTION_FINAL] as $action){
                switch($result = $this->doAction($action)){
                    case self::RET_NEXT_LOOP:
                    case self::RET_FINISH: break 2;
                    case self::RET_EXIT: return $result;
                    case self::RET_LOOP:
                    default: ;
                }
            }
        }
        $this->logger->notice('Script execution completed successfully.');
        return self::RET_LOOP;
    }

    public static function factory(array $config, Logger $logger)
    {
        foreach([
                    self::CFG_ACTIONS,
                    self::CFG_SERVICE,
                    self::CFG_CONST,
                    self::CFG_COMPONENTS,
                    self::CFG_TITLE,
                    self::CFG_VERSION
                ] as $cfg_key){
            if(!isset($config[$cfg_key])) throw new \InvalidArgumentException("Error: Missing mandatory configuration item '{$cfg_key}'.");
        }
        $logger->info("Loading script '%s' version %s.", [$config[self::CFG_TITLE], $config[self::CFG_VERSION]]);
        return new self($config, $logger);
    }

    public static function run(array $config, Logger $logger)
    {
        return self::factory($config, $logger)->start();
    }
}

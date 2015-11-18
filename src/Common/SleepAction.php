<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class SleepAction implements ActionInterface
{
    const CONFIG_FROM_MS = 'FromMS';
    const CONFIG_TILL_MS = 'TillMS';
    private $fromMS;
    private $tillMS;
    
    public function __construct(Manager $context, array $config)
    {
        $this->fromMS = isset($config[self::CONFIG_FROM_MS]) ? $config[self::CONFIG_FROM_MS] : 1000;
        $this->tillMS = isset($config[self::CONFIG_TILL_MS]) ? $config[self::CONFIG_TILL_MS] : 2000;
    }
    
    public function run(Manager $context)
    {
        /**
         * @var AB\Random\Delay;
         */
        $delay = $context->getService('Common', 'Random\\Delay');
        $delay->delayBoundaryRandom($this->fromMS, $this->tillMS);
    }
}

?>
<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class SleepAction implements ActionInterface
{
    const CONFIG_FROM_MS = 'FromMS';
    const CONFIG_TILL_MS = 'TillMS';
    
    public function run(Manager $context, array $config)
    {
        /**
         * @var AB\Random\Delay;
         */
        $delay = $context->getService('Common', 'Random\\Delay');
        $delay->delayBoundaryRandom(
            isset($config[self::CONFIG_FROM_MS]) ? $config[self::CONFIG_FROM_MS] : 1000,
            isset($config[self::CONFIG_TILL_MS]) ? $config[self::CONFIG_TILL_MS] : 2000);
        return Manager::RET_LOOP;
    }
}

?>
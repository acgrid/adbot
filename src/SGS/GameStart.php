<?php
namespace AB\SGS;

use AB\ActionInterface;
use AB\Manager;

class GameStart implements ActionInterface
{
    const CONFIG_TESTS = 'tests';

    /**
     * @param Manager $context
     * @param array $config
     */
    public function run(Manager $context, array $config)
    {
        if(!isset($config[self::CONFIG_TESTS])) throw new \InvalidArgumentException('No test configuration provided.');
        $base = $context->getGameBase(__NAMESPACE__);
        $delay = $base->helperDelay();
        while($base->processErrors() || !$this->test($config[self::CONFIG_TESTS])){
            $delay->delayCentralRandom(2, 5);
        }
    }
    
    protected function test($tests)
    {
        
    }
}

?>
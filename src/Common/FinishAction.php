<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class FinishAction implements ActionInterface
{
    const CONFIG_COUNT = 'count';
    
    private $leftCount;
    
    public function __construct(Manager $context, array $config)
    {
        $this->leftCount = isset($config[self::CONFIG_COUNT]) && intval($config[self::CONFIG_COUNT]) ? intval($config[self::CONFIG_COUNT]) : 1;
        $context->logger->info('Finish the script after %u loop(s).', [$this->leftCount]);
    }

    public function run(Manager $context, array $config)
    {
        return --$this->leftCount ? Manager::RET_LOOP : Manager::RET_FINISH;
    }
}

?>
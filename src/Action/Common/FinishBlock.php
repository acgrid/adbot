<?php
namespace AB\Action\Common;

use AB\Action\BaseAction;
use AB\Manager;

class FinishBlock extends BaseAction
{
    const CFG_COUNT = 'count';
    
    private $leftCount;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->leftCount = Manager::readConfig($config, self::CFG_COUNT, 0);
        if(!is_int($this->leftCount) || $this->leftCount < 0){
            $this->leftCount = 0;
            $this->logger->warn("%s: A non-negative integer as tick count is required, assume 0.", [__CLASS__]);
        }
    }

    public function run(array $context = [])
    {
        if($this->leftCount > 1) $this->logger->info('This script will enter next phase when hit this checkpoint more %u times later.', [$this->leftCount]);
        if($this->leftCount === 1) $this->logger->warn('This script will enter next phase at next time on this checkpoint!');
        if($this->leftCount === 0) $this->logger->info('This script will enter next phase now due to a count control.');
        return $this->leftCount-- ? Manager::RET_LOOP : Manager::RET_FINISH;
    }
}

?>
<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class FinishAction implements ActionInterface
{

    public function run(Manager $context)
    {
        return Manager::RET_FINISH;
    }
}

?>
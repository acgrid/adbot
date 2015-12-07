<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 16:41
 */

namespace AB\Action\Common;


use AB\Action\BaseAction;
use AB\Manager;

class AbortExecution extends BaseAction
{
    public function run(array $context = [])
    {
        if(!empty($context)) $this->manager->getAction('PrintMessage')->run($context);
        return Manager::RET_EXIT;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 16:07
 */

namespace AB\Action\TestApp;


use AB\Action\BaseAction;
use AB\Manager;

class RetryAction extends BaseAction
{
    public function run(array $context = [])
    {
        return Manager::RET_AGAIN;
    }

}
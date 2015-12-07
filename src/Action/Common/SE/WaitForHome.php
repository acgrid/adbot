<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 17:39
 */

namespace AB\Action\Common\SE;


use AB\Action\AppAction;
use AB\Manager;

class WaitForHome extends AppAction
{

    public function run(array $context = [])
    {
        return Manager::RET_LOOP;
    }

}
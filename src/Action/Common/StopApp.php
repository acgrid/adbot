<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 17:28
 */

namespace AB\Action\Common;


use AB\Action\AppAction;

class StopApp extends AppAction
{
    public function run(array $context = [])
    {
        $this->logger->info('Send app stopping signal.');
        return $this->stopApp();
    }

}
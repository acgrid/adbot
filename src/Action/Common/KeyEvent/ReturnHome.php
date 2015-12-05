<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 16:47
 */

namespace AB\Action\Common\KeyEvent;


class ReturnHome extends Base
{
    const DEFAULT_EVENT_CODE = self::CODE_HOME;

    public function run(array $context = [])
    {
        $this->logger->info('Press Home Key');
        return parent::run($context);
    }


}
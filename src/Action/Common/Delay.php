<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 18:11
 */

namespace AB\Action\Common;


use AB\Action\BaseAction;
use AB\Service\Delay as DelayService;
use AB\Manager;

class Delay extends BaseAction
{
    const CFG_DELAY_BASE = 'base';
    const CFG_DELAY_OFFSET = 'offset';
    /**
     * @var DelayService
     */
    private $delay;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->delay = DelayService::instance($manager, $this->app);
    }

    public function run(array $context = [])
    {
        if(isset($context[self::CFG_DELAY_BASE]) && isset($context[self::CFG_DELAY_OFFSET]) && is_int($context[self::CFG_DELAY_BASE]) && is_int($context[self::CFG_DELAY_OFFSET])){
            $this->delay->delayOffset($context[self::CFG_DELAY_BASE], $context[self::CFG_DELAY_OFFSET]);
        }else{
            $this->logger->warning('%s: No valid delay param found! Use default value instead.', [__CLASS__]);
            $this->delay->delayOffset(1000, 500);
        }
    }

}
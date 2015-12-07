<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 14:13
 */

namespace AB\Action\Common;

use AB\Action\BaseAction;
use AB\Manager;

class TestAction extends BaseAction
{
    const CFG_TEST = 'test';
    const DEFAULT_VALUE = 'default';

    public $test;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->test = Manager::readConfig($config, self::CFG_TEST, static::DEFAULT_VALUE);
    }

    public function run(array $context = [])
    {
        $this->test = Manager::readConfig($context, self::CFG_TEST, $this->test);
        return Manager::RET_LOOP;
    }

}
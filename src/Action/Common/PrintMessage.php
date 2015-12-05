<?php
namespace AB\Action\Common;

use AB\Action\BaseAction;
use AB\Manager;

class PrintMessage extends BaseAction
{
    const CFG_MESSAGE = 'message';

    private $message;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->message = Manager::readConfig($config, self::CFG_MESSAGE, 'Test output');
    }

    public function run(array $context = [])
    {
        $this->logger->info(isset($context[self::CFG_MESSAGE]) ? $context[self::CFG_MESSAGE] : $this->message);
        return Manager::RET_LOOP;
    }
}
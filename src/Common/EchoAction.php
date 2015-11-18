<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class EchoAction implements ActionInterface
{
    const CONFIG_MESSAGE = 'message';

    public function run(Manager $context, array $config)
    {
        $context->logger->info(isset($config[self::CONFIG_MESSAGE]) ? $config[self::CONFIG_MESSAGE] : 'Check point');
        return Manager::RET_LOOP;
    }
}

?>
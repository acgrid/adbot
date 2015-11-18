<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;

class EchoAction implements ActionInterface
{
    const CONFIG_MESSAGE = 'message';
    private $message;
    
    public function __construct(Manager $context, array $config)
    {
        $this->message = isset($config[self::CONFIG_MESSAGE]) ? $config[self::CONFIG_MESSAGE] : 'Check point';
    }

    public function run(Manager $context)
    {
        $context->logger->info($this->message);
    }
}

?>
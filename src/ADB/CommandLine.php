<?php
namespace AB\ADB;

use AB\Manager;

class CommandLine
{
    public $returnCode;
    public $output;
    /**
     * @var AB\Logger\Logger
     */
    protected $logger;
    
    public function __construct(Manager $context)
    {
        $this->logger = $context->logger;
    }
    
    public function exec($cmd)
    {
        $this->logger->info('CMD %s', [$cmd]);
        ob_start();
        passthru($cmd, $this->returnCode);
        $this->output = ob_get_clean();
    }
    
    public function execFormat($cmd, ...$params)
    {
        $this->logger->debug("Building command %s with %s", [$cmd, var_export($params, true)]);
        $cmd = vsprintf($cmd, array_map('escapeshellarg', $params));
        $this->exec($cmd);
    }
}

?>
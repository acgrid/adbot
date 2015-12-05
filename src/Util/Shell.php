<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 11:19
 */

namespace AB\Util;


use AB\Logger;

/**
 * Class Shell
 * @package AB\Util
 */
class Shell
{
    public $returnCode;
    public $output;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function exec($cmd)
    {
        $this->logger->notice('SHELL %s', [$cmd]);
        ob_start();
        passthru($cmd, $this->returnCode);
        $this->output = ob_get_clean();
    }

    public function execFormat($cmd, ...$params)
    {
        $this->logger->debug("Building command %s with %s", [$cmd, join(',', $params)]);
        $cmd = vsprintf($cmd, array_map('escapeshellarg', $params));
        $this->exec($cmd);
    }
}
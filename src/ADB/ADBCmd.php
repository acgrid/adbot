<?php
namespace AB\ADB;

use AB\Manager;
use AB\ServiceInterface;

class ADBCmd extends CommandLine implements ServiceInterface
{
    const CONST_ADB_HOST = 'ADB_TCPIP';
    const CONST_ADB_BIN = 'ADB_PATH';
    
    public $bin;
    public $host;
    
    public function __construct(Manager $context)
    {
        parent::__construct($context);
        $this->bin = $context->getConstant(self::CONST_ADB_BIN, 'adb');
        $this->host = $context->getConstant(self::CONST_ADB_HOST);
        
    }
     
     /* (non-PHPdoc)
      * @see \AB\ServiceInterface::instance()
      */
    public static function instance(Manager $context) {
        static $instance;
        if(!isset($instance)){
            $instance = new self($context);
        }
        return $instance;
    }

    /**
     * (non-PHPdoc)
     * @see \AB\ADB\CommandLine::exec()
     */
    public function exec($cmd)
    {
        parent::exec(escapeshellarg($this->bin) . ' ' . $cmd);
    }
    
    public function execHost($cmd, ...$param)
    {
        array_unshift($param, $this->host);
        $this->execFormat("-s %s $cmd", ...$param);
    }
    
    public function connect()
    {
        $this->execFormat('connect %s', $this->host);
    }
    
    public function screenshot($filename)
    {
        $this->execHost("shell screencap -p");
        if($this->returnCode === 0){
            $this->output = str_replace("\x0D\x0D\x0A", "\x0A", $this->output);
            file_put_contents($filename, $this->output);
            $this->logger->info('Screenshot OK');
            return true;
        }else{
            $this->logger->warning('Screencode return code %u.', [$this->returnCode]);
            return false;
        }
    }
    
    public function stopActivity($packageName)
    {
        $this->execHost('shell am stop %s', $packageName);
        if($this->returnCode === 0){
            $this->logger->info('Stop activity OK');
            return true;
        }else{
            $this->logger->warning('Screencode return code %u.', [$this->returnCode]);
            return false;
        }
    }
    
    public function tap($x, $y)
    {
        $this->execHost(sprintf('shell input touchscreen tap %u %u', $x, $y));
        if($this->returnCode === 0){
            $this->logger->info('Touchscreen Tap at %ux%u', [$x, $y]);
            return true;
        }else{
            $this->logger->warning('Touchscreen tap return code %u, message: %s.', [$this->returnCode, $this->output]);
            return false;
        }
    }
    
    public function swipe($fromX, $fromY, $toX, $toY)
    {
        $this->execHost(sprintf('shell input touchscreen swipe %u %u %u %u', $fromX, $fromY, $toX, $toY));
        if($this->returnCode === 0){
            $this->logger->info('Touchscreen Swipe from %ux%u to %ux%u', [$fromX, $fromY, $toX, $toY]);
            return true;
        }else{
            $this->logger->warning('Touchscreen tap return code %u, message: %s.', [$this->returnCode, $this->output]);
            return false;
        }
    }
}

?>
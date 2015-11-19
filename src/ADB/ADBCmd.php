<?php
namespace AB\ADB;

use AB\Manager;
use AB\ServiceInterface;

class ADBCmd extends CommandLine implements ServiceInterface
{
    const CONST_ADB_HOST = 'ADB_TCPIP';
    const CONST_ADB_BIN = 'ADB_PATH';
    
    const CONST_X = 'X';
    const CONST_Y = 'Y';
    
    const CONST_X1 = 'X1';
    const CONST_X2 = 'X2';
    const CONST_Y1 = 'Y1';
    const CONST_Y2 = 'Y2';
    
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
        $this->execHost('shell am force-stop %s', $packageName);
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
    
    public function tapXY(array $xy)
    {
        self::assertPoint($xy);
        return $this->tap($xy[self::CONST_X], $xy[self::CONST_Y]);
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
    
    public function swipeXY($xy)
    {
        self::assertRect($xy);
        return $this->swipe($xy[self::CONST_X1], $xy[self::CONST_Y1], $xy[self::CONST_X2], $xy[self::CONST_Y2]);
    }
    
    public static function assertPoint(&$xy)
    {
        if(!isset($xy[self::CONST_X]) || !isset($xy[self::CONST_Y])) throw new \InvalidArgumentException('The point JSON must be {X: x, Y: y}.');
    }
    
    public static function assertRect(&$xy)
    {
        if(!isset($xy[self::CONST_X1]) || !isset($xy[self::CONST_Y1]) || !isset($xy[self::CONST_X2]) || !isset($xy[self::CONST_Y2])) throw new \InvalidArgumentException('The point JSON must be {X1: x1, Y1: y1, X2: x2, Y2: y2}.');
    }
}

?>
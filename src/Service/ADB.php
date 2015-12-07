<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 11:23
 */

namespace AB\Service;


use AB\Manager;
use AB\Util\Shell;

/**
 * Class ADB
 * @package AB\Service
 * @property-read Shell $shell
 * @property-read string $host
 */
class ADB extends BaseService
{
    const CONST_ADB_HOST = 'ADB_HOST';
    const CONST_ADB_BIN = 'ADB_PATH';

    private $adb;
    private $host;
    private $shell;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->shell = new Shell($this->logger);
        $this->adb = $manager->getConstant(self::CONST_ADB_BIN, 'adb');
        $this->host = $manager->getConstant(self::CONST_ADB_HOST);
    }

    public function __get($name)
    {
        if($name === 'shell' || $name == 'host'){
            return $this->$name;
        }else{
            return null;
        }
    }

    protected function logError($log)
    {
        $this->logger->error("$log perhaps failed. Return code %u, message: %s.", [$this->shell->returnCode, $this->shell->output]);
    }

    public function exec($cmd, ...$param)
    {
        $this->shell->execFormat(escapeshellarg($this->adb) . ' ' . $cmd, ...$param);
    }

    public function execHost($cmd, ...$param)
    {
        array_unshift($param, $this->host);
        $this->exec("-s %s $cmd", ...$param);
    }

    public function connect()
    {
        $this->exec('connect %s', $this->host);
    }

    public function screenshot($filename)
    {
        $this->execHost("shell screencap -p");
        if($this->shell->returnCode === 0){
            $this->shell->output = str_replace("\x0D\x0D\x0A", "\x0A", $this->shell->output);
            file_put_contents($filename, $this->shell->output);
            $this->logger->notice('Screen captured OK');
            return true;
        }else{
            $this->logError('Screen capturing');
            return false;
        }
    }

    public function stopActivity($packageName)
    {
        $this->execHost('shell am force-stop %s', $packageName);
        if($this->shell->returnCode === 0){
            $this->logger->info('Stop activity %s OK', [$packageName]);
            return true;
        }else{
            $this->logError('Stopping main activity');
            return false;
        }
    }

    public function keyInput($code)
    {
        $this->execHost(sprintf('shell input keyevent %u', $code));
        if($this->shell->returnCode === 0){
            $this->logger->notice('Key event input value %u OK', [$code]);
            return true;
        }else{
            $this->logError('Sending keyboard event');
            return false;
        }
    }

    public function tap($x, $y)
    {
        $this->execHost(sprintf('shell input touchscreen tap %u %u', $x, $y));
        if($this->shell->returnCode === 0){
            $this->logger->notice('Touchscreen tap at %ux%u', [$x, $y]);
            return true;
        }else{
            $this->logError('Tapping screen');
            return false;
        }
    }

    public function swipe($fromX, $fromY, $toX, $toY)
    {
        $this->execHost(sprintf('shell input touchscreen swipe %u %u %u %u', $fromX, $fromY, $toX, $toY));
        if($this->shell->returnCode === 0){
            $this->logger->notice('Touchscreen swipe from %ux%u to %ux%u', [$fromX, $fromY, $toX, $toY]);
            return true;
        }else{
            $this->logError('Swiping screen');
            return false;
        }
    }

    public function tapPoint($point)
    {
        Position::assertPoint($point);
        return $this->tap($point[Position::X], $point[Position::Y]);
    }

    public function swipeLine($line)
    {
        Position::assertRect($line);
        return $this->swipe($line[Position::X1], $line[Position::Y1], $line[Position::X2], $line[Position::Y2]);
    }

}
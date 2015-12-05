<?php
namespace AB\Action\Common;

use AB\Action\BaseAction;
use AB\Manager;
use AB\Service\ADB;

class TestConnection extends BaseAction
{
    /**
     * @var ADB
     */
    private $adb;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->adb = ADB::instance($manager, $this->app);
    }

    protected function failure($step)
    {
        $this->logger->debug('adb %s return code %u output: %s', [$step, $this->adb->shell->returnCode, $this->adb->output]);
        $this->logger->error('ADB Connection is not ready.');
        return $this->errorReturnCode;
    }

    public function run(array $context = [])
    {
        $this->adb->connect();
        if(stripos($this->adb->shell->output, 'connected') !== false){
            $this->adb->exec('devices');
            if(strpos($this->adb->shell->output, "{$this->adb->host}\tdevice") !== false){
                $this->logger->notice('adb connection established successfully.');
                return Manager::RET_LOOP;
            }else{
                $this->failure('devices');
            }
        }else{
            $this->failure('connect');
        }
    }
}

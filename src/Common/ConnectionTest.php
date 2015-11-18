<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;
use AB\ADB\ADBCmd;

class ConnectionTest implements ActionInterface
{

    public function run(Manager $context)
    {
        $adb = ADBCmd::instance($context);
        $adb->connect();
        if(stripos($adb->output, 'connected') !== false){
            $adb->exec('devices');
            if(strpos($adb->output, "{$adb->host}\tdevice") !== false){
                $context->logger->notice('adb connection established successfully.');
                return Manager::RET_LOOP;
            }else{
                $context->logger->debug('adb devices output: %s', [$adb->output]);
                $context->logger->error('ADB Connection is not ready.');
                return Manager::RET_EXIT;
            }
        }else{
            $context->logger->debug('adb connect output: %s', [$adb->output]);
            $context->logger->error('ADB Connection Failed to %s', [$adb->host]);
            return Manager::RET_EXIT;
        }
    }
}

?>
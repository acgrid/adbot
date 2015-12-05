<?php
namespace AB;

use Monolog\Logger as MonoLogger;

class Logger extends MonoLogger
{    
    public function debug($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::debug($message, $context);
    }
    
    public function info($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::info($message, $context);
    }
    
    public function notice($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::notice($message, $context);
    }
    
    public function warning($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::warning($message, $context);
    }
    
    public function warn($message, array $args = [], array $context = [])
    {
        return $this->warning($message, $args, $context);
    }
    
    public function error($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::error($message, $context);
    }
    
    public function err($message, array $args = [], array $context = [])
    {
        return $this->error($message, $args, $context);
    }
    
    public function alert($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::alert($message, $context);
    }
    
    public function critical($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::critical($message, $context);
    }
    
    public function crit($message, array $args = [], array $context = [])
    {
        return $this->critical($message, $args, $context);
    }
    
    public function emergency($message, array $args = [], array $context = [])
    {
        if(count($args)) $message = vsprintf($message, $args);
        return parent::emergency($message, $context);
    }
    
    public function emerg($message, array $args = [], array $context = [])
    {
        return $this->emerg($message, $args, $context);
    }
}

?>
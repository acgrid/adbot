<?php
/**
 * @var $argv array
 */
namespace AB;

use AB\Logger\Logger;
use Monolog\Handler\StreamHandler;
     
if(PHP_SAPI !== 'cli') exit("Run this script in console.");
chdir(__DIR__);
require 'vendor/autoload.php';

$options = getopt('v::', ['verbose']);
$opt_num = count($options);
if(!isset($options['v'])) $options['v'] = null;
if(!isset($options['verbose'])) $options['verbose'] = null;
if($options['v'] === 'vv' || strcasecmp($options['verbose'], 'debug') === 0){
    $log_level = Logger::DEBUG;
}elseif($options['v'] === 'v' || strcasecmp($options['verbose'], 'info') === 0){
    $log_level = Logger::INFO;
}elseif($options['v'] === false || $options['verbose'] === false){
    $log_level = Logger::NOTICE;
}else{
    $log_level = Logger::WARNING;
}
	
$logger = new Logger('main');
$logger->pushHandler(new StreamHandler(sprintf('%s/log/%s.log', __DIR__, date('Ymd-His')), Logger::DEBUG));
$logger->pushHandler(new StreamHandler('php://output', $log_level));
try{
    if(!isset($argv[++$opt_num])) throw new \InvalidArgumentException(sprintf("Usage: %s [-v[v|vv]|--verbose [debug|info]] CONFIG.json", $argv[0]));
    $config_file = $argv[$opt_num];
    if(!is_readable($config_file)) throw new \RuntimeException("Config file is not readable.");
    $config = json_decode(file_get_contents($config_file), true);
    if(!is_array($config)) throw new \InvalidArgumentException('Config file is not valid JSON: ' . json_last_error_msg());
    exit(Manager::run($config, $logger));
}catch(\Exception $e){
    $logger->error($e->getMessage());
    echo $e->getTraceAsString();
}

<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/19
 * Time: 18:04
 */

namespace AB\Service;


use AB\Manager;

class Input extends BaseService
{
    const DEFAULT_RETRY_LIMIT = 5;
    const DEFAULT_RETRY_DELAY = 1000;

    private $position;
    private $delay;
    /**
     * @var ADB
     */
    private $adb;
    /**
     * @var Screen
     */
    private $scr;

    private $retryLimit;
    private $retryDelay;
    private $retryOffset;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->setRetryLimit(Manager::readConfig($config, Manager::RES_CONFIG_RETRY, self::DEFAULT_RETRY_LIMIT));
        $this->setRetryDelay(Manager::readConfig($config, Manager::RES_CONFIG_RETRY, self::DEFAULT_RETRY_DELAY));
        $this->retryOffset = intval($this->retryDelay / 10);
        $this->position = Position::instance($manager, $this->app);
        $this->delay = Delay::instance($manager, $this->app);
        $this->adb = ADB::instance($manager, $this->app);
        $this->scr = Screen::instance($manager, $this->app);
    }

    public function setRetryLimit($limit)
    {
        if(!is_int($limit) || $limit < 0) throw new \InvalidArgumentException("Retry limit should be non-negative integer.");
        $this->retryLimit = $limit;
    }

    public function setRetryDelay($delay)
    {
        if(!is_int($delay) || $delay <= 0) throw new \InvalidArgumentException("Retry delay should be positive integer.");
        $this->retryDelay = $delay;
    }

    public function tap(array $point)
    {
        $retry = 0;
        $this->logger->info('Tap point %u*%u', [$point[Position::X], $point[Position::Y]]);
        while(!$this->adb->tapPoint($point)){
            if(++$retry > $this->retryLimit){
                $this->logger->error('Abandon retry for tapping screen.');
                return $this->adb->shell->returnCode;
            }else{
                $this->logger->warning('Retry %u for tapping screen.', [$retry]);
            }
            $this->delay->delayOffset($this->retryDelay, $this->retryOffset);
        }
        return true;
    }

    public function tapInRect(array $rectangle)
    {
        $this->scr->translateRect($rectangle);
        return $this->tap($this->position->getPointInRect($rectangle));
    }

    public function swipe(array $rectangle)
    {
        $this->scr->translateRect($rectangle);
        $retry = 0;
        while(!$this->adb->swipeLine($rectangle)){
            if(++$retry > $this->retryLimit){
                $this->logger->error('Abandon retry for tapping screen.');
                return $this->adb->shell->returnCode;
            }else{
                $this->logger->warning('Retry %u for tapping screen.', [$retry]);
            }
            $this->delay->delayOffset($this->retryDelay, $this->retryOffset);
        }
        return true;
    }
}
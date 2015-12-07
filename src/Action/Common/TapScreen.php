<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 18:24
 */

namespace AB\Action\Common;


use AB\Action\BaseAction;
use AB\Manager;
use AB\Service\ADB;
use AB\Service\Delay;
use AB\Service\Position;
use AB\Service\Screen;

class TapScreen extends BaseAction
{
    const CFG_RECTANGLES = 'pos';
    const CFG_DELAY = 'delay';

    const DEFAULT_DELAY = 1000;
    const DEFAULT_RETRY = 3;

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

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->position = Position::instance($manager, $this->app);
        $this->delay = Delay::instance($manager, $this->app);
        $this->adb = ADB::instance($manager, $this->app);
        $this->scr = Screen::instance($manager, $this->app);
    }

    public function run(array $context = [])
    {
        $rectangles = Manager::readConfig($context, self::CFG_RECTANGLES);
        if(!is_array($rectangles) || ($count = count($rectangles)) === 0) return Manager::RET_LOOP;
        $delay = (int) Manager::readConfig($context, self::CFG_DELAY, self::DEFAULT_DELAY);
        if($delay <= 0){
            $delay = self::DEFAULT_DELAY;
            $this->logger->warning('%s: delay milliseconds should be positive integer. Use default value %u.', [__CLASS__, $delay]);
        }
        $delayOffset = intval($delay / 10);
        $this->logger->info('Tap screen for %u point(s) with delay %u-%u ms.', [$count, $delay, $delayOffset]);
        $retryLimit = Manager::readConfig($context, Manager::RES_CONFIG_RETRY, self::DEFAULT_RETRY);
        foreach($rectangles as $rectangle){
            $this->scr->translateRect($rectangle);
            $point = $this->position->getPointInRect($rectangle);
            $retry = 0;
            while(!$this->adb->tapPoint($point)){
                if(++$retry > $retryLimit){
                    $this->logger->error('Abandon retry for tapping screen.');
                    return $this->errorReturnCode;
                }else {
                    $this->logger->warning('Retry %u for tapping screen.', [$retry]);
                }
                $this->delay->delayOffset($delay, $delayOffset);
            }
            $this->delay->delayOffset($delay, $delayOffset);
        }
        return Manager::RET_LOOP;
    }

}
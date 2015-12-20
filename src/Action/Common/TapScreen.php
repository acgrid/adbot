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
use AB\Service\Delay;
use AB\Service\Input;

class TapScreen extends BaseAction
{
    const CFG_RECTANGLES = 'pos';
    const CFG_DELAY = 'delay';

    const DEFAULT_DELAY = 1000;

    private $delay;
    /**
     * @var Input
     */
    private $input;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->delay = Delay::instance($manager, $this->app);
        $this->input = Input::instance($manager, $this->app);
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
        foreach($rectangles as $rectangle){
            $this->input->tapInRect($rectangle);
            $this->delay->delayOffset($delay, $delayOffset);
        }
        return Manager::RET_LOOP;
    }

}
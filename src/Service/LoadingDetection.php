<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 20:04
 */

namespace AB\Service;


use AB\Action\Common\TapScreen;
use AB\Manager;

class LoadingDetection extends BaseService
{
    const CFG_ERROR_DIALOGS = 'dialogs';
    const CFG_LOADING_ICON = 'loading';
    const CFG_CHECK_INTERVAL = 'interval';
    const CFG_TIMEOUT = 'timeout';

    const DEFAULT_INTERVAL = 1000;
    const DEFAULT_TIMEOUT = 60000;

    const DIALOG_JUDGE = 'judge';
    const DIALOG_BUTTON = 'button';

    private $dialogs;
    private $loading;
    private $interval;
    private $delayOffset;
    private $timeout;

    /**
     * @var Screen
     */
    private $screen;
    /**
     * @var Delay
     */
    private $delay;
    private $tapAction;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->dialogs = Manager::readConfig($config, self::CFG_ERROR_DIALOGS);
        $this->loading = Manager::readConfig($config, self::CFG_LOADING_ICON);
        $this->interval = intval(Manager::readConfig($config, self::CFG_CHECK_INTERVAL, self::DEFAULT_INTERVAL));
        $this->timeout = intval(Manager::readConfig($config, self::CFG_TIMEOUT, self::DEFAULT_TIMEOUT));

        if(!is_array($this->dialogs)) throw new \InvalidArgumentException('Dialogs is not an array');
        array_walk($this->dialogs, [__CLASS__, 'assertDialogRule']);
        Screen::assertRules($this->loading);
        if($this->interval <= 0){
            $this->interval = self::DEFAULT_INTERVAL;
            $this->logger->warning('%s: Interval is not positive integer, use default value %u.', [__CLASS__, self::DEFAULT_INTERVAL]);
        }
        $this->delayOffset = intval($this->delay * 0.15);
        if($this->timeout < $this->interval * 3){
            $this->timeout = $this->interval * 3;
            $this->logger->warning('%s: Timeout is not science, use minimum value %u.', [__CLASS__, $this->timeout]);
        }
        $this->screen = Screen::instance($manager, $this->app);
        $this->delay = Delay::instance($manager, $this->app);
        $this->tapAction = TapScreen::instance($manager, $this->app);
    }

    public static function assertDialogRule(array &$dialog)
    {
        if(!isset($dialog[self::DIALOG_JUDGE]) || !isset($dialog[self::DIALOG_BUTTON])) {
            throw new \InvalidArgumentException('Rules should be an array.');
        }
        Screen::assertRules($dialog[self::DIALOG_JUDGE]);
        Position::assertRect($dialog[self::DIALOG_BUTTON]);
    }

    public function isLoading()
    {
        if($this->screen->compareRules($this->loading)){
            $this->logger->debug('Target app is in loading status.');
            return true;
        }
        return false;
    }

    public function clickDialogs()
    {
        foreach($this->dialogs as $index => $dialog){
            if($this->screen->compareRules($dialog[self::DIALOG_JUDGE])){
                $this->logger->info('Common dialog %s encountered.', [$index]);
                $this->tapAction->run([TapScreen::CFG_RECTANGLES => $dialog[self::DIALOG_BUTTON]]);
                return true;
            }
        }
        return false;
    }

    public function wait()
    {
        $waited = 0;
        while($this->isLoading() || $this->clickDialogs()){
            $this->delay->delayOffset($this->interval, $this->delayOffset);
            $waited += $this->interval;
            if($waited > $this->timeout) throw new \RuntimeException('Loading assertion timeout.');
        }
    }

    /**
     *
     * @param array $assertion
     */
    public function waitFor(array $assertion, $timeout = null, $delay = null)
    {
        Screen::assertRules($assertion);
        $timeout = is_int($timeout) && $timeout > 0 ? $timeout : $this->timeout;
        $delay = is_int($delay) && $delay > 0 ? $delay : $this->delay;
        $offset = intval($delay * 0.15);
        $waited = 0;
        while(!$this->screen->compareRules($assertion)){
            $this->delay->delayOffset($delay, $offset);
            $waited += $delay;
            if($waited > $timeout) throw new \RuntimeException('Waiting for assertion timeout.');
        }
    }

}
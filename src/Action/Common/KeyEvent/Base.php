<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 16:18
 */

namespace AB\Action\Common\KeyEvent;
use AB\Action\BaseAction;
use AB\Manager;
use AB\Service\ADB;

/**
 * Class Base
 * @see http://developer.android.com/reference/android/view/KeyEvent.html for full list of key codes
 * @package AB\Action\Common\KeyEvent
 */
abstract class Base extends BaseAction
{
    const CFG_EVENT_CODE = 'code';
    const DEFAULT_EVENT_CODE = -1;

    const CODE_HOME = 3;
    const CODE_VOLUME_UP = 24;
    const CODE_VOLUME_DOWN = 25;

    private $eventCode;
    /**
     * @var ADB
     */
    private $adb;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        // prevent forgetting override default value
        if(static::DEFAULT_EVENT_CODE === -1) throw new \LogicException(sprintf("You forget to override %s::DEFAULT_EVENT_CODE.", __CLASS__));
        $this->eventCode = Manager::readConfig($config, self::CFG_EVENT_CODE, static::DEFAULT_EVENT_CODE);
        $this->adb = ADB::instance($manager, $this->app);
    }

    public function run(array $context = [])
    {
        if($this->adb->keyInput(Manager::readConfig($context, self::CFG_EVENT_CODE, $this->eventCode))) return Manager::RET_LOOP;
        return $this->errorReturnCode;
    }
}
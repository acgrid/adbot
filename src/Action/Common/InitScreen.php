<?php
namespace AB\Action\Common;

use AB\Action\BaseAction;
use AB\Manager;
use AB\Service\Screen;

class InitScreen extends BaseAction
{
    const RET_ERROR = Manager::RET_AGAIN;

    const CFG_ORIENTATION = 'orientation';
    const CFG_SYMBOL = 'name';

    const DEFAULT_ORIENTATION = Screen::AUTO;
    const DEFAULT_SYMBOL = 'INIT';

    /**
     * @var Screen
     */
    private $screen;
    private $defaultSymbol;
    private $defaultOrientation;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->screen = Screen::instance($manager, $this->app);
        $this->defaultSymbol = Manager::readConfig($config, self::CFG_SYMBOL, self::DEFAULT_SYMBOL);
        $this->defaultOrientation = Manager::readConfig($config, self::CFG_ORIENTATION, self::DEFAULT_ORIENTATION);
    }

    public function run(array $context = [])
    {
        $symbol = Manager::readConfig($context, self::CFG_SYMBOL, $this->defaultSymbol);
        try{
            $this->screen->capture($symbol, Manager::readConfig($context, self::CFG_ORIENTATION, $this->defaultOrientation));
            $this->logger->info('Screen info: %uX%u mode %s, rotate fix is %s.',
                [$this->screen->width, $this->screen->height, $this->screen->orientation, $this->screen->rotateFix ? 'On' : 'Off']);
            return Manager::RET_LOOP;
        }catch(\RuntimeException $e){
            return $this->errorReturnCode;
        }
    }
}

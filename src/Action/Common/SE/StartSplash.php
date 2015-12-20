<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 17:39
 */

namespace AB\Action\Common\SE;


use AB\Action\AppAction;
use AB\Manager;
use AB\Service\Position;
use AB\Service\Screen;

class StartSplash extends AppAction
{
    const CFG_RULES = 'rules';
    const CFG_BUTTON = 'button';

    private $rules;
    private $button;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->rules = Manager::readConfig($config, self::CFG_RULES);
        $this->button = Manager::readConfig($config, self::CFG_BUTTON);
        Screen::assertRules($this->rules);
        Position::assertRect($this->button);
    }

    public function run(array $context = [])
    {
        if($this->screen->compareRules($this->rules)){
            $this->logger->info('Start splash detected.');
            $this->input->tapInRect($this->button);
            return Manager::RET_LOOP;
        }else{
            return Manager::RET_AGAIN;
        }
    }

}
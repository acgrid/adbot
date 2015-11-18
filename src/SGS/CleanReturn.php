<?php
namespace AB\SGS;

use AB\ActionInterface;
use AB\Manager;
use AB\Common\PressKey;
use AB\Common\StopApp;

class CleanReturn implements ActionInterface
{

    public function run(Manager $context, array $config)
    {
        /* @var $returnAction AB\Common\PressKey */
        /* @var $stopAction AB\Common\StopApp */
        $base = $context->getGameBase(__NAMESPACE__);
        $returnAction = $context->getService(__NAMESPACE__, 'Common\\PressKey');
        $stopAction = $context->getService(__NAMESPACE__, 'Common\\StopApp');
        $returnAction->run($context, [PressKey::CONFIG_EVENTCODE => PressKey::CODE_HOMEKEY]);
        $base->helperDelay()->delayBoundaryRandom(100, 600);
        $stopAction->run($context, [StopApp::CONFIG_APPNAME => $base->getAppPackageName()]);
        $context->logger->info('Exit SGS and return home.');
    }
}

?>
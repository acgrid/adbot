<?php
namespace AB\Common;

use AB\ActionInterface;
use AB\Manager;
use AB\OCR\ScreenFacade;

class ResolutionCheck implements ActionInterface
{

    public function run(Manager $context)
    {
        $scr = ScreenFacade::instance($context);
        list($x, $y) = $scr->capture('init')->xy;
        $context->setConstant(ScreenFacade::CONST_LONG_EDGE, $x > $y ? $x : $y);
        $context->setConstant(ScreenFacade::CONST_SHORT_EDGE, $x < $y ? $x : $y);
        $context->logger->info('Get resultion %ux%u', [$context->getConstant(ScreenFacade::CONST_LONG_EDGE), $context->getConstant(ScreenFacade::CONST_SHORT_EDGE)]);
        return Manager::RET_LOOP;
    }
}

?>
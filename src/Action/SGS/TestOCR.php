<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/11
 * Time: 21:07
 */

namespace AB\Action\SGS;


use AB\Action\AppAction;
use AB\Manager;
use AB\Service\OCR\Number;
use AB\Service\Position;

class TestOCR extends AppAction
{
    public function run(array $context = [])
    {
        $ocr = Number::instance($this->manager, $this->app);
        $align = Manager::readConfig($context, 'align', 'L');
        $rect = $align == 'L' ? Position::makeRectangle(0, 0, 1, 1) : Position::makeRectangle(1, 1, 0, 0);
        $result = $ocr->ocr('Test', $rect, [Number::CFG_COLOR => '000000', Number::CFG_WIDTH => 0.25, Number::CFG_MARGIN => 0]);
        $this->logger->info('OCR Result %s', [$result]);
        return Manager::RET_LOOP;
    }

}
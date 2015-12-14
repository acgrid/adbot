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
        $mode = Manager::readConfig($context, 'scan', Number::SCAN_FIXED);
        $rule = Manager::readConfig($context, 'rule', 'Test');
        $color = Manager::readConfig($context, 'color', '000000');
        $rect = $align == 'L' ? Position::makeRectangle(0, 0, 1.0, 1.0) : Position::makeRectangle(1.0, 1.0, 0, 0);
        $result = $ocr->ocr($rule, $rect, [Number::CFG_COLOR => $color,
            Number::CFG_SCAN_MODE => $mode, Number::CFG_WIDTH => 0.25, Number::CFG_MARGIN => 0]);
        $this->logger->info('OCR Result %s', [$result]);
        return Manager::RET_LOOP;
    }

}
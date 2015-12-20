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
use AB\Service\Screen;

class TestDigitOCR extends AppAction
{
    public function run(array $context = [])
    {
        $ocr = Number::instance($this->manager, $this->app);
        $point = Manager::readConfig($context, 'point');
        Position::assertPoint($point);
        $this->logger->info('Test for X=%.4f, Y=%.4f', $point[Position::X], $point[Position::Y]);
        $ocr->setRules(['Test' => ['J' => [$point], 'T' => 1, 'F' => 0]]);
        $rect = Position::makeRectangle(0, 0, 1, 1);
        for($i = 0; $i <= 9; $i++){
            $this->screen->load(sprintf('tests/digits/%u.png', $i), Screen::PORTRAIT);
            $this->logger->info('OCR Result for digit %u is %s',
                [$i, $ocr->ocr('Test', $rect, [Number::CFG_COLOR => '000000', Number::CFG_WIDTH => 1, Number::CFG_MARGIN => 0])]);
        }

        return Manager::RET_LOOP;
    }

}
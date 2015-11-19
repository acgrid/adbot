<?php
namespace AB\Random;

use AB\Manager;
use AB\ADB\ADBCmd;
class Position
{
    private $context;
    
    public function __construct(Manager $context)
    {
        $this->context = $context;        
    }
    
    public function pointByRect($fromX, $fromY, $toX, $toY)
    {
        return [ADBCmd::CONST_X => mt_rand($fromX, $toX), ADBCmd::CONST_Y => mt_rand($fromY, $toY)];
    }
    
    public function pointInRect($x1y1x2y2)
    {
        ADBCmd::assertRect($x1y1x2y2);
        return $this->pointByRect($x1y1x2y2[ADBCmd::CONST_X1], $x1y1x2y2[ADBCmd::CONST_Y1], $x1y1x2y2[ADBCmd::CONST_X2], $x1y1x2y2[ADBCmd::CONST_Y2]);
    }
    
    /**
     * 
     * @param int $fromX
     * @param int $fromY
     * @param int $toX
     * @param int $toY
     * @param real $minSlope
     * @param real $maxVerticalPercentage
     */
    public function swipeLineByRect($fromX, $fromY, $toX, $toY, $minSlope = 10, $maxVerticalPercentage = 0.05)
    {
        $slope = mt_rand($minSlope, mt_getrandmax());
        $X1 = mt_rand($fromX, $toX);
        $Y1 = mt_rand(floor($fromY * (1 + $maxVerticalPercentage)), ceil($fromY * (1 + $maxVerticalPercentage)));
        $Y2 = mt_rand(floor($toY * (1 + $maxVerticalPercentage)), ceil($toY * (1 + $maxVerticalPercentage)));
        if(mt_rand(0, 1)) $slope = -$slope;
        $X2 = $X1 + abs($Y1 - $Y2) / $slope;
        $X2 = min($toX, max($fromX, $X2));
        return [ADBCmd::CONST_X1 => $X1, ADBCmd::CONST_Y1 => $Y1, ADBCmd::CONST_X2 => $X2, ADBCmd::CONST_Y2 => $Y2];
    }
}

?>
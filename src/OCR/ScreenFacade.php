<?php
namespace AB\OCR;

use AB\ServiceInterface;
use AB\Manager;
use AB\ADB\ADBCmd;

/**
 * @property integer $x
 * @property integer $y
 * @property array $xy
 * @property string $rotate
 * 
 * @author acgrid
 *
 */
class ScreenFacade implements ServiceInterface
{
    const CONFIG_SCREENSHOT_SAVE_PATH = 'screenshot_path';
    
    const CONST_LONG_EDGE = 'DIM_LONG';
    const CONST_SHORT_EDGE = 'DIM_SHORT';
    
    const ROTATE_L = 'l';
    const ROTATE_P = 'p';
    
    const ALIGN_LEFT = 'left';
    const ALIGN_RIGHT = 'right';
    
    private $context;
    private $savePath;
    private $adb;
    private $currentImage;
    
    private $x = 0;
    private $y = 0;

    public function __construct(Manager $context)
    {
        $this->savePath = $context->getConstant(self::CONFIG_SCREENSHOT_SAVE_PATH, $context->path . DIRECTORY_SEPARATOR . 'screenshot' . DIRECTORY_SEPARATOR);
        $this->adb = ADBCmd::instance($context);
    }
     
     /* (non-PHPdoc)
      * @see \AB\ServiceInterface::instance()
      */
    public static function instance(Manager $context) {
        static $instance;
        if(!isset($instance)){
            $instance = new self($context);
        }
        return $instance;
    }
    
    public function __get($name)
    {
        switch($name){
            case 'xy': return [$this->x, $this->y];
            case 'x': return $this->x;
            case 'y': return $this->y;
            case 'rotate': return $this->x >= $this->y ? self::ROTATE_L : self::ROTATE_P;
            default: return null;
        }
    }
    
    /**
     * If Android screenshot do not rotate image, specify $position to fix it
     * @param array $xy
     * @param string $position
     * @return array:number
     */
    public function absPoint(array $xy, $position = null)
    {
        ADBCmd::assertPoint($xy);
        /**
         * A := A + B = a + b
         * B := A - B = a + b - b = a
         * A := A - B = a + b - a = b
         */
        $x = $this->x;
        $y = $this->y;
        if($position && $position != $this->rotate){
            $x = $x + $y;
            $y = $x - $y;
            $x = $x - $y;
        }
        return [ADBCmd::CONST_X => intval($x * $xy[ADBCmd::CONST_X]), ADBCmd::CONST_Y => intval($y * $xy[ADBCmd::CONST_Y])];
    }
    
    public function absRect(array $x1y1x2y2, $position = null)
    {
        ADBCmd::assertRect($x1y1x2y2);
        $P1 = $this->absPoint([ADBCmd::CONST_X => $x1y1x2y2[ADBCmd::CONST_X1], ADBCmd::CONST_Y => $x1y1x2y2[ADBCmd::CONST_Y1]], $position);
        $P2 = $this->absPoint([ADBCmd::CONST_X => $x1y1x2y2[ADBCmd::CONST_X2], ADBCmd::CONST_Y => $x1y1x2y2[ADBCmd::CONST_Y2]], $position);
        return [
            ADBCmd::CONST_X1 => $P1[ADBCmd::CONST_X],
            ADBCmd::CONST_Y1 => $P1[ADBCmd::CONST_Y],
            ADBCmd::CONST_X2 => $P2[ADBCmd::CONST_X],
            ADBCmd::CONST_Y2 => $P2[ADBCmd::CONST_Y],
        ];
    }
    
    public function absPoints(array &$points, $position = null)
    {
        $colors = array_keys($points);
        foreach($colors as $key){
            $that = $this;
            array_walk($points[$key], function(&$point, $index, $position) use ($that){
                $point = $that->absPoint($point, $position);
            });
        }
    }
    
    public function getGD()
    {
        if(!is_resource($this->currentImage)) throw new \BadMethodCallException("No image present now.");
        return $this->currentImage;
    }
    
    public function capture($name = '')
    {
        $name = date('Ymd-His') . preg_replace('/[^0-9a-z]+/', '_', $name) . ".png";
        if($this->adb->screenshot($this->savePath . $name)){
            $this->currentImage = imagecreatefromstring($this->adb->output);
            if(!is_resource($this->currentImage)) throw new \RuntimeException('Unable to construct image resource.');
            $this->x = imagesx($this->currentImage);
            $this->y = imagesy($this->currentImage);
            return $this;
        }else{
            throw new \RuntimeException('Screenshot failed.');
        }
    }
    
    public function load($filename)
    {
        $this->currentImage = imagecreatefrompng($filename);
        if(!is_resource($this->currentImage)) throw new \InvalidArgumentException("$filename is not an valid PNG file!");
        return $this;
    }
    
    public function getColor($x, $y)
    {
        if(!is_resource($this->currentImage)) throw new \BadMethodCallException("No image present now.");
        return imagecolorat($this->currentImage, $x, $y);
    }
    
    public function getColorRGB($x, $y)
    {
        $rgb = $this->getColor($x, $y);
        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }
    
    public function compareRGB($x, $y, $rgbstr, $tol = 16)
    {
        if(!preg_match('/[A-F0-9]{6}/i', $rgbstr)) throw new \InvalidArgumentException("'$rgbstr' is not a valid RGB notation.");
        list($dr, $dg, $db) = $this->getColorRGB($x, $y);
        list($sr, $sg, $sb) = array_map('hexdec', [substr($rgbstr, 0, 2), substr($rgbstr, 2, 2), substr($rgbstr, 4, 2)]);
        return sqrt(($dr - $sr) ^ 2 + ($dg - $sg) ^ 2 + ($db - $sb) ^ 2) < $tol; 
    }
    
    /**
     * 
     * @param array $dotsArray {color: [{x: x1, y: y1}, {x: x2, y: y2}]} 
     * @param number $tol
     * 
     * @return boolean
     */
    public function compareDots(array $dotsArray, $tol = 16)
    {
        foreach($dotsArray as $color => $dots){
            foreach($dots as $dot){
                try{
                    ADBCmd::assertPoint($dot);
                    if(!$this->compareRGB($dot[ADBCmd::CONST_X], $dot[ADBCmd::CONST_Y], $color, $tol)) return false;
                }catch(\InvalidArgumentException $e){
                    ADBCmd::assertRect($dot);
                    if(!$this->compareRGB($dot[ADBCmd::CONST_X1], $dot[ADBCmd::CONST_Y1], $color, $tol) ||
                       !$this->compareRGB($dot[ADBCmd::CONST_X2], $dot[ADBCmd::CONST_Y2], $color, $tol)) return false;
                }
            }
        }
        return true;
    }
    
    private function ocrAbs(array &$rules, $color, $tol, $fromX, $fromY, $toX, $toY)
    {
        foreach($rules as $rule){
            ADBCmd::assertPoint($rule);
            $x = $fromX + abs($toX - $fromX) * $rule[ADBCmd::CONST_X];
            $y = $fromY + abs($toY - $fromY) * $rule[ADBCmd::CONST_Y];
            if(!$this->compareRGB($x, $y, $color, $tol)) return false;
        }
        return true;
    }
    
    public function ocrOnce(array &$font, $color, $tol, $fromX, $fromY, $toX, $toY){
        if(!isset($font['J']) || !isset($font['T']) || !$font['F']) throw new \InvalidArgumentException("Font configuration is missing key.");
        $result = $this->ocrAbs($font['J'], $color, $tol, $fromX, $fromY, $toX, $toY) ? $font['T'] : $font['F']; // TODO relative value
        if(is_int($result) || is_string($result)) return intval($result);
        if(is_null($result)) return null;
        return $this->ocrOnce($result, $color, $tol, $fromX, $fromY, $toX, $toY);
    }
    
    /**
     * 
     * @param array $font {"J": [{X: 0.14, Y: 0.48}, {X: 0.14, Y: 0.48}], "T": 1, "F": {"J": x, "T": y, "F": null}}
     * @param array $x1x2y1y2 
     * @param string $color
     * @param string $align
     * @param integer $width
     * @param integer $spacing
     * @param integer $tol
     * @param string $rotate
     */
    public function ocr(array &$font, $x1x2y1y2, $color, $align, $width, $spacing = 0, $tol = 16, $rotate = null)
    {
        ADBCmd::assertRect($x1x2y1y2);
        $fromX = $x1x2y1y2[ADBCmd::CONST_X1];
        $toX = $x1x2y1y2[ADBCmd::CONST_X2];
        $fromY = $x1x2y1y2[ADBCmd::CONST_Y1];
        $toY = $x1x2y1y2[ADBCmd::CONST_Y2];
        $scanAt = $align == self::ALIGN_LEFT ? $fromX : $toX - $width;
        $step = $align == self::ALIGN_LEFT ? $width + $spacing : -$width - $spacing;
        if($rotate && $rotate == self::ROTATE_P && $this->rotate == self::ROTATE_L) throw new \InvalidArgumentException('Portrait mode OCR in landscape screenshot is not supported now.');
        $result = '';
        while($scanAt > $fromX && $scanAt < $toX){
            $digit = $this->ocrOnce($font, $color, $tol, $scanAt, $fromY, $scanAt + $width, $toY);
            if(is_int($digit)) $result = $align == self::ALIGN_LEFT ? $result . strval($digit) : strval($digit) . $result;
            if(is_null($digit)) break; // end of number
            $scanAt += $step;
        }
        return $result;
    }
}

?>
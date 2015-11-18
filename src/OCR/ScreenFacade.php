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
    
    private $x;
    private $y;

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
     * @return boolean
     */
    public function compareDots(array $dotsArray, $tol = 16)
    {
        foreach($dotsArray as $color => $dots){
            foreach($dots as $dot){
                if(!isset($dot['x']) || !isset($dot['y'])) throw new \InvalidArgumentException("Unexpected dot without X,Y key.");
                if(!$this->compareRGB($dot['x'], $dot['y'], $color, $tol)) return false;
            }
        }
        return true;
    }
    
    public function ocrOnce(array &$font, $color, $fromX, $fromY, $toX, $toY){
        if(!isset($font['J']) || !isset($font['T']) || !$font['F']) throw new \InvalidArgumentException("Font configuration is missing key.");
        $result = $this->compareDots([$color => $font['J']]) ? $font['T'] : $font['F'];
        if(is_int($result)) return intval($result);
        return $this->ocrOnce($result, $color, $fromX, $fromY, $toX, $toY);
    }
    
    /**
     * 
     * @param array $font {"J": [{X: 0.14, Y: 0.48}, {X: 0.14, Y: 0.48}], "T": 1, "F": {"J": x, "T": y, "F": null}}
     * @param string $color
     * @param string $align
     * @param integer $width
     * @param integer $spacing
     * @param integer $fromX
     * @param integer $fromY
     * @param integer $toX
     * @param integer $toY
     */
    public function ocr(array &$font, $color, $align, $width, $spacing, $fromX, $fromY, $toX, $toY)
    {
        $scanAt = $align == self::ALIGN_LEFT ? $fromX : $toX - $width;
        $step = $align == self::ALIGN_LEFT ? $width : -$width;
        $result = '';
        while($scanAt > $fromX && $scanAt < $toX){
            $digit = $this->ocrOnce($font, $color, $scanAt, $fromY, $scanAt + $width, $toY);
            if(is_int($digit)) $result = $align == self::ALIGN_LEFT ? $result . strval($digit) : strval($digit) . $result;
            $scanAt += $step;
        }
        return $result;
    }
}

?>
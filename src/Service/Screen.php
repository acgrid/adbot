<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 15:36
 */

namespace AB\Service;


use AB\Manager;

/**
 * Class Screen
 * @package AB\Service
 * @property-read resource $gd
 * @property-read integer $x
 * @property-read integer $y
 * @property-read integer $width
 * @property-read integer $height
 * @property-read string $orientation
 * @property-read bool $rotateFix
 */
class Screen extends BaseService
{
    const CFG_SCREEN_SHOT_PATH = 'screen-shot-path';
    const CFG_DEFAULT_DISTANCE = 'default-rgb-distance';

    const AUTO = 'A';
    const PORTRAIT = 'P';
    const LANDSCAPE = 'L';

    const COLOR_R = 'R';
    const COLOR_G = 'G';
    const COLOR_B = 'B';
    const COLOR_D = 'D';

    const REGEXP_COLOR = '/([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})(?::?(\d+))?/i';

    /**
     * @var ADB
     */
    private $adb;

    private $savePath;
    private $defaultDistance;

    private $gd;
    /* Actual value from image */
    private $x;
    private $y;
    /* Logical value */
    private $width;
    private $height;
    private $orientation = self::AUTO;
    private $rotateFix;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->adb = ADB::instance($manager, $config[Manager::RES_CONFIG_APP]);
        $this->savePath = Manager::readConfig($config, self::CFG_SCREEN_SHOT_PATH, $manager->path . DIRECTORY_SEPARATOR . 'screenshot' . DIRECTORY_SEPARATOR);
        $this->defaultDistance = Manager::readConfig($config, self::CFG_DEFAULT_DISTANCE, 8);
    }

    public function __destruct()
    {
        if(is_resource($this->gd)) imagedestroy($this->gd);
    }

    public function __get($name)
    {
        switch($name){
            case 'gd':
            case 'x':
            case 'y':
            case 'orientation':
            case 'width':
            case 'height':
            case 'rotateFix':
                return $this->$name;
            default:
                return null;
        }
    }

    public function setOrientation($mode)
    {
        if($mode != self::PORTRAIT && $mode != self::LANDSCAPE) throw new \InvalidArgumentException("Invalid orientation mode '$mode'.'");
        $this->orientation = $mode;
    }

    protected function init($orientation = self::AUTO)
    {
        if(!is_resource($this->gd)) throw new \RuntimeException('Unable to construct image resource.');
        $this->x = imagesx($this->gd);
        $this->y = imagesy($this->gd);
        $imageOrientation = $this->x > $this->y ? self::LANDSCAPE : self::PORTRAIT;
        if($orientation == self::AUTO){
            $this->orientation = $imageOrientation;
        }else{
            $this->setOrientation($orientation);
            $this->rotateFix = $orientation != $imageOrientation;
        }
        if($this->rotateFix){
            $this->width = $this->y;
            $this->height = $this->x;
        }else{
            $this->width = $this->x;
            $this->height = $this->y;
        }
    }

    public function load($filename, $orientation = self::AUTO)
    {
        $this->gd = imagecreatefrompng($filename);
        $this->init($orientation);
        return $this;
    }

    public function capture($name = '', $orientation = self::AUTO)
    {
        $filename = date('Ymd-His') . '-' . preg_replace('/[^0-9a-z]+/i', '_', $name) . ".png";
        if($this->adb->screenshot($this->savePath . $filename)){
            $this->gd = imagecreatefromstring($this->adb->shell->output);
            $this->init($orientation);
            return $this;
        }else{
            throw new \RuntimeException('Capture failed.');
        }
    }

    public function translateRotatedPoint(array &$point)
    {
        Position::assertPoint($point);
        if($this->rotateFix){
            $point[Position::X] = $point[Position::X] + $point[Position::Y];
            $point[Position::Y] = $point[Position::X] - $point[Position::Y];
            $point[Position::X] = $point[Position::X] - $point[Position::Y];
        }
        return $this;
    }

    public function translatePoint(array &$point, $width = null, $height = null)
    {
        Position::assertPoint($point);
        if((is_float($point[Position::X]) && $point[Position::X] <= 1) || (is_float($point[Position::Y]) && $point[Position::Y] <= 1)){
            if(!$width && !$this->width) throw new \InvalidArgumentException('Screen is not initialized, capture or load an image first.');
            if(!$height && !$this->height) throw new \InvalidArgumentException('Screen is not initialized, capture or load an image first.');
            $point[Position::X] = intval(round($point[Position::X] * (($width ?: $this->width) - 1)));
            $point[Position::Y] = intval(round($point[Position::Y] * (($height ?: $this->height) - 1)));
        }
        return $this;
    }

    public function getPoint(array $point)
    {
        $this->translatePoint($point);
        return $point;
    }

    public function translateRect(array &$rect)
    {
        Position::assertRect($rect);
        list($P1, $P2) = array_map([$this, 'getPoint'], Position::getRectVertex($rect));
        $rect[Position::X1] = $P1[Position::X];
        $rect[Position::Y1] = $P1[Position::Y];
        $rect[Position::X2] = $P2[Position::X];
        $rect[Position::Y2] = $P2[Position::Y];
        return $this;
    }

    public function getRect(array $rect)
    {
        $this->translateRect($rect);
        return $rect;
    }

    public function getColor($x, $y)
    {
        if(!is_resource($this->gd)) throw new \BadMethodCallException("No image loaded or captured now.");
        return imagecolorat($this->gd, $x, $y);
    }

    public function getColorRGB($x, $y)
    {
        $rgb = $this->getColor($x, $y);
        return [self::COLOR_R => ($rgb >> 16) & 0xFF, self::COLOR_G => ($rgb >> 8) & 0xFF, self::COLOR_B => $rgb & 0xFF];
    }

    public function parseColor($color)
    {
        static $cache;
        if(!isset($cache)) $cache = [];
        if(isset($cache[$color])) return $cache[$color];
        if(!preg_match(self::REGEXP_COLOR, $color, $match)) throw new \InvalidArgumentException("Invalid color notation '$color'.");
        $cache[$color] = [self::COLOR_R => hexdec($match[1]), self::COLOR_G => hexdec($match[2]), self::COLOR_B => hexdec($match[3])];
        $cache[$color][self::COLOR_D] = isset($match[4]) ? intval($match[4]) : $this->defaultDistance;
        return $cache[$color];
    }

    public static function colorToHTML(array &$color)
    {
        return sprintf('#%X%X%X', $color[self::COLOR_R], $color[self::COLOR_G], $color[self::COLOR_B]);
    }

    /**
     * Low-level Actual (X,Y) on the canvas
     *
     * @param integer $x
     * @param integer $y
     * @param string $color
     * @return bool
     */
    public function compare($x, $y, $color)
    {
        if($x < 0 || $y < 0 || $x >= $this->x || $y >= $this->y){
            $this->logger->warning("Attempt to access pixel %d*%d, while the image size is %u*%u.",
                [$x, $y, $this->x, $this->y]);
            return false;
        }
        $testColor = $this->parseColor($color);
        $imageColor = $this->getColorRGB($x, $y);
        $this->logger->debug('Color comparison at %u*%u, actual=%s, expected=%s, tolerance=%u',
            [$x, $y, self::colorToHTML($imageColor), self::colorToHTML($testColor), $testColor[self::COLOR_D]]);
        if($testColor[self::COLOR_D]){
            return sqrt(
                ($imageColor[self::COLOR_R] - $testColor[self::COLOR_R]) ^ 2 +
                ($imageColor[self::COLOR_G] - $testColor[self::COLOR_G]) ^ 2 +
                ($imageColor[self::COLOR_B] - $testColor[self::COLOR_B]) ^ 2) < $testColor[self::COLOR_D];
        }else{

            return $imageColor[self::COLOR_R] == $testColor[self::COLOR_R] &&
            $imageColor[self::COLOR_G] == $testColor[self::COLOR_G] &&
            $imageColor[self::COLOR_B] == $testColor[self::COLOR_B];
        }
    }

    public function compareRotated($x, $y, $color)
    {
        if($this->rotateFix){
            $x = $x + $y;
            $y = $x - $y;
            $x = $x - $y;
        }
        return $this->compare($x, $y, $color);
    }

    /**
     * Support both absolute and relative point with rotate fix.
     *
     * @param array $point
     * @param string $color
     * @return bool
     */
    public function comparePoint(&$point, $color)
    {
        $this->logger->debug('Request virtual dot comparison at %.3f*%.3f', [$point[Position::X], $point[Position::Y]]);
        $this->translatePoint($point);
        return $this->compareRotated($point[Position::X], $point[Position::Y], $color);
    }

    public function comparePos($pos, $color)
    {
        if(isset($pos[Position::X]) && isset($pos[Position::Y])){
            return $this->comparePoint($pos, $color);
        }else{
            list($P1, $P2) = Position::getRectVertex($pos);
            return $this->comparePoint($P1, $color) && $this->comparePoint($P2, $color);
        }
    }

    public function comparePositions(array $positions, $color)
    {
        foreach($positions as $pos){
            if(!$this->comparePos($pos, $color)) return false;
        }
        return true;
    }

    public function compareRules(array $rules)
    {
        foreach($rules as $color => $positions){
            if(!$this->comparePositions($positions, $color)) return false;
        }
        return true;
    }

    public static function assertRules(array $rules)
    {
        foreach($rules as $color => $positions){
            if(!preg_match(self::REGEXP_COLOR, $color)) throw new \InvalidArgumentException("Invalid color notation '$color'.");
            if(!is_array($positions)) throw new \InvalidArgumentException('Points array in rule entry does not exist.');
            foreach($positions as $position){
                try{
                    Position::assertPoint($position);
                }catch(\Exception $e){
                    Position::assertRect($position);
                }
            }
        }
        return true;
    }

}
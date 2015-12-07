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

    public function translatePoint(array &$point, $width = null, $height = null)
    {
        Position::assertPoint($point);
        if($this->rotateFix){
            $point[Position::X] = $point[Position::X] + $point[Position::Y];
            $point[Position::Y] = $point[Position::X] - $point[Position::Y];
            $point[Position::X] = $point[Position::X] - $point[Position::Y];
        }
        // Process for relative value point
        if($point[Position::X] < 1 && $point[Position::Y] < 1){
            $point[Position::X] *= $width ?: $this->width;
            $point[Position::Y] *= $height ?: $this->height;
        }
        return $point;
    }

    public function getPoint(array $point)
    {
        return $this->translatePoint($point);
    }

    public function translateRect(array &$rect)
    {
        Position::assertRect($rect);
        list($P1, $P2) = array_map([$this, 'getPoint'], Position::getRectVertex($rect));
        $rect[Position::X1] = $P1[Position::X];
        $rect[Position::Y1] = $P1[Position::Y];
        $rect[Position::X2] = $P2[Position::X];
        $rect[Position::Y2] = $P2[Position::Y];
        return $rect;
    }

    public function getRect(array $rect)
    {
        return $this->translateRect($rect);
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
        if(!preg_match('/([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})(?::?(\d+))?/i', $color, $match)) throw new \InvalidArgumentException("Invalid color notation '$color'.");
        $cache[$color] = [self::COLOR_R => hexdec($match[1]), self::COLOR_G => hexdec($match[2]), self::COLOR_B => $match[3]];
        $cache[$color][self::COLOR_D] = isset($match[4]) ? intval($match[4]) : $this->defaultDistance;
        return $cache[$color];
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
        $testColor = self::parseColor($color);
        $imageColor = $this->getColorRGB($x, $y);
        return sqrt(
            ($imageColor[self::COLOR_R] - $testColor[self::COLOR_R]) ^ 2 +
            ($imageColor[self::COLOR_G] - $testColor[self::COLOR_G]) ^ 2 +
            ($imageColor[self::COLOR_B] - $testColor[self::COLOR_B]) ^ 2) < $testColor[self::COLOR_D];
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
        self::translatePoint($point);
        return $this->compare($point[Position::X], $point[Position::Y], $color);
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
            self::parseColor($color);
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
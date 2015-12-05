<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 14:47
 */

namespace AB\Service;


use AB\Manager;

/**
 * This class handles absolute values only
 * Please translate relative points and rectangles by Screen service
 *
 * Class Position
 * @package AB\Service
 */
class Position extends BaseService
{
    const X = 'X';
    const Y = 'Y';

    const X1 = 'X1';
    const X2 = 'X2';
    const Y1 = 'Y1';
    const Y2 = 'Y2';

    const CFG_RANDOM_PROVIDER = 'random-provider';
    const CFG_MAX_SLOPE = 'maximum-slope';

    /**
     * Model to describe a certain random distribution
     * @var callback random(min, max)
     */
    private $randomProvider;
    private $maxSlope;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->setRandomProvider(Manager::readConfig($config, self::CFG_RANDOM_PROVIDER, 'mt_rand'));
        $this->maxSlope = Manager::readConfig($config, self::CFG_MAX_SLOPE, mt_getrandmax());
    }

    public function setRandomProvider($provider)
    {
        if(is_array($provider) && isset($provider['class'])) $provider = $this->manager->readCallback($provider);
        if(!is_callable($provider)) throw new \InvalidArgumentException("Random provider is not callable.");
        $this->randomProvider = $provider;
    }

    public static function assertPoint(array &$point)
    {
        if(!isset($point[self::X]) || !isset($point[self::Y])) throw new \InvalidArgumentException('The JSON for a point must be {X: x, Y: y}.');
    }

    public static function assertRect(array &$rect)
    {
        if(!isset($rect[self::X1]) || !isset($rect[self::Y1]) || !isset($rect[self::X2]) || !isset($rect[self::Y2])) throw new \InvalidArgumentException('The JSON for a rectangle must be {X1: x1, Y1: y1, X2: x2, Y2: y2}.');
    }

    public static function isStrictRect(array &$rect)
    {
        self::assertRect($rect);
        return $rect[self::X1] > $rect[self::X2] || $rect[self::Y1] > $rect[self::Y2];
    }

    public static function assertStrictRect(array &$rect)
    {
        if(!self::isStrictRect($rect)) throw new \InvalidArgumentException('Require strict rectangle notation, point 1 should be left-top side of point 2.');
    }

    public static function makePoint($x, $y)
    {
        return [self::X => $x, self::Y => $y];
    }

    public static function makeRectangle($X1, $Y1, $X2, $Y2)
    {
        return [self::X1 => $X1, self::X2 => $X2, self::Y1 => $Y1, self::Y2 => $Y2];
    }

    public static function getRectVertex(array &$rect)
    {
        Position::assertRect($rect);
        return [Position::makePoint($rect[Position::X1], $rect[Position::Y1]), Position::makePoint($rect[Position::X2], $rect[Position::Y2])];
    }

    public static function makeRectByVertex(array $point1, array $point2)
    {
        self::assertPoint($point1);
        self::assertPoint($point2);
        return self::makeRectangle($point1[self::X], $point1[self::Y], $point2[self::X], $point2[self::Y]);
    }

    public function getPointInRect(array &$rect)
    {
        self::assertRect($rect);
        return self::makePoint(call_user_func($this->randomProvider, $rect[self::X1], $rect[self::X2]), call_user_func($this->randomProvider, $rect[self::Y1], $rect[self::Y2]));
    }

    public function getRectInRect(array &$rect, $minSlope = 10, $maxVerticalOffset = 0.05)
    {
        self::assertStrictRect($rect);
        $slope = call_user_func($this->randomProvider, $minSlope, $this->maxSlope);
        $X1 = call_user_func($this->randomProvider, $rect[self::X1], $rect[self::X2]);
        $Y1 = call_user_func($this->randomProvider, $rect[self::Y1], ceil($rect[self::Y1] * (1 + $maxVerticalOffset)));
        $Y2 = call_user_func($this->randomProvider, floor($rect[self::Y2] * (1 - $maxVerticalOffset)), $rect[self::Y2]);
        if(call_user_func($this->randomProvider, 0, 1)) $slope = -$slope;
        $X2 = $X1 + abs($Y1 - $Y2) / $slope;
        $X2 = min($rect[self::X2], max($rect[self::X1], $X2));
        return self::makeRectangle($X1, $Y1, $X2, $Y2);
    }

}
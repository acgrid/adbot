<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 10:52
 */

namespace AB\Service\OCR;


use AB\Manager;
use AB\Service\BaseService;
use AB\Service\Position;
use AB\Service\Screen;

abstract class Base extends BaseService
{
    const CFG_SCAN_MODE = 'scan';
    const CFG_RULES = 'rules';
    const CFG_COLOR = 'color';
    const CFG_WIDTH = 'width';
    const CFG_MARGIN = 'margin';

    const SCAN_FIXED = 'Fixed';
    const SCAN_ADAPTIVE = 'Auto';

    const RULE_JUDGE = 'J';
    const RULE_COLOR = 'C';
    const RULE_TRUE = 'T';
    const RULE_FALSE = 'F';

    const ALIGN_LEFT = 'L';
    const ALIGN_RIGHT = 'R';

    /**
     * @var Screen
     */
    protected $screen;

    // default config
    protected $rules = [];
    protected $defaultMode;
    protected $defaultColor;
    protected $defaultWidth;
    protected $defaultMargin;

    // Working config
    protected $rule;
    protected $mode;
    protected $color;
    /**
     * Used for fixed scan distance
     * @var float
     */
    protected $width;
    protected $margin;
    /**
     * @var array
     */
    protected $scanCache;
    /**
     * @var string
     */
    protected $align;
    protected $step;
    protected $result;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->screen = Screen::instance($manager, $this->app);
        if(isset($config[self::CFG_RULES])) $this->setRules($config[self::CFG_RULES]);
        $this->setDefaultConfig($config);
    }

    public static function checkMode($mode)
    {
        if($mode != self::SCAN_ADAPTIVE && $mode != self::SCAN_FIXED) throw new \InvalidArgumentException("Scan mode '$mode' is not supported.");
        return $mode;
    }

    public static function getRectAlign(array &$rect)
    {
        Position::assertRect($rect);
        return $rect[Position::X1] < $rect[Position::X2] ? self::ALIGN_LEFT : self::ALIGN_RIGHT;
    }

    public static function checkDistance($value, $allowNegative = false)
    {
        $value = (float) $value;
        if($value < ($allowNegative ? -1 : 0) || $value > 1) throw new \InvalidArgumentException("OCR distance must be decimal percentage.");
        return $value;
    }

    public static function checkRule(array &$rule)
    {
        if(!isset($rule[self::RULE_JUDGE]) || !is_array($rule[self::RULE_JUDGE])) throw new \InvalidArgumentException('OCR Rule lacks judgement or is not array.');
        foreach($rule[self::RULE_JUDGE] as &$point) Position::assertPoint($point);
        if(!array_key_exists(self::RULE_TRUE, $rule)) throw new \InvalidArgumentException('OCR Rule lacks true decision.');
        if(!array_key_exists(self::RULE_FALSE, $rule)) throw new \InvalidArgumentException('OCR Rule lacks false decision.');
        if(is_array($rule[self::RULE_TRUE])) self::checkRule($rule[self::RULE_TRUE]);
        if(is_array($rule[self::RULE_FALSE])) self::checkRule($rule[self::RULE_FALSE]);
        return true;
    }

    /**
     * Check rules in setter only
     * Skip assertions and key existence checks in OCR process
     * @param array $rules
     * @return $this
     */
    public function setRules(array $rules)
    {
        foreach($rules as $rule) self::checkRule($rule);
        $this->rules = $rules;
        return $this;
    }

    public function getRule($key = null)
    {
        if($key === null) return $this->rules;
        return isset($this->rules[$key]) ? $this->rules[$key] : null;
    }

    public function setDefaultConfig(&$config)
    {
        $this->defaultMode = self::checkMode(Manager::readConfig($config, self::CFG_SCAN_MODE));
        $this->defaultColor = $this->screen->parseColor(Manager::readConfig($config, self::CFG_COLOR, 'FFFFFF:4'));
        $this->defaultWidth = self::checkDistance(Manager::readConfig($config, self::CFG_WIDTH, 0.001));
        $this->defaultMargin = self::checkDistance(Manager::readConfig($config, self::CFG_MARGIN, 0), true);
        return $this;
    }

    public function setConfig(&$config)
    {
        $this->mode = isset($config[self::CFG_SCAN_MODE]) ? self::checkMode($config[self::CFG_SCAN_MODE]) : $this->defaultMode;
        $this->color = isset($config[self::CFG_COLOR]) && $this->screen->parseColor($config[self::CFG_COLOR]) ? $config[self::CFG_COLOR] : $this->defaultColor;
        if($this->mode === self::SCAN_FIXED){
            $this->width = isset($config[self::CFG_WIDTH]) ? self::checkDistance($config[self::CFG_WIDTH]) : $this->defaultWidth;
            $this->margin = isset($config[self::CFG_MARGIN]) ? self::checkDistance($config[self::CFG_MARGIN]) : $this->defaultMargin;
        }
        return $this;
    }

    public static function range($start, $end)
    {
        if(!is_int($start) || !is_int($end) || $start === $end) throw new \InvalidArgumentException('Scan range must be integer and can not be the same.');
        $step = $start < $end ? 1 : -1;
        for($i = $start; $i <> $end + $step; $i += $step) yield $i;
    }

    protected function compareCached($x, $y)
    {
        if(!isset($this->scanCache[$x][$y])){
            $this->scanCache[$x][$y] = $this->screen->compareRotated($x, $y, $this->color);
        }
        return $this->scanCache[$x][$y];
    }

    protected function searchInLine($direction, $axis, $start, $end)
    {
        if($direction <> Position::X && $direction <> Position::Y) throw new \InvalidArgumentException("Direction is unknown.");
        if(!is_int($axis) || !is_int($start) || !is_int($end) || $axis < 0 || $start < 0 || $end < 0) throw new \InvalidArgumentException("Actual pixel point is required for line search.");
        $constantAxis = $direction === Position::X ? Position::Y : Position::X;
        $point[$constantAxis] = $axis;
        $iteration = self::range($start, $end);
        foreach($iteration as $value){
            $point[$direction] = $value;
            if($this->compareCached($point[Position::X], $point[Position::Y])){
                $this->logger->debug('Line search on %s axis found at (%u,%u), requested %u to %u.',
                    [$direction, $point[Position::X], $point[Position::Y], $start, $end]);
                return $value;
            }
        }
        $this->logger->debug('Line search found nothing. %s=%u - %u, %s=%u.', [$direction, $start, $end, $constantAxis, $axis]);
        return false;
    }

    /**
     * Determine how the rect pointer moves
     * @return int
     */
    protected function ocrStep()
    {
        if(!isset($this->step)) $this->step = $this->align == self::ALIGN_LEFT ? $this->width + $this->margin : -$this->width - $this->margin;
        return $this->step;
    }

    /**
     * Programmatic processing of recognized char
     * Basic implementation uses string literal simply, override me if needed
     *
     * @param $char
     * @return mixed
     */
    protected function ocrResult(&$char)
    {
        $this->logger->info("OCR char: %s", [$char]);
        return strval($char);
    }

    /**
     * Determine how recognized chars concat
     * Basic implementation is combining them according to align mode, override me if needed
     *
     * @param $char
     * @return string
     */
    protected function ocrConcat($char)
    {
        return $this->result = $this->align == self::ALIGN_LEFT ? "{$this->result}$char" : "$char{$this->result}";
    }

    /**
     * Determine whether finish OCR if no result obtained
     * The default value Manager::RET_FINISH constant will lead a exit.
     *
     * @return int
     */
    protected function ocrFailure()
    {
        return Manager::RET_FINISH;
    }

    /**
     * Final processing after all OCR operations.
     * Default implementation is dummy, override me if needed
     *
     * @param $result
     * @return mixed
     */
    protected function ocrComplete($result)
    {
        return $result;
    }

    protected function judge(array $judge, array &$rect)
    {
        array_walk($judge, function(&$point) use ($rect){
            $point[Position::X] = ($rect[Position::X1] < $rect[Position::X2] ? $rect[Position::X1] : $rect[Position::X2]) +
                $point[Position::X] * abs($rect[Position::X2] - $rect[Position::X1]);
            $point[Position::Y] = ($rect[Position::Y1] < $rect[Position::Y2] ? $rect[Position::Y1] : $rect[Position::Y2]) +
                $point[Position::Y] * abs($rect[Position::Y2] - $rect[Position::Y1]);
        });
        return $this->screen->comparePositions($judge, $this->color);
    }

    protected function ocrChar(array &$rule, array &$rect)
    {
        $result = $this->judge($rule[self::RULE_JUDGE], $rect) ? $rule[self::RULE_TRUE] : $rule[self::RULE_FALSE];
        if(is_null($result)) return null;
        if(!is_array($result)) return $this->ocrResult($result);
        return $this->ocrChar($result, $rect);
    }

    public function ocr($rule, array &$rect, array $override = [])
    {
        if(!isset($this->rules[$rule])) throw new \InvalidArgumentException("Undefined OCR rule '$rule'.");
        $this->rule = &$this->rules[$rule];
        $this->setConfig($override);
        $this->align = self::getRectAlign($rect);
        $this->result = '';
        if($this->mode === self::SCAN_FIXED){
            $minX = min($rect[Position::X1], $rect[Position::X2]);
            $maxX = max($rect[Position::X1], $rect[Position::X2]);
            if($this->width == 0) throw new \InvalidArgumentException('OCR Width is not initialized or passed.');
            $scanAt = $this->align == self::ALIGN_LEFT ? $minX : $maxX - $this->width;
            $this->logger->debug('Ready to OCR with fixed stepping, Align=%s, Range=%.4f-%.4f, Start=%.4f, Width=%.4f, Margin=%.4f, Step=%.4f',
                [$this->align, $minX, $maxX, $scanAt, $this->width, $this->margin, $this->ocrStep()]);

            while($scanAt >= $minX && $scanAt + $this->width <= $maxX){
                $ocrRect = Position::makeRectangle($scanAt, $rect[Position::Y1], $scanAt + $this->width, $rect[Position::Y2]);
                $char = $this->ocrChar($this->rule, $ocrRect);
                if(is_null($char) && $this->ocrFailure() === Manager::RET_FINISH) break;
                $this->ocrConcat($char);
                $scanAt += $this->ocrStep();
                $this->logger->debug('Forward=%.4f', [$scanAt]);
            }
        }elseif($this->mode === self::SCAN_ADAPTIVE){
            $this->screen->translateRect($rect);
            $this->scanCache = [];
            $minX = min($rect[Position::X1], $rect[Position::X2]);
            $maxX = max($rect[Position::X1], $rect[Position::X2]);
            $minY = min($rect[Position::Y1], $rect[Position::Y2]);
            $maxY = max($rect[Position::Y1], $rect[Position::Y2]);
            $scanAt = $rect[Position::X1];
            $step = $this->align === self::ALIGN_LEFT ? 1 : -1;
            $this->logger->debug('Ready to OCR with adaptive width. Align=%s, Range=%u-%u, Step=%d', [$this->align, $minX, $maxX, $step]);
            while($scanAt >= $minX && $scanAt <= $maxX){
                $Y1 = $maxY;
                $Y2 = $minY;
                // Get X1
                while($scanAt >= $minX && $scanAt <= $maxX){
                    if($Y1 = $this->searchInLine(Position::Y, $scanAt, $minY, $maxY)) break;
                    $scanAt += $step;
                }
                $X1 = $scanAt;
                // Get X2, Y1
                while($scanAt >= $minX && $scanAt <= $maxX &&
                    ($foundY = $this->searchInLine(Position::Y, $scanAt, $minY, $maxY))){
                    if($foundY < $Y1) $Y1 = $foundY;
                    $scanAt += $step;
                }
                $X2 = $scanAt;
                if($scanAt < $minX || $scanAt > $maxX) break;
                // Get Y2
                for($scanY = $maxY; $scanY > $Y1; $scanY--){
                    if($this->searchInLine(Position::X, $scanY, $X1, $X2)){
                        $Y2 = $scanY;
                        break;
                    }
                }
                // OCR
                $ocrRect = Position::makeRectangle($X1, $Y1, $X2, $Y2);
                $this->logger->info('Possible character from (%u,%u) to (%u,%u)', [$X1, $Y1, $X2, $Y2]);
                $char = $this->ocrChar($this->rule, $ocrRect);
                if(is_null($char) && $this->ocrFailure() === Manager::RET_FINISH) break;
                $this->ocrConcat($char);
                $scanAt += $step;
                $this->logger->debug('Forward=%u', [$scanAt]);
            }
        }else{
            throw new \LogicException("OCR scan mode is exceptional.");
        }

        return $this->ocrComplete($this->result);
    }

}
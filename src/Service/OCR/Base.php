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
    const CFG_RULES = 'rules';
    const CFG_COLOR = 'color';
    const CFG_WIDTH = 'width';
    const CFG_MARGIN = 'margin';

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
    protected $defaultColor;
    protected $defaultWidth;
    protected $defaultMargin;

    // Working config
    protected $rule;
    protected $color;
    protected $width;
    protected $margin;
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
        $this->defaultColor = $this->screen->parseColor(Manager::readConfig($config, self::CFG_COLOR, 'FFFFFF:4'));
        $this->defaultWidth = self::checkDistance(Manager::readConfig($config, self::CFG_WIDTH, 0.001));
        $this->defaultMargin = self::checkDistance(Manager::readConfig($config, self::CFG_MARGIN, 0), true);
        return $this;
    }

    public function setConfig(&$config)
    {
        $this->color = isset($config[self::CFG_COLOR]) && $this->screen->parseColor($config[self::CFG_COLOR]) ? $config[self::CFG_COLOR] : $this->defaultColor;
        $this->width = isset($config[self::CFG_WIDTH]) ? self::checkDistance($config[self::CFG_WIDTH]) : $this->defaultWidth;
        $this->margin = isset($config[self::CFG_MARGIN]) ? self::checkDistance($config[self::CFG_MARGIN]) : $this->defaultMargin;
        return $this;
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
        $this->logger->debug("OCR char: %s", [$char]);
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
        if($this->width == 0) throw new \InvalidArgumentException('OCR Width is not initialized or passed.');
        $this->align = self::getRectAlign($rect);

        $min = min($rect[Position::X1], $rect[Position::X2]);
        $max = max($rect[Position::X1], $rect[Position::X2]);
        $scanAt = $this->align == self::ALIGN_LEFT ? $min : $max - $this->width;
        $this->logger->debug('Ready to OCR, Align=%s, Range=%.4f-%.4f, Start=%.4f, Width=%.4f, Margin=%.4f, Step=%.4f',
            [$this->align, $min, $max, $scanAt, $this->width, $this->margin, $this->ocrStep()]);
        $this->result = '';

        while($scanAt >= $min && $scanAt + $this->width <= $max){
            $ocrRect = Position::makeRectangle($scanAt, $rect[Position::Y1], $scanAt + $this->width, $rect[Position::Y2]);
            $char = $this->ocrChar($this->rule, $ocrRect);
            if(is_null($char) && $this->ocrFailure() === Manager::RET_FINISH) break;
            $this->ocrConcat($char);
            $scanAt += $this->ocrStep();
            $this->logger->debug('Forward=%.4f', [$scanAt]);
        }
        return $this->ocrComplete($this->result);
    }

}
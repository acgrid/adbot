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
use AB\Service\IService;
use AB\Service\Position;
use AB\Service\Screen;

abstract class Base extends BaseService implements IService
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
    protected $result;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->screen = Screen::instance($manager, $this->app);
        if(isset($config[self::CFG_RULES]) && is_array($config[self::CFG_RULES])) $this->setRules($config[self::CFG_RULES]);
        $this->setDefaultConfig($config);

    }

    public static function checkDistance($value)
    {
        $value = (float) $value;
        if($value < 0 || $value > 1) throw new \InvalidArgumentException("OCR distance must be decimal percentage (0~1).");
        return $value;
    }

    public static function checkRule(&$rule)
    {
        if(!is_array($rule)) throw new \InvalidArgumentException('OCR Rule entry is not array.');
        if(!isset($rule[self::RULE_JUDGE]) || !is_array($rule[self::RULE_JUDGE])) throw new \InvalidArgumentException('OCR Rule lacks judgement or is not array.');
        foreach($rule[self::RULE_JUDGE] as &$point) Position::assertPoint($point);
        if(!isset($rule[self::RULE_TRUE])) throw new \InvalidArgumentException('OCR Rule lacks true decision.');
        if(!isset($rule[self::RULE_FALSE])) throw new \InvalidArgumentException('OCR Rule lacks false decision.');
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
        $this->defaultMargin = self::checkDistance(Manager::readConfig($config, self::CFG_MARGIN, 0));
        return $this;
    }

    public function setConfig(&$config)
    {
        $this->color = isset($config[self::CFG_COLOR]) ? $this->screen->parseColor($config[self::CFG_COLOR]) : $this->defaultColor;
        $this->width = isset($config[self::CFG_WIDTH]) ? self::checkDistance($config[self::CFG_WIDTH]) : $this->defaultWidth;
        $this->margin = isset($config[self::CFG_MARGIN]) ? self::checkDistance($config[self::CFG_MARGIN]) : $this->defaultMargin;
        return $this;
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
        return strval($char);
    }

    /**
     * Determine how recognized chars concat
     * Basic implementation is combining them according to align mode, override me if needed
     *
     * @param $char
     * @return string
     */
    protected function ocrConcat(&$char)
    {
        return $this->result = $this->align == self::ALIGN_LEFT ? "$char{$this->result}" : "{$this->result}$char";
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
        array_walk($judge, function(&$point, $width, $height) use ($this){
            $this->screen->translatePoint($point, $width, $height);
        }, [abs($rect[Position::X2] - $rect[Position::X1]), abs($rect[Position::Y2] - $rect[Position::Y1])]);
        return $this->screen->comparePositions($judge, $this->color);
    }

    protected function ocrChar(array &$rule, array &$rect)
    {
        $result = $this->judge($rule[self::RULE_JUDGE], $rect) ? $rule[self::RULE_TRUE] : $rule[self::RULE_FALSE];
        if(is_null($result)) return null;
        if(!is_array($result)) return $this->ocrResult($result);
        return $this->ocrChar($result, $rect);
    }

    public function ocr($rule, array &$rect, $override = [])
    {
        if(!isset($this->rules[$rule])) throw new \InvalidArgumentException("Undefined OCR rule '$rule'.");
        $this->screen->translateRect($rect);
        $this->setConfig($override);
        $this->rule = &$this->rules[$rule];

        $this->align = Position::isStrictRect($rect) ? self::ALIGN_LEFT : self::ALIGN_RIGHT;
        $scanAt = $this->align == self::ALIGN_LEFT ? $rect[Position::X1] : $rect[Position::X2] - $this->width;
        $step = $this->align == self::ALIGN_LEFT ? $this->width + $this->margin : -$this->width - $this->margin;
        $this->result = '';

        while($scanAt > $rect[Position::X1] && $scanAt < $rect[Position::X2]){
            $char = $this->ocrChar($this->rule, Position::makeRectangle($scanAt, $rect[Position::Y1], $scanAt + $this->width, $rect[Position::Y2]));
            if(is_null($char) && $this->ocrFailure() === Manager::RET_FINISH) break;
            $this->ocrConcat($this->ocrResult($char));
            $scanAt += $step;
        }
        return $this->ocrComplete($this->result);
    }

}
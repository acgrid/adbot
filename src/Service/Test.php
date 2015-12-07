<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/7
 * Time: 11:31
 */

namespace AB\Service;


use AB\Manager;

class Test extends BaseService
{
    const CFG_TEST_KEY = 'test';
    const CFG_TEST_REQUIRED_KEY = 'test-required';
    const CFG_TEST_PROVIDER = 'provider';

    const DEFAULT_TEST_VALUE = 'foo';

    const STUB_METHOD_STATIC = 'static';
    const STUB_METHOD = 'instance';
    const STUB_METHOD_INVOKE = 'object';
    const STUB_METHOD_CLOSURE = 'closure';

    public $test;
    public $testRequired;
    public $provider;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->test = Manager::readConfig($config, self::CFG_TEST_KEY, self::DEFAULT_TEST_VALUE);
        $this->testRequired = Manager::readConfig($config, self::CFG_TEST_REQUIRED_KEY);
        $this->provider = $manager->readCallback(Manager::readConfig($config, self::CFG_TEST_PROVIDER, function(){
            return self::STUB_METHOD_CLOSURE;
        }));
    }

    public static function methodStatic()
    {
        return self::STUB_METHOD_STATIC;
    }

    public function method()
    {
        return self::STUB_METHOD . $this->testRequired;
    }

    public function __invoke()
    {
        return self::STUB_METHOD_INVOKE . $this->testRequired;
    }

    public function testProvider()
    {
        if(!is_callable($this->provider)) throw new \InvalidArgumentException('Provider is not callable.');
        return call_user_func($this->provider);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 19:58
 */

namespace AB;

use AB\Service\Test;
use Monolog\Handler\StreamHandler;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    const FQN_MANAGER = 'AB\\Manager';
    const NS_APP = 'TestApp';

    const TEST_SERVICE = 'Test';
    /**
     * @var Logger
     */
    private $logger;
    private $configSkeleton;

    public function setUp()
    {
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new StreamHandler('php://output'));
        $this->configSkeleton = [
            Manager::CFG_TITLE => 'Dummy configuration for test use',
            Manager::CFG_VERSION => '1.0',
            Manager::CFG_CONST => [],
            Manager::CFG_SERVICE => [],
            Manager::CFG_COMPONENTS => [],
            Manager::CFG_ACTIONS => [],
        ];
    }

    /**
     * @before
     */
    public function testFactory()
    {
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $this->assertInstanceOf(self::FQN_MANAGER, $manager, 'Basic factory call failure');
        $this->assertInstanceOf(get_class($this->logger), $manager->logger);
        $this->assertEquals(dirname(__DIR__), $manager->path);
    }

    /**
     * @depends testFactory
     */
    public function testConstant()
    {
        $testKey = 'test';
        $testAnotherKey = 'new-test';
        $testValue = 'foobar';
        $this->configSkeleton[Manager::CFG_TITLE] = 'Dummy configuration for constant test.';
        $this->configSkeleton[Manager::CFG_CONST][$testKey] = $testValue;
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $this->assertSame($testValue, $manager->getConstant($testKey));
        try{
            $manager->getConstant('not-exist');
            $this->fail('No exception thrown on constant reading without default value.');
        }catch(\RuntimeException $e){
            $this->logger->debug('Mandatory constant check OK.');
        }
        $this->assertSame($testValue, $manager->getConstant('not-exist', $testValue));
        $this->assertInstanceOf(self::FQN_MANAGER, $manager->setConstant($testAnotherKey, $testValue));
        $this->assertSame($testValue, $manager->getConstant($testAnotherKey));
    }

    /**
     * @depends testConstant
     */
    public function testService()
    {
        /**
         * @var Test $service
         */
        $config = $this->configSkeleton;
        $config[Manager::CFG_TITLE] = 'Dummy configuration for service test.';
        $config[Manager::CFG_SERVICE][Manager::COMMON][self::TEST_SERVICE] = [];
        $serviceCommonConfig = &$config[Manager::CFG_SERVICE][Manager::COMMON][self::TEST_SERVICE];

        // Test 1: Common, failed to provide enough params
        try{
            $manager = Manager::factory($config, $this->logger);
            $manager->getService(Manager::COMMON, self::TEST_SERVICE);
            $this->fail('There should be an exception on service initialization with missing required configuration item.');
        }catch(\RuntimeException $e){
            $this->logger->debug('Service required key check OK.');
        }
        // Test 2: Common, default values
        $valueRequired = 'some-value';
        $serviceCommonConfig[Test::CFG_TEST_REQUIRED_KEY] = $valueRequired;
        $manager = Manager::factory($config, $this->logger);
        $service = $manager->getService(Manager::COMMON, self::TEST_SERVICE);
        $fullServiceClass = $manager->nsService . self::TEST_SERVICE;
        $this->assertInstanceOf($fullServiceClass, $service);
        $this->assertSame($valueRequired, $service->testRequired);
        $this->assertSame(Test::DEFAULT_TEST_VALUE, $service->test);
        $this->assertSame(Test::STUB_METHOD_CLOSURE, $service->testProvider());
        // Test 3: Special with overriding Common, static callback provider
        $valueAppRequired = 'app-value-required';
        $valueApp = 'app-value';
        $config[Manager::CFG_SERVICE][self::NS_APP][self::TEST_SERVICE] = [];
        $serviceAppConfig = &$config[Manager::CFG_SERVICE][self::NS_APP][self::TEST_SERVICE];
        $serviceAppConfig[Test::CFG_TEST_REQUIRED_KEY] = $valueAppRequired;
        $serviceAppConfig[Test::CFG_TEST_KEY] = $valueApp;
        $serviceAppConfig[Test::CFG_TEST_PROVIDER] = [$fullServiceClass, 'methodStatic'];
        $manager = Manager::factory($config, $this->logger);
        $service = $manager->getService(self::NS_APP, self::TEST_SERVICE);
        $this->assertSame($valueAppRequired, $service->testRequired);
        $this->assertSame($valueApp, $service->test);
        $this->assertSame(Test::STUB_METHOD_STATIC, $service->testProvider());
        // Test 4: Object invoke callback
        $valueInvokeRequired = 'invoke-value-required';
        $serviceAppConfig[Test::CFG_TEST_PROVIDER] = [
            Manager::RES_CONFIG_CLASS => $fullServiceClass,
            Test::CFG_TEST_REQUIRED_KEY => $valueInvokeRequired];
        $manager = Manager::factory($config, $this->logger);
        $service = $manager->getService(self::NS_APP, self::TEST_SERVICE);
        $this->assertSame(Test::STUB_METHOD_INVOKE . $valueInvokeRequired, $service->testProvider());
        // Test 5: Object method callback
        $valueCallbackRequired = 'callback-value-required';
        $serviceAppConfig[Test::CFG_TEST_PROVIDER][Manager::RES_CONFIG_METHOD] = 'method';
        $serviceAppConfig[Test::CFG_TEST_PROVIDER][Test::CFG_TEST_REQUIRED_KEY] = $valueCallbackRequired;
        $manager = Manager::factory($config, $this->logger);
        $service = $manager->getService(self::NS_APP, self::TEST_SERVICE);
        $this->assertSame(Test::STUB_METHOD . $valueCallbackRequired, $service->testProvider());
    }

    /**
     * @depends testService
     */
    public function testComponent()
    {

    }

    /**
     * @depends testComponent
     */
    public function testRun()
    {

    }

}

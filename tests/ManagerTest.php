<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 19:58
 */

namespace AB;

use AB\Action\Common\FinishBlock;
use AB\Action\Common\PrintMessage;
use AB\Action\Common\TestAction;
use AB\Action\TestApp\TestAction as AppTestAction;
use AB\Service\Test;
use Monolog\Handler\StreamHandler;

class ManagerTest extends \PHPUnit_Framework_TestCase
{
    const FQN_MANAGER = 'AB\\Manager';
    const NS_APP = 'TestApp';

    const TEST_SERVICE = 'Test';
    const TEST_ACTION = 'TestAction';
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
        /**
         * @var TestAction $action
         */
        $this->configSkeleton[Manager::CFG_TITLE] = 'Dummy configuration for components test.';
        $this->configSkeleton[Manager::CFG_COMPONENTS][Manager::COMMON][self::TEST_ACTION] = [];
        // Test 1: Define common, get common
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $fullCommonAction = $manager->nsAction . Manager::COMMON . '\\' . self::TEST_ACTION;
        $fullAppAction = $manager->nsAction . self::NS_APP . '\\' . self::TEST_ACTION;
        $action = $manager->getComponent(Manager::COMMON, self::TEST_ACTION);
        $this->assertInstanceOf($fullCommonAction, $action);
        $action->run();
        $this->assertSame(TestAction::DEFAULT_VALUE, $action->test);
        $message = 'foo';
        $action->run([TestAction::CFG_TEST => $message]);
        $this->assertSame($message, $action->test);
        // Test 2: Define common, get app
        $action = $manager->getComponent(self::NS_APP, self::TEST_ACTION);
        $this->assertInstanceOf($fullAppAction, $action);
        $action->run();
        $this->assertSame(AppTestAction::DEFAULT_VALUE, $action->test);
        // Test 2.5: constructor passing
        $message = 'init-common';
        $this->configSkeleton[Manager::CFG_COMPONENTS][Manager::COMMON][self::TEST_ACTION] = [TestAction::CFG_TEST => $message];
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $action = $manager->getComponent(self::NS_APP, self::TEST_ACTION);
        $action->run();
        $this->assertSame($message, $action->test);
        // Test 3: Define app, get common
        $message = 'init-app';
        $this->configSkeleton[Manager::CFG_COMPONENTS][self::NS_APP][self::TEST_ACTION] = [TestAction::CFG_TEST => $message];
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $action = $manager->getComponent(Manager::COMMON, self::TEST_ACTION);
        $this->assertSame(TestAction::DEFAULT_VALUE, $action::DEFAULT_VALUE);
        $action = $manager->getComponent(self::NS_APP, self::TEST_ACTION);
        $action->run();
        $this->assertSame($message, $action->test);
        // Test 4: Define app, get app by full qualified class name
        $this->assertSame($action, $manager->getComponent(self::NS_APP, $fullAppAction));
        // Test 5: Temporary object
        $this->assertNotSame($action, $manager->getComponent(self::NS_APP, $fullAppAction, true));
    }

    /**
     * @depends testComponent
     */
    public function testAction()
    {
        $this->configSkeleton[Manager::CFG_TITLE] = 'Dummy configuration for action test.';
        $manager = Manager::factory($this->configSkeleton, $this->logger);
        $action = $manager->getAction(self::TEST_ACTION);
        $this->assertInstanceOf($manager->nsAction . Manager::COMMON . '\\' . self::TEST_ACTION, $action);
        $this->assertNotSame($action, $manager->getAction('@' . self::TEST_ACTION));
        $this->assertSame($action, $manager->getAction(Manager::COMMON . '\\' . self::TEST_ACTION));
        $this->assertInstanceOf($manager->nsAction . self::NS_APP . '\\' . self::TEST_ACTION, $manager->getAction(self::NS_APP . '\\' . self::TEST_ACTION));
    }

    /**
     * @depends testComponent
     */
    public function testRun()
    {
        $this->configSkeleton[Manager::CFG_TITLE] = 'Dummy configuration for running test.';
        $this->configSkeleton[Manager::CFG_COMPONENTS][Manager::COMMON]['FinishBlock'] = [FinishBlock::CFG_COUNT => 2];
        $this->configSkeleton[Manager::CFG_COMPONENTS][self::NS_APP]['PrintMessage'] = [PrintMessage::CFG_MESSAGE => 'Test Action Message'];
        $this->configSkeleton[Manager::CFG_ACTIONS] = [
            Manager::ACTION_INIT => ['PrintMessage', self::NS_APP . '\\PrintMessage',
                [Manager::RES_CONFIG_CLASS => self::NS_APP . '\\RetryAction', Manager::RES_CONFIG_RETRY => 2, Manager::RES_CONFIG_RETRY_DELAY => 2000]
            ],
            Manager::ACTION_LOOP => [[Manager::RES_CONFIG_CLASS => 'PrintMessage', PrintMessage::CFG_MESSAGE => 'Loop message'], 'FinishBlock'],
            Manager::ACTION_FINAL => [self::NS_APP . '\\TestAction']
        ];
        $this->assertEquals(Manager::RET_LOOP, Manager::run($this->configSkeleton, $this->logger));
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 12:26
 */

namespace AB\Service;


use AB\Manager;

class Delay extends BaseService
{
    const CFG_DELAY_PROVIDER = 'delay-provider';
    const CFG_RANDOM_PROVIDER = 'random-provider';
    const CFG_DELAY_UNIT = 'delay-unit';

    /**
     * Implementation of script execution pause
     * @var callback sleep(milliseconds)
     */
    private $delayProvider;
    /**
     * Model to describe a certain random distribution
     * @var callback random(min, max)
     */
    private $randomProvider;

    /**
     * The time slice for every delay, used for console output control
     * @var integer
     */
    private $delayUnit;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->setDelayProvider(Manager::readConfig($config, self::CFG_DELAY_PROVIDER, 'usleep'));
        $this->setRandomProvider(Manager::readConfig($config, self::CFG_RANDOM_PROVIDER, 'mt_rand'));
        $this->delayUnit = Manager::readConfig($config, self::CFG_DELAY_UNIT, 1000);
    }

    public function setDelayProvider($provider)
    {
        if(is_array($provider) && isset($provider[Manager::RES_CONFIG_CLASS])) $provider = $this->manager->readCallback($provider);
        if(!is_callable($provider)) throw new \InvalidArgumentException("Delay provider is not callable.");
        $this->delayProvider = $provider;
    }

    public function setRandomProvider($provider)
    {
        if(is_array($provider) && isset($provider[Manager::RES_CONFIG_CLASS])) $provider = $this->manager->readCallback($provider);
        if(!is_callable($provider)) throw new \InvalidArgumentException("Random provider is not callable.");
        $this->randomProvider = $provider;
    }

    public function delay($ms)
    {
        $this->logger->info('Delay for %u milliseconds.', [$ms]);
        $wait = $ms > $this->delayUnit ? $this->delayUnit : $ms;
        $digits = strlen(strval($ms)) - 3; // 12,000 ms
        if($digits > 0) printf('Waiting %u seconds, remaining %ss.', $ms / 1000, str_repeat(' ', $digits));
        $digits += 2;
        do{
            call_user_func($this->delayProvider, $wait);
            $ms -= $wait;
            printf("\033[%uD%us.", $digits, $ms / 1000);
        }while($ms > 0);
        echo "\033[K\n";
        return true;
    }

    public function delayRange($min, $max)
    {
        return $this->delay(call_user_func($this->randomProvider, $min, $max));
    }

    public function delayOffset($ms, $offset)
    {
        if(!is_int($ms) || !is_int($offset)) throw new \InvalidArgumentException("Delay param must be integer.");
        if($offset <= 0 || $ms <= 0 || $offset > $ms) throw new \InvalidArgumentException("Delay param is not positive value or offset is higher than base value.");
        return $this->delay(call_user_func($this->randomProvider, $ms - $offset, $ms + $offset));
    }

}
<?php
namespace AB\Random;

use AB\Manager;
class Delay
{
    public $waitPiece = 1000;
    /**
     * @var AB\Manager
     */
    private $context;
    private $randomProvider;
    
    public function __construct(Manager $context)
    {
        $this->context = $context;
    }
    
    public function delay($usec)
    {
        $this->context->logger->info('Delay for %u milliseconds.', [$usec]);
        $wait = $usec > $this->waitPiece ? $this->waitPiece : $usec;
        $waitdigit = strlen(strval($usec)) - 3; // 12,000 ms
        if($waitdigit > 0) printf('Waiting %u seconds, remaining %ss.', $usec / 1000, str_repeat(' ', $waitdigit));
        $waitdigit += 2;
        do{
            printf("\033[%uD%us.", $waitdigit, $usec / 1000);
            usleep($wait * 1000);
            $usec -= $wait;
        }while($usec > 0);
        echo "\033[K\n";
    }
    
    public function delayCentralRandom($sec, $offset = 0)
    {
        $this->delay($sec * 1000 + $offset * mt_rand(0, $offset));
    }
    
    public function delayBoundaryRandom($low, $high)
    {
        $this->delay(mt_rand($low, $high));
    }
}

?>
<?php
namespace AB;

interface GameManagerInterface
{
    public function __construct(Manager $context, array $config);
    /**
     * Get namespace identifier for this game
     * @return string
     */
    public function getGameName();
    /**
     * Get Android package name
     * @return string
     */
    public function getAppPackageName();
    /**
     * Get the Random\Delay helper for this game
     * @return Random\Delay
     */
    public function helperDelay();
    /**
     * Get the Random\Position helper for this game
     * @return Random\Position
     */
    public function helperPosition();
}
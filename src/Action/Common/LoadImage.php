<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/5
 * Time: 15:52
 */

namespace AB\Action\Common;


use AB\Action\BaseAction;
use AB\Manager;
use AB\Service\Screen;

class LoadImage extends BaseAction
{
    const CFG_FILENAME = 'filename';
    const CFG_ORIENTATION = 'orientation';
    /**
     * @var Screen
     */
    protected $screen;

    public function __construct(Manager $manager, array $config)
    {
        parent::__construct($manager, $config);
        $this->screen = Screen::instance($manager, $this->app);
    }

    public function run(array $context = [])
    {
        $filename = Manager::readConfig($context, self::CFG_FILENAME);
        $orientation = Manager::readConfig($context, self::CFG_ORIENTATION, Screen::AUTO);
        if(!is_readable($filename)) throw new \RuntimeException("Image '$filename' is unable to read.");
        try{
            $this->screen->load($filename, $orientation);
            return Manager::RET_LOOP;
        }catch(\Exception $e){
            $this->logger->error("Image '$filename' is corrupt or not a PNG file.");
            return $this->errorReturnCode;
        }
    }

}
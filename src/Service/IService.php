<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 11:01
 */

namespace AB\Service;


use AB\Manager;

interface IService
{
    public function __construct(Manager $manager, array $config);
    public static function instance(Manager $manager, $app = Manager::COMMON);
}
<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 10:23
 */

namespace AB\Action;


use AB\Manager;

interface IAction
{
    public function __construct(Manager $manager, array $config);
    public static function instance(Manager $manager, $app = Manager::COMMON);
    public function run(array $context = []);
}
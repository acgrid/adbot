<?php
namespace AB;

interface ServiceInterface
{
    public function __construct(Manager $context);
    public static function instance(Manager $context);
}
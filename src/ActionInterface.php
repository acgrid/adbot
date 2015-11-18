<?php
namespace AB;

interface ActionInterface
{
    public function run(Manager $context);
}
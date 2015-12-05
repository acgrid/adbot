<?php
/**
 * Created by PhpStorm.
 * User: acgrid
 * Date: 2015/12/4
 * Time: 17:45
 */

namespace AB\Service\OCR;


class Number extends Base
{
    protected function ocrComplete($result)
    {
        return intval($this->result);
    }
}
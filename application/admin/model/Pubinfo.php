<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-05-14
 * Time: 10:47
 */

namespace app\admin\model;


class Pubinfo extends Base
{
    public function unique($cbcode, $pubinfo_id = 0)
    {
        $pubinfo_id = self::field("pubinfo_id")->where(array(
            "cbcode" => $cbcode,
            "pubinfo_id" => array("neq", $pubinfo_id)
        ))->find();

        if ($pubinfo_id) {
            return false;
        }
        return true;
    }
}
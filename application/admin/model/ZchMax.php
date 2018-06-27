<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 17:24
 */

namespace app\admin\model;


class ZchMax extends Base
{
    public function unique($tsg_code, $clc, $mt_id, $zch_max_id = 0)
    {
        $zch_max_id = $this->field("zch_max_id")->where(array(
            "tsg_code" => $tsg_code,
            "clc" => $clc,
            "mt_id" => $mt_id,
            "zch_max_id" => array("neq", $zch_max_id)
        ))->find();

        if ($zch_max_id) {
            return false;
        }

        return true;
    }
}
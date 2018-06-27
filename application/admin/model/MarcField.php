<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/14
 * Time: 16:59
 */

namespace app\admin\model;


class MarcField extends Base
{
    public function unique($zd_name, $mt_id, $marc_field_id = 0)
    {
        $marc_field_id = self::field("marc_field_id")->where(array(
            "zd_name" => $zd_name,
            "mt_id" => $mt_id,
            "marc_field_id" => array("neq", $marc_field_id)
        ))->find();

        if ($marc_field_id) {
            return false;
        }

        return true;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/15
 * Time: 11:51
 */

namespace app\admin\model;


class MarcTpl extends Base
{

    public static function unique($tpl_name, $marc_tpl_id = 0)
    {
        $marc_tpl_id = self::field("marc_tpl_id")->where(array(
            "tpl_name"    => $tpl_name,
            "marc_tpl_id" => array("neq", $marc_tpl_id)
        ))->find();

        if ($marc_tpl_id) {
            return false;
        }

        return true;
    }

    public static function get_tpl($marc_tpl_id)
    {
        $tpl_info = self::where(['marc_tpl_id'=>$marc_tpl_id])->find();
        $tpl = (!empty($tpl_info) ? $tpl_info["tpl"] : "");
        return unserialize($tpl);
    }
}
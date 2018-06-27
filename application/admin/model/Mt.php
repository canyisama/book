<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 16:54
 */

namespace app\admin\model;


class Mt extends Base
{
    public static function unique($mt_code, $mt_id = 0)
    {
        $mt_code = self::field("mt_code")->where(array(
            "mt_code" => $mt_code,
            "mt_id" => array("neq", $mt_id)
        ))->find();

        if ($mt_code) {
            return false;
        }

        return true;
    }

    public static function get_list($option = array())
    {
        $order = (isset($option["order"]) ? $option["order"] : "sort_num");
        $field = (isset($option["field"]) ? $option["field"] : "mt_id,mt_code");
        return self::field($field)->where($option["where"])->order($order)->select();
    }

    public static function get_mapper($mt_id)
    {
        $mapper_info = self::where("mt_id=$mt_id")->find();
        $mapper = (!empty($mapper_info) ? $mapper_info["mapper"] : "");
        return unserialize($mapper);
    }

    public static function get_field_list($mt_id)
    {
        $mod_Marc_field = d("Marc_field");
        $Marc_field_info = $mod_Marc_field->field("zd_name,zd_text_jd")->where(['mt_id' => $mt_id])->select();
        $Marc_field_temp = array();

        foreach ($Marc_field_info as $item) {
            $Marc_field_temp[$item["zd_name"]] = $item["zd_text_jd"];
        }

        return $Marc_field_temp;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-05-08
 * Time: 17:02
 */

namespace app\admin\model;


class Doctype extends Base
{
    public static function get_list($option = array())
    {
        $order = (isset($option["order"]) ? $option["order"] : "sort_num");
        $field = (isset($option["field"]) ? $option["field"] : "doctype_id,dt_name");
        return self::field($field)->where($option["where"])->order($order)->select();
    }
}
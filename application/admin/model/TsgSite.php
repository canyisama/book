<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/19
 * Time: 11:37
 */

namespace app\admin\model;

/**
 * Class TsgSite
 * @package app\admin\model
 * 分馆馆藏地址模型类
 */
class TsgSite extends Base
{
    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "tsg_site_code");
        $field = (isset($option["field"]) ? $option["field"] : "tsg_site_code,site_name");
        return self::field($field)->where($where)->order($order)->select();
    }
}
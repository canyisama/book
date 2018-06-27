<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/19
 * Time: 11:36
 */

namespace app\admin\model;

/**
 * Class Ltype
 * @package app\admin\model
 * 图书流通类型模型类
 */
class Ltype extends Base
{

    /**
     * @param $ltype_code
     * @param $tsg_code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function unique($ltype_code, $tsg_code)
    {
        $where = [
            'ltype_code' => $ltype_code,
            'tsg_code'   => $tsg_code
        ];
        $ltype_code = self::field("ltype_code")->where($where)->find();

        return empty($ltype_code) ? true : false;

    }

    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "ltype_code");
        $field = (isset($option["field"]) ? $option["field"] : "ltype_code,ltype_name");
        return self::field($field)->where($where)->order($order)->select();
    }

}
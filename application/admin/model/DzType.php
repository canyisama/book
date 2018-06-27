<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-17
 * Time: 22:02
 */

namespace app\admin\model;


class DzType extends Base
{
    /**
     * @param $tsg_code
     * @param $dz_type_code
     * @param bool $field
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 判断读者类型是否存在 | 是否开通馆际借还
     */
    public function isDzType($dz_tsg_code, $dz_type_code, $tsg_code, $field = true)
    {
        $where = [
            'tsg_code' => $dz_tsg_code,
            'dz_type_code' => $dz_type_code
        ];
        $dz_type_info = self::field($field)->where($where)->find();

        if (empty($dz_type_info)) {
            $this->error = "读者类型数据不存在";
            return false;
        }

        if (($tsg_code != $dz_tsg_code) && ($dz_type_info["is_inter"] != 1)) {
//            $this->error = session('adminInfo.tsg_code');
            $this->error = "读者未开通馆际借还";
            return false;
        }
        return $dz_type_info;
    }

    /**
     * @param $tsg_code
     * @param array $option
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *根据分馆代码获取对象
     */
    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "dz_type_code");
        $field = (isset($option["field"]) ? $option["field"] : "dz_type_code,dz_type_name");
        return self::field($field)->where($where)->order($order)->select();
    }

    public function unique($dz_type_code, $tsg_code)
    {
        $dz_type_code_info = $this->field("dz_type_code")->where(array("dz_type_code" => $dz_type_code, "tsg_code" => $tsg_code))->find();

        if ($dz_type_code_info) {
            return false;
        }

        return true;
    }


}
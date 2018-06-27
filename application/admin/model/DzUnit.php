<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-17
 * Time: 22:16
 */

namespace app\admin\model;


class DzUnit extends Base
{
    /**
     * @param $tsg_code
     * @param array $option
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 根据分馆代码获取单位对象
     */
    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "sort_num desc,dz_unit_id desc,unit_name");
        $field = (isset($option["field"]) ? $option["field"] : "unit_name");
        return self::field($field)->where($where)->order($order)->select();
    }

    public function createUnit($tsg_code, $unit_name)
    {
        if ($this->unique($tsg_code, $unit_name)) {
            $add_data = array("tsg_code" => $tsg_code, "unit_name" => $unit_name, "sort_num" => 0);
            return $this->create($add_data);
        } else {
            return true;
        }
    }

    public function unique($tsg_code, $unit_name, $dz_unit_id = 0)
    {
        $dz_unit_id = $this->field("dz_unit_id")->where(array(
            "unit_name" => $unit_name,
            "tsg_code" => $tsg_code,
            "dz_unit_id" => array("neq", $dz_unit_id)
        ))->find();

        if ($dz_unit_id) {
            return false;
        }

        return true;
    }
}
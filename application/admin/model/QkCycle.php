<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/3
 * Time: 9:52
 */

namespace app\admin\model;

/**
 * Class QkCycle
 * @package app\admin\model
 * 期刊周期
 */
class QkCycle extends Base
{
    /**
     * @param $cycle_name
     * @param $tsg_code
     * @param int $qk_cycle_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *判断期刊周期是否唯一
     */
    public static function unique($cycle_name, $tsg_code,$qk_cycle_id = 0)
    {
        $qk_cycle_id = self::field("qk_cycle_id")->where(array(
            "cycle_name"  => $cycle_name,
            'tsg_code' => $tsg_code,
            "qk_cycle_id" => array("neq", $qk_cycle_id)
        ))->find();

        if ($qk_cycle_id) {
            return false;
        }

        return true;
    }

    /**
     * @param $tsg_code
     * @param array $option
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *--期刊周期清单
     */
    public static function get_list($tsg_code,$option=[])
    {
        $order = (isset($option["order"]) ? $option["order"] : "qk_cycle_id");
        $field = (isset($option["field"]) ? $option["field"] : "qk_cycle_id,cycle_name,cycle_cnt");
        return self::field($field)->where(['tsg_code'=>$tsg_code])->order($order)->select();
    }
}
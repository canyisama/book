<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/27
 * Time: 10:51
 */

namespace app\admin\model;


class Qk extends  Base
{
    public static function get_rel_data(&$qk_data, $qk_id)
    {
        $data_list = array();
        $qk_cnt = (isset($qk_data["qk_cnt"]) ? intval($qk_data["qk_cnt"]) : 0);

        if (1000 < $qk_cnt) {
            $qk_cnt = 1000;
        }

        $rel_data = array("qk_id" => $qk_id);

        for ($i = 1; $i <= $qk_cnt; $i++) {
            $rel_data["pos"] = $i;
            $data_list[] = $rel_data;
        }

        return $data_list;
    }

    public function qkRel()
    {
        return $this->hasMany('QkRel', 'qk_id', 'qk_id');
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/18
 * Time: 16:14
 */

namespace app\admin\model;


use think\Db;

class Common
{
    public function addFast(&$data)
    {
        if (empty($data)) {
            return false;
        }

        $insertId = Db::insertFast($this->getTableName(), $data);
        return $insertId;
    }

    static public function array_keySwap(&$array_input, $zd_name, $val_zd_name = "")
    {
        if (empty($zd_name)) {
            return $array_input;
        }

        $re_arr = array();

        foreach ($array_input as $item) {
            if ($item && !is_array($item))
                $item = $item->toArray();
            $key_tmp = (is_array($item) ? $item[$zd_name] : $item);
            $val_tmp = (!empty($val_zd_name) ? $item[$val_zd_name] : $item);
            $re_arr[$key_tmp] = $val_tmp;
        }

        return $re_arr;
    }
}
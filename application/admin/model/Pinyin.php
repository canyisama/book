<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 9:36
 */

namespace app\admin\model;


class Pinyin extends Base
{

    public function unique($hz, $pinyin_id = 0)
    {
        $user_id = self::field("pinyin_id")->where(array(
            "hz" => $hz,
            "pinyin_id" => array("neq", $pinyin_id)
        ))->find();

        if ($user_id) {
            return false;
        }

        return true;
    }
}
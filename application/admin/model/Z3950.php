<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/7
 * Time: 17:35
 */

namespace app\admin\model;


class Z3950 extends Base
{
    const SRARCH_TYPE_ALL_EMPTY = 1;
    const SRARCH_TYPE_AWAY = 2;
    const SRARCH_TYPE_BD_EMPTY = 3;
    const SRARCH_TYPE_TL_EMPTY = 4;

    /**
     * @param $tsg_code
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function get_list($tsg_code)
    {
        return self::where(['tsg_code'=>'sys'])->whereOr(['tsg_code'=>$tsg_code])->select();
    }

    public static function get_search_type_list()
    {
        return array(
            self::SRARCH_TYPE_ALL_EMPTY => "本地书库和套录库为空时检索",
            self::SRARCH_TYPE_AWAY => "无条件检索",
            self::SRARCH_TYPE_BD_EMPTY => "本地书库为空时检索",
            self::SRARCH_TYPE_TL_EMPTY => "套录库为空时检索");
    }
}
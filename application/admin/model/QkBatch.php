<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/27
 * Time: 10:25
 */

namespace app\admin\model;

/**
 * Class QkBatch
 * @package app\admin\model
 * 期刊模型类
 */
class QkBatch extends Base
{
    /**
     * @param $qk_batch_code
     * @param $tsg_code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 片段期刊是否唯一
     */
    public static function unique($qk_batch_code, $tsg_code)
    {
        $qk_batch_code = self::field("qk_batch_code")->where(array(
            "qk_batch_code" => $qk_batch_code,
            "tsg_code"      => array("eq", $tsg_code)
        ))->find();

        if ($qk_batch_code) {
            return false;
        }

        return true;
    }

}
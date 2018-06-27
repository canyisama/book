<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 16:33
 */

namespace app\admin\model;

/**
 * Class QkLog
 * @package app\admin\model
 * 期刊日志模型类
 */
class QkLog extends Base
{

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    const OP_TYPE_YD_ADD = 1;
    const OP_TYPE_YD_SAVE = 2;
    const OP_TYPE_YD_DROP = 3;
    const OP_TYPE_YD_TD = 4;
    const OP_TYPE_YD_CD = 5;
    const OP_TYPE_YS_ADD = 6;
    const OP_TYPE_YS_DROP = 8;
    const OP_TYPE_ZD_ADD = 9;
    const OP_TYPE_ZD_SAVE = 10;
    const OP_TYPE_ZD_DROP = 11;
    const OP_TYPE_CYCLE_ADD = 12;
    const OP_TYPE_CYCLE_SAVE = 13;
    const OP_TYPE_CYCLE_DROP = 14;
    const OP_TYPE_YD_BATCH_ADD = 15;
    const OP_TYPE_YD_BATCH_SAVE = 16;
    const OP_TYPE_YD_BATCH_DROP = 17;

    public static function getTypes()
    {
        return array(self::OP_TYPE_YD_BATCH_ADD => "预订批次-增加", self::OP_TYPE_YD_BATCH_SAVE => "预订批次-编辑", self::OP_TYPE_YD_BATCH_DROP => "预订批次-删除", self::OP_TYPE_YD_ADD => "期刊预订-增加", self::OP_TYPE_YD_SAVE => "期刊预订-编辑", self::OP_TYPE_YD_DROP => "期刊预订-删除", self::OP_TYPE_YD_TD => "期刊预订-退订", self::OP_TYPE_YD_CD => "期刊预订-重订", self::OP_TYPE_YS_ADD => "期刊验收-增加", self::OP_TYPE_YS_DROP => "期刊验收-删除", self::OP_TYPE_ZD_ADD => "期刊装订-增加", self::OP_TYPE_ZD_SAVE => "期刊装订-编辑", self::OP_TYPE_ZD_DROP => "期刊装订-删除", self::OP_TYPE_CYCLE_ADD => "出版周期-增加", self::OP_TYPE_CYCLE_SAVE => "出版周期-编辑", self::OP_TYPE_CYCLE_DROP => "出版周期-删除");
    }

    public static function addLog($op_type, $user_info, $param = array())
    {
        $types = self::getTypes();
        $book_id = (isset($param["book_id"]) ? $param["book_id"] : 0);
        $dck_id = (isset($param["dck_id"]) ? $param["dck_id"] : 0);
        $db1 = (isset($param["db1"]) ? $param["db1"] : "");
        $db2 = (isset($param["db2"]) ? $param["db2"] : "");
        $op_desc_defautl = "操作:$types[$op_type],数据记录ID：$db1";
        $op_desc = (isset($param["op_desc"]) ? str_replace("[#]", $op_desc_defautl, $param["op_desc"]) : $op_desc_defautl);
        $qk_log_data = array("dck_id" => $dck_id, "book_id" => $book_id, "op_time" => time(), "tsg_code" => $user_info["tsg_code"], "ip_addr" => $_SERVER["REMOTE_ADDR"], "op_user" => $user_info["user_name"], "db1" => $db1, "db2" => $db2, "op_desc" => $op_desc, "op_type" => $op_type);
        return self::create($qk_log_data)->result;
    }

}
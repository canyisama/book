<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 9:47
 */

namespace app\admin\model;


class CfLog extends Base
{
    const OP_TYPE_YD_BATCH_ADD = 1;
    const OP_TYPE_YD_BATCH_SAVE = 2;
    const OP_TYPE_YD_BATCH_SET_STATUS = 3;
    const OP_TYPE_YD_BATCH_DROP = 4;
    const OP_TYPE_YD_ADD = 5;
    const OP_TYPE_YD_SAVE = 6;
    const OP_TYPE_YD_TD = 7;
    const OP_TYPE_YD_CD = 8;
    const OP_TYPE_YD_DROP = 9;
    const OP_TYPE_YS_BATCH_ADD = 10;
    const OP_TYPE_YS_BATCH_SAVE = 11;
    const OP_TYPE_YS_BATCH_SET_STATUS = 12;
    const OP_TYPE_YS_BATCH_DROP = 13;
    const OP_TYPE_YS_ADD = 14;
    const OP_TYPE_YS_SAVE = 15;
    const OP_TYPE_YS_DROP = 16;
    const OP_TYPE_BOOKSELL_ADD = 17;
    const OP_TYPE_BOOKSELL_SAVE = 18;
    const OP_TYPE_BOOKSELL_DROP = 19;
    const OP_TYPE_COST_ADD = 20;
    const OP_TYPE_COST_SAVE = 21;
    const OP_TYPE_COST_DROP = 22;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    public static function get_types()
    {
        return array(
            self::OP_TYPE_YD_BATCH_ADD => "预订批次-增加",
            self::OP_TYPE_YD_BATCH_SAVE => "预订批次-保存",
            self::OP_TYPE_YD_BATCH_SET_STATUS => "预订批次-设置状态",
            self::OP_TYPE_YD_BATCH_DROP => "预订批次-删除",
            self::OP_TYPE_YD_ADD => "书目预订-增加",
            self::OP_TYPE_YD_SAVE => "书目预订-保存",
            self::OP_TYPE_YD_TD => "书目预订-退订",
            self::OP_TYPE_YD_CD => "书目预订-重订",
            self::OP_TYPE_YD_DROP => "书目预订-删除",
            self::OP_TYPE_YS_BATCH_ADD => "验收批次-增加",
            self::OP_TYPE_YS_BATCH_SAVE => "验收批次-保存",
            self::OP_TYPE_YS_BATCH_SET_STATUS => "验收批次-设置状态",
            self::OP_TYPE_YS_BATCH_DROP => "验收批次-删除",
            self::OP_TYPE_YS_ADD => "书目验收-增加",
            self::OP_TYPE_YS_SAVE => "书目验收-保存",
            self::OP_TYPE_YS_DROP => "书目验收-删除",
            self::OP_TYPE_BOOKSELL_ADD => "书商信息-增加",
            self::OP_TYPE_BOOKSELL_SAVE => "书商信息-保存",
            self::OP_TYPE_BOOKSELL_DROP => "书商信息-删除",
            self::OP_TYPE_COST_ADD => "预算-增加",
            self::OP_TYPE_COST_SAVE => "预算-修改",
            self::OP_TYPE_COST_DROP => "预算-删除"
        );
    }

    public static function addLog($op_type, $user_info, $param = array())
    {
        $types = self::get_types();
        $book_id = (isset($param["book_id"]) ? $param["book_id"] : 0);
        $dck_id = (isset($param["dck_id"]) ? $param["dck_id"] : 0);
        $db1 = (isset($param["db1"]) ? $param["db1"] : "");
        $db2 = (isset($param["db2"]) ? $param["db2"] : "");
        $op_desc_defautl = "操作:$types[$op_type],数据记录ID：$db1";
        $op_desc = (isset($param["op_desc"]) ? str_replace("[#]", $op_desc_defautl, $param["op_desc"]) : $op_desc_defautl);
        $qk_log_data = array(
            "dck_id" => $dck_id,
            "book_id" => $book_id,
            "op_time" => time(),
            "tsg_code" => $user_info["tsg_code"],
            "ip_addr" => $_SERVER["REMOTE_ADDR"],
            "op_user" => $user_info["user_name"],
            "db1" => $db1,
            "db2" => $db2,
            "op_desc" => $op_desc,
            "op_type" => $op_type
        );
        return self::create($qk_log_data)->result;
    }
}
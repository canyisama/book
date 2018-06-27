<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 16:29
 */

namespace app\admin\model;


class BmLog extends Base
{
    const OP_TYPE_BOOK_ADD = 1;
    const OP_TYPE_BOOK_SAVE = 2;
    const OP_TYPE_BOOK_VERIFY = 3;
    const OP_TYPE_BOOK_NOVERIFY = 4;
    const OP_TYPE_BOOK_DROP = 5;
    const OP_TYPE_DC_ADD = 6;
    const OP_TYPE_DC_SAVE = 7;
    const OP_TYPE_DC_DROP = 8;
    const OP_TYPE_BASE_PARAM_ADD = 9;
    const OP_TYPE_BASE_PARAM_SAVE = 10;
    const OP_TYPE_BASE_PARAM_DROP = 11;
    const OP_TYPE_TAOLU_IMPORT = 12;
    const OP_TYPE_TAOLU_WH_DEL = 13;
    const OP_TYPE_TAOLU_WH_BAT = 14;
    const OP_TYPE_TAOLU_WH_INIT = 15;
    const OP_TYPE_MARC_BAT = 16;
    const OP_TYPE_BOOK_ACCEPT = 17;
    const OP_TYPE_BOOK_DROP_BAT = 18;
    const OP_TYPE_BOOK_DROP_BAT_ALL = 19;
    const OP_TYPE_ZCH_WH_ADD = 20;
    const OP_TYPE_ZCH_WH_SAVE = 21;
    const OP_TYPE_ZCH_WH_DROP = 22;
    const OP_TYPE_ZCH_WH_IMPORT = 23;
    const OP_TYPE_ZCH_WH_RE = 24;
    const OP_TYPE_BOOK_OUT = 25;

    public static function get_types()
    {
        return array(self::OP_TYPE_BOOK_ADD => "书目-增加", self::OP_TYPE_BOOK_SAVE => "书目-修改", self::OP_TYPE_BOOK_VERIFY => "书目-审核", self::OP_TYPE_BOOK_NOVERIFY => "书目-重审", self::OP_TYPE_BOOK_DROP => "书目-删除", self::OP_TYPE_DC_ADD => "馆藏-增加", self::OP_TYPE_DC_SAVE => "馆藏-修改", self::OP_TYPE_DC_DROP => "馆藏-删除", self::OP_TYPE_BASE_PARAM_ADD => "通用参数设置-增加", self::OP_TYPE_BASE_PARAM_SAVE => "通用参数设置-修改", self::OP_TYPE_BASE_PARAM_DROP => "通用参数设置-删除", self::OP_TYPE_TAOLU_IMPORT => "套录数据导入", self::OP_TYPE_TAOLU_WH_DEL => "套录数据处理-删除", self::OP_TYPE_TAOLU_WH_BAT => "套录数据处理-批量删除", self::OP_TYPE_TAOLU_WH_INIT => "套录数据处理-初始化", self::OP_TYPE_MARC_BAT => "MARC批处理", self::OP_TYPE_BOOK_ACCEPT => "馆藏书目接收", self::OP_TYPE_BOOK_OUT => "馆藏书目输出", self::OP_TYPE_BOOK_DROP_BAT => "未利用书目批量删除", self::OP_TYPE_BOOK_DROP_BAT_ALL => "未利用书目剔除全部", self::OP_TYPE_ZCH_WH_ADD => "种次号维护-新增", self::OP_TYPE_ZCH_WH_SAVE => "种次号维护-修改", self::OP_TYPE_ZCH_WH_DROP => "种次号维护-删除", self::OP_TYPE_ZCH_WH_IMPORT => "种次号维护-导入", self::OP_TYPE_ZCH_WH_RE => "种次号维护-重建");
    }

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    public static function addLog($op_type, $user_info, $param = array())
    {
        $types = self::get_types();
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
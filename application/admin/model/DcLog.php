<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/9
 * Time: 17:22
 */

namespace app\admin\model;


class DcLog extends Base
{
    const OP_TYPE_REG = 1;
    const OP_TYPE_DROP = 2;
    const OP_TYPE_CHECK = 3;
    const OP_TYPE_HANDLE = 4;
    const OP_TYPE_BARCODE_TAB = 5;
    const OP_TYPE_BATCH_PROC = 6;
    const OP_TYPE_BATCH_DISPATCH = 7;
    const OP_TYPE_BATCH_REG = 8;
    const OP_TYPE_BATCH_DISPATCH2 = 9;


    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];


    public static function get_types()
    {
        return array(self::OP_TYPE_REG => "入藏登记", self::OP_TYPE_BATCH_REG => "批量入藏", self::OP_TYPE_DROP => "馆藏剔除", self::OP_TYPE_CHECK => "藏书清点", self::OP_TYPE_HANDLE => "清点处理", self::OP_TYPE_BARCODE_TAB => "条码更换",self::OP_TYPE_BATCH_DISPATCH =>'馆藏调拨' ,self::OP_TYPE_BATCH_PROC => "馆藏批修改", self::OP_TYPE_BATCH_DISPATCH2 => "批量调拨");
    }

    public static function addlog($op_type, $user_info, $param = array())
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
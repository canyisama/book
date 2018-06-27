<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 10:30
 */

namespace app\admin\model;


class SysLog extends Base
{

    const OP_TYPE_USER_ADD = 1;
    const OP_TYPE_USER_SAVE = 2;
    const OP_TYPE_USER_DROP = 3;
    const OP_TYPE_ROLE_ADD = 4;
    const OP_TYPE_ROLE_SAVE = 5;
    const OP_TYPE_ROLE_DROP = 6;
    const OP_TYPE_TSG_ADD = 7;
    const OP_TYPE_TSG_SAVE = 8;
    const OP_TYPE_TSG_DROP = 9;
    const OP_TYPE_TSG_SITE_ADD = 10;
    const OP_TYPE_TSG_SITE_SAVE = 11;
    const OP_TYPE_TSG_SITE_DROP = 12;
    const OP_TYPE_Z39_ADD = 13;
    const OP_TYPE_Z39_SAVE = 14;
    const OP_TYPE_Z39_DROP = 15;
    const OP_TYPE_PINYIN_ADD = 16;
    const OP_TYPE_PINYIN_SAVE = 17;
    const OP_TYPE_PINYIN_DROP = 18;
    const OP_TYPE_PUB_ADD = 19;
    const OP_TYPE_PUB_SAVE = 20;
    const OP_TYPE_PUB_DROP = 21;
    const OP_TYPE_DOCTYPE_ADD = 22;
    const OP_TYPE_DOCTYPE_SAVE = 23;
    const OP_TYPE_DOCTYPE_DROP = 24;
    const OP_TYPE_MT_ADD = 25;
    const OP_TYPE_MT_SAVE = 26;
    const OP_TYPE_MT_DROP = 27;
    const OP_TYPE_MT_ZD_ADD = 28;
    const OP_TYPE_MT_ZD_SAVE = 29;
    const OP_TYPE_MT_ZD_DROP = 30;
    const OP_TYPE_MT_INDEX_SAVE = 31;
    const OP_TYPE_MT_MAP_SAVE = 32;
    const OP_TYPE_MT_TPL_ADD = 33;
    const OP_TYPE_MT_TPL_SAVE = 34;
    const OP_TYPE_MT_TPL_DROP = 35;
    const OP_TYPE_MT_JS_ADD = 36;
    const OP_TYPE_MT_JS_SAVE = 37;
    const OP_TYPE_MT_JS_DROP = 38;
    const OP_TYPE_LOGIN_IN = 42;
    const OP_TYPE_LOGIN_OUT = 43;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    public static function get_types()
    {
        return array(self::OP_TYPE_LOGIN_IN => "用户登录", self::OP_TYPE_LOGIN_OUT => "用户登出", self::OP_TYPE_USER_ADD => "操作员-增加", self::OP_TYPE_USER_SAVE => "操作员-修改", self::OP_TYPE_USER_DROP => "操作员-删除", self::OP_TYPE_ROLE_ADD => "角色-增加", self::OP_TYPE_ROLE_SAVE => "角色-修改", self::OP_TYPE_ROLE_DROP => "角色-删除", self::OP_TYPE_TSG_ADD => "分馆-增加", self::OP_TYPE_TSG_SAVE => "分馆-修改", self::OP_TYPE_TSG_DROP => "分馆-删除", self::OP_TYPE_TSG_SITE_ADD => "馆藏地址设置-增加", self::OP_TYPE_TSG_SITE_SAVE => "馆藏地址设置-修改", self::OP_TYPE_TSG_SITE_DROP => "馆藏地址设置-删除", self::OP_TYPE_Z39_ADD => "Z39地址-增加", self::OP_TYPE_Z39_SAVE => "Z39地址-修改", self::OP_TYPE_Z39_DROP => "Z39地址-删除", self::OP_TYPE_PINYIN_ADD => "拼音库-增加", self::OP_TYPE_PINYIN_SAVE => "拼音库-修改", self::OP_TYPE_PINYIN_DROP => "拼音库-删除", self::OP_TYPE_PUB_ADD => "出版库-增加", self::OP_TYPE_PUB_SAVE => "出版库-修改", self::OP_TYPE_PUB_DROP => "出版库-删除", self::OP_TYPE_DOCTYPE_ADD => "图书类型-增加", self::OP_TYPE_DOCTYPE_SAVE => "图书类型-修改", self::OP_TYPE_DOCTYPE_DROP => "图书类型-删除", self::OP_TYPE_MT_ADD => "MARC类型-增加", self::OP_TYPE_MT_SAVE => "MARC类型-修改", self::OP_TYPE_MT_DROP => "MARC类型-删除", self::OP_TYPE_MT_ZD_ADD => "MARC字段-增加", self::OP_TYPE_MT_ZD_SAVE => "MARC字段-修改", self::OP_TYPE_MT_ZD_DROP => "MARC字段-删除", self::OP_TYPE_MT_INDEX_SAVE => "MARC索引配置-修改", self::OP_TYPE_MT_MAP_SAVE => "MARC映射条目-修改", self::OP_TYPE_MT_TPL_ADD => "MARC模板-增加", self::OP_TYPE_MT_TPL_SAVE => "MARC模板-修改", self::OP_TYPE_MT_TPL_DROP => "MARC模板-删除", self::OP_TYPE_MT_JS_ADD => "MARC脚本-增加", self::OP_TYPE_MT_JS_SAVE => "MARC脚本-修改", self::OP_TYPE_MT_JS_DROP => "MARC脚本-删除");
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
        $qk_log_data = array("dck_id" => $dck_id, "book_id" => $book_id, "op_time" => time(), "tsg_code" => $user_info["tsg_code"], "ip_addr" => $_SERVER["REMOTE_ADDR"], "op_user" => $user_info["user_name"], "db1" => $db1, "db2" => $db2, "op_desc" => $op_desc, "op_type" => $op_type);
        return self::create($qk_log_data)->result;
    }


}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-18
 * Time: 11:39
 */

namespace app\admin\model;


class DzLog extends Base
{
    const OP_TYPE_DZ_ADD = 1;
    const OP_TYPE_DZ_RECARD = 2;
    const OP_TYPE_DZ_EDIT = 3;
    const OP_TYPE_DZ_DEL = 4;
    const OP_TYPE_DZ_CUNIT = 5;
    const OP_TYPE_DZ_CSTATUS = 6;
    const OP_TYPE_DZ_CPWD = 7;
    const OP_TYPE_DZ_CENDDATE = 8;
    const OP_TYPE_DZ_CDZTYPE = 9;
    const OP_TYPE_DZ_SWAP = 10;
    const OP_TYPE_DZ_UNIT_ADD = 11;
    const OP_TYPE_DZ_UNIT_EDIT = 12;
    const OP_TYPE_DZ_UNIT_DROP = 13;
    const OP_TYPE_DZ_IMPORT = 14;
    const OP_TYPE_DZ_TYPE_ADD = 15;
    const OP_TYPE_DZ_TYPE_EDIT =16;
    const OP_TYPE_DZ_TYPE_DROP = 17;
    const OP_TYPE_DZUNIT_SWAP = 18;
    const OP_TYPE_DZ_BATCH_DROP =19;
    const OP_TYPE_DZ_SHOUFEI = 20;
    const OP_TYPE_DZ_TUIFEI = 21;



    public function get_types()
    {
        return array(
            self::OP_TYPE_DZ_SHOUFEI => "财务-收费",
            self::OP_TYPE_DZ_TUIFEI => "财务-退费",
            self::OP_TYPE_DZ_ADD => "读者办证",
            self::OP_TYPE_DZ_RECARD => "读者退证",
            self::OP_TYPE_DZ_EDIT => "编辑读者",
            self::OP_TYPE_DZ_DEL => "删除读者",
            self::OP_TYPE_DZ_BATCH_DROP => "读者批量删除",
            self::OP_TYPE_DZ_CUNIT => "读者修改单位",
            self::OP_TYPE_DZ_CSTATUS => "读者证件处理",
            self::OP_TYPE_DZ_CPWD => "读者修改密码",
            self::OP_TYPE_DZ_CENDDATE => "读者修改有效期",
            self::OP_TYPE_DZ_CDZTYPE => "修改读者类型",
            self::OP_TYPE_DZ_SWAP => "读者换证",
            self::OP_TYPE_DZ_UNIT_ADD => "读者单位-新增",
            self::OP_TYPE_DZ_UNIT_EDIT => "读者单位-编辑",
            self::OP_TYPE_DZ_UNIT_DROP => "读者单位-删除",
            self::OP_TYPE_DZUNIT_SWAP => "批量换班",
            self::OP_TYPE_DZ_IMPORT => "读者导入",
            self::OP_TYPE_DZ_TYPE_ADD => "读者类型-新增",
            self::OP_TYPE_DZ_TYPE_EDIT => "读者类型-编辑",
            self::OP_TYPE_DZ_TYPE_DROP => "读者类型-删除"
        );
    }

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    public static function getType($all = 0)
    {
        $type_lists = config('dz_log_type');
        if ($all === 0){
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

    public static function addLog($op_type, $op_user, $param = array())
    {
        $book_id = (isset($param['book_id'])) ? $param['book_id'] : 0;
        $dck_id  = (isset($param['dck_id'])) ? $param['dck_id'] : 0;
        $db_1    = (isset($param['db_1'])) ? $param['db_1'] : '';
        $db_2    = (isset($param['db_2'])) ? $param['db_2'] : '';
        $op_desc_default = "操作:[". self::getType($op_type)."]";
        $data = [
            'op_type'  => $op_type,
            'tsg_code' => $op_user['tsg_code'],
            'op_user'  => $op_user['user_name'],
            'book_id'  => $book_id,
            'dck_id'   => $dck_id,
            'db1'     => $db_1,
            'db2'     => $db_2,
            'op_time'  => time(),
            'ip_addr'  => request()->ip(),
            'op_desc'  => (isset($param["op_desc"]) ? str_replace("[#]", $op_desc_default, $param["op_desc"]) : $op_desc_default)
        ];
        return self::create($data)->result;
    }
}
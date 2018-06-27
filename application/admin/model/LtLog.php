<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 14:52
 */

namespace app\admin\model;

/**
 * Class LtLog
 * @package app\admin\model
 * 图书流通日志模型类
 */
class LtLog extends Base
{
    /*const OP_TYPE_LEND_BOOK = 1;
	const OP_TYPE_RE_BOOK = 2;
	const OP_TYPE_KEEP_BOOK = 3;
	const OP_TYPE_LEND_NOCARD = 4;
	const OP_TYPE_DZ_ADD = 5;
	const OP_TYPE_DZ_RECARD = 6;
	const OP_TYPE_DZ_EDIT = 7;
	const OP_TYPE_DZ_DEL = 8;
	const OP_TYPE_DZ_CUNIT = 9;
	const OP_TYPE_DZ_CSTATUS = 10;
	const OP_TYPE_DZ_CPWD = 11;
	const OP_TYPE_DZ_CENDDATE = 12;
	const OP_TYPE_DZ_CDZTYPE = 13;
	const OP_TYPE_DZ_SWAP = 14;
	const OP_TYPE_DZ_UNIT_ADD = 15;
	const OP_TYPE_DZ_UNIT_EDIT = 16;
	const OP_TYPE_DZ_UNIT_DROP = 17;
	const OP_TYPE_DZ_IMPORT = 18;
	const OP_TYPE_DZ_TYPE_ADD = 19;
	const OP_TYPE_DZ_TYPE_EDIT = 20;
	const OP_TYPE_DZ_TYPE_DROP = 21;
	const OP_TYPE_LTYPE_ADD = 21;
	const OP_TYPE_LTYPE_EDIT = 22;
	const OP_TYPE_LTYPE_DROP = 23;
	const OP_TYPE_LTRULE_ADD = 24;
	const OP_TYPE_LTRULE_EDIT = 25;
	const OP_TYPE_LTRULE_DROP = 26;
	const OP_TYPE_DZUNIT_SWAP = 27;
	const OP_TYPE_HOLIDAY_ADD = 28;
	const OP_TYPE_HOLIDAY_EDIT = 29;
	const OP_TYPE_HOLIDAY_DROP = 30;
	const OP_TYPE_DZ_BATCH_DROP = 31;
	*/
    const OP_TYPE_DZ_SHOUFEI = 32;
    const OP_TYPE_DZ_TUIFEI = 33;

    const OP_TYPE_LEND_BOOK = 1;
    const OP_TYPE_RE_BOOK = 2;
    const OP_TYPE_KEEP_BOOK = 3;
    const OP_TYPE_LEND_NOCARD = 4;
    const OP_TYPE_LTYPE_ADD = 5;
    const OP_TYPE_LTYPE_EDIT = 6;
    const OP_TYPE_LTYPE_DROP = 7;
    const OP_TYPE_LTRULE_ADD = 8;
    const OP_TYPE_LTRULE_EDIT = 9;
    const OP_TYPE_LTRULE_DROP = 10;
    const OP_TYPE_HOLIDAY_ADD = 11;
    const OP_TYPE_HOLIDAY_EDIT = 12;
    const OP_TYPE_HOLIDAY_DROP = 13;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'op_time'    =>  'timestamp'
    ];

    /**
     * @param int $all   @为0获取所有类型
     * @return mixed|string
     * 数组类型
     */
    public static function getType($all = 0)
    {
        $type_lists = config('lt_log_type');
        if ($all === 0){
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

    /**
     * @param $op_type '日志类型'
     * @param $op_user '操作管理员'
     * @param $param   '写入的信息'
     * @return bool
     * 添加日志 --- 调用公共日志类
     */
    public static function addLog($op_type, $op_user, $param = array())
    {
        $book_id = (isset($param['book_id'])) ? $param['book_id'] : 0;
        $dck_id  = (isset($param['dck_id'])) ? $param['dck_id'] : 0;
        $db1    = (isset($param['db1'])) ? $param['db1'] : '';
        $db2    = (isset($param['db2'])) ? $param['db2'] : '';
        $op_desc_default = "操作:[". self::getType($op_type)."]";
        $data = [
            'op_type'  => $op_type,
            'tsg_code' => $op_user['tsg_code'],
            'op_user'  => $op_user['user_name'],
            'book_id'  => $book_id,
            'dck_id'   => $dck_id,
            'db1'     => $db1,
            'db2'     => $db2,
            'op_time'  => time(),
            'ip_addr'  => request()->ip(),
            'op_desc'  => (isset($param["op_desc"]) ? str_replace("[#]", $op_desc_default, $param["op_desc"]) : $op_desc_default)
        ];
        return self::create($data)->result;
    }

}
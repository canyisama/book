<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/6/5
 * Time: 17:51
 */

namespace app\admin\model;

/**
 * Class ExpectLog
 * @package app\admin\model
 * 读者荐购模型
 */
class ExpectLog extends Base
{
    const EXPECT_STATUS_ADD = 1;
    const EXPECT_STATUS_VERIFY_OK = 2;
    const EXPECT_STATUS_VERIFY_ERR = 3;
    const EXPECT_STATUS_BOOK = 4;

    protected $dateFormat = 'Y-m-d';
    protected $type = [
        'add_time'    =>  'timestamp',
        'verify_time'    =>  'timestamp',
        'book_time'    =>  'timestamp'
    ];

    static private $arr = [
        self::EXPECT_STATUS_ADD => '审核中',
        self::EXPECT_STATUS_VERIFY_OK => '审核成功',
        self::EXPECT_STATUS_VERIFY_ERR => '审核失败',
        self::EXPECT_STATUS_BOOK => '已到书',
    ];

    public static function get_status_list($status = 0)
    {
        if ($status === 0){
            return self::$arr;
        }else if($status === 'verify'){
            return [
                self::EXPECT_STATUS_VERIFY_OK=>self::$arr[self::EXPECT_STATUS_VERIFY_OK],
                self::EXPECT_STATUS_VERIFY_ERR=>self::$arr[self::EXPECT_STATUS_VERIFY_ERR]];
        }
        return self::$arr[$status] ?: '';
    }

    public function getStatusOpAttr($value,$data)
    {
        if (isset($data['status'])){
            $type = self::get_status_list();
            switch ($data['status']){
                case 1:
                    $return = '<span class="label label-primary">'.$type[$data['status']].'</span>';
                    break;
                case 2:
                    $return = '<span class="label label-warning">'.$type[$data['status']].'</span>';
                    break;
                case 3:
                    $return = '<span class="label label-danger">'.$type[$data['status']].'</span>';
                    break;
                case 4:
                    $return = '<span class="label label-success">'.$type[$data['status']].'</span>';
                    break;
                default :
                    $return = '';
                    break;
            }
            return $return;
        }
        return '';
    }

    protected function getVerifyTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    protected function getBookTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }
}
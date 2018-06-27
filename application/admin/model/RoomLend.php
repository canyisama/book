<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/21
 * Time: 15:15
 */

namespace app\admin\model;


/**
 * Class RoomLend
 * @package app\admin\model
 * 借阅室模型类
 */
class RoomLend extends Base
{
    const LT_STATUS_ON = 1;
    const LT_STATUS_FINISH = 2;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'beg_time'    =>  'timestamp',
        'end_time'    =>  'timestamp',
    ];

    private static $type_arr = [
        self::LT_STATUS_ON => "签到",
        self::LT_STATUS_FINISH => "完成"
    ];

    /**
     * @param int $all
     * @return array|mixed|string
     * 获取借阅类型
     */
    public static function getType($all = 0)
    {
        $lists = self::$type_arr;
        if ($all === 0){
            return $lists;
        }
        return isset($lists[$all]) ? $lists[$all] : '无此类型';
    }

    /**
     * @param $value
     * @return mixed|string
     * 阅览状态获取器
     */
    protected function getLtStatusAttr($value)
    {
        $type = [
            self::LT_STATUS_ON => "签到",
            self::LT_STATUS_FINISH => "完成"
        ];
        if ($value === 1){
            return "<i class='label label-primary'>".$type[$value] . "</i>";
        }else if($value === 2){
            return "<i class='label label-success'>".$type[$value] . "</i>";
        }
        return "-";
//        return isset($type[$value]) ? "<i class='label label-info'>".$type[$value] . "</i>" : '';
    }
}
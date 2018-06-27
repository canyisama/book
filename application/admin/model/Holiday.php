<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/19
 * Time: 11:45
 */

namespace app\admin\model;


class Holiday extends Base
{
    protected $dateFormat = 'Y-m-d';
    protected $type = [
        'date_beg' => 'timestamp',
        'date_end' => 'timestamp'
    ];
    /**
     * @param $tsg_code     @分馆代码
     * @param $sour_date    @原还书时间
     * @return int          @成功返回假期id
     * @throws \think\exception\DbException
     */
    public static function disDate($tsg_code, &$sour_date)
    {

//        $tsg_code = $user_info["tsg_code"];
        $sour_date = intval($sour_date);
        $where = array(
            "tsg_code" => $tsg_code,
            "date_beg" => array("<=", $sour_date),
            "date_end" => array(">=", $sour_date)
        );
        $holi_info = self::get($where);



        if (!$holi_info) {
            return 0;
        }

        //假期结束时间
        $date_end = $holi_info->getData('date_end') + 1;
        //预约到期剩余时间
        $holi_val = $sour_date - $holi_info->getData('date_beg');
        //新预约到期时间
        $sour_date = $date_end + $holi_val;

        return $holi_info["holiday_id"];
    }
}
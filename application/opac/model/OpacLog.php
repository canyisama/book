<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/16
 * Time: 13:47
 */

namespace app\opac\model;

use app\admin\model\Base;

class OpacLog extends Base
{
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'log_time'    =>  'timestamp'
    ];

    public static function addlog($dz_id, $dz_code)
    {
        $dz_id = ($dz_id ? $dz_id : 0);
        $dz_code = ($dz_code ? $dz_code : "");
        $log_data = array("dz_id" => $dz_id, "dz_code" => $dz_code, "log_time" => time(), "ip_addr" => $_SERVER["REMOTE_ADDR"]);
        return self::create($log_data)->result;
    }

    public function dzgl()
    {
        return $this->belongsTo('app\admin\model\Dzgl','dz_id','dz_id');
    }
}
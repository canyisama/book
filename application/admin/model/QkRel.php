<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/2
 * Time: 17:38
 */

namespace app\admin\model;

/**
 * Class QkRel
 * @package app\admin\model
 * 期刊验收模型类
 */
class QkRel extends Base
{
    protected $dateFormat = 'Y-m-d';
    protected $type = [
      'ys_time' => 'timestamp'
    ];
    public function qk()
    {
        return $this->belongsTo('Qk', 'qk_id', 'qk_id');
    }

}
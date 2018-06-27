<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/23
 * Time: 13:59
 */

namespace app\admin\validate;


use think\Validate;

class Holiday extends Validate
{
    protected $rule = [
        'ho_name' => 'require',
        'date_beg' => 'require',
        'date_end' => 'require'
    ];

    protected $message = [
        'ho_name.required' => '假期名称不能为空不能为空',
        'date_beg.required' => '假期开始时间不能为空',
        'date_end.required' => '假期结束不能为空'
    ];

}
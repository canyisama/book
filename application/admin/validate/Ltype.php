<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/23
 * Time: 13:59
 */

namespace app\admin\validate;


use think\Validate;

class Ltype extends Validate
{
    protected $rule = [
        'ltype_code' => 'require|alphaNum',
        'ltype_name' => 'require'
    ];

    protected $message = [
        'ltype_code.required' => '流通类型代码不能为空',
        'ltype_code.alphaNum' => '流通类型代码只能为数字和字母',
        'ltype_name.required' => '图书类型名称不能为空'
    ];

}
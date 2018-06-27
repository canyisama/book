<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/3/20
 * Time: 17:24
 */

namespace app\opac\validate;

use think\Validate;

class Login extends Validate
{
    protected $rule = [
        'dz_code' => 'require|max:10',
        'pwd' => 'require|max:10',
    ];

    protected $message = [
        'dz_code.require' => '用户账号不能为空',
        'dz_code.max' => '用户账号最多十个字符',
        'pwd.require' => '密码不能为空',
        'pwd.max' => '密码最多十个字符',
    ];
}
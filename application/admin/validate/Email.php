<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/10
 * Time: 14:46
 */

namespace app\admin\validate;


use think\Validate;

class Email extends Validate
{
    protected $rule = [
        'smtp_host' => 'require|checkSpace',
        'smtp_port' => 'require|checkSpace',
        'smtp_user' => 'require|checkSpace',
        'from_name' => 'require|checkSpace',
        'from_email' => 'require|email',
        'to_email' => 'require|email',
        'to_name' => 'require|checkSpace',
        'subject' => 'require|checkSpace',
        'email_body' => 'require|checkSpace',
        'tpl_name'  => 'require|checkSpace',
        'tpl_subject'  => 'require|checkSpace',
        'tpl_body'  => 'require|checkSpace'
    ];

    protected $message = [
        'smtp_host.require' => 'smtp服务器不能为空',
        'smtp_port.require' => 'smtp服务器端口不能为空',
        'smtp_user.require' => 'smtp服务器用户名不能为空',
        'from_name.require' => '发件人姓名不能为空',
        'from_email.require' => '发件人email不能为空',
        'from_email.email' => '发件人email不是一个有效的email',
        'to_email.require' => '收信人email不能为空',
        'to_email.email' => '收信人email不是一个有效的email',
        'to_name.require' => '收信人姓名不能为空',
        'subject.require' => '邮件标题不能为空',
        'email_body.require' => '邮件内容不能为空',
        'tpl_name.require' => '模板名称为空',
        'tpl_subject.require' => '模板标题不能为空',
        'tpl_body.require' => '模板内容不能为空',
        'tpl_body.checkSpace' => '模板内容不能为空格',
        'smtp_host.checkSpace' => 'smtp服务器不能为空格',
        'smtp_port.checkSpace' => 'smtp服务器端口不能为空格',
        'smtp_user.checkSpace' => 'smtp服务器用户名不能为空格',
        'from_name.checkSpace' => '发件人姓名不能为空格',
        'to_name.checkSpace' => '收信人姓名不能为空格',
        'subject.checkSpace' => '邮件标题不能为空格',
        'email_body.checkSpace' => '邮件内容不能为空格',
        'tpl_name.checkSpace' => '模板名称不能为空格',
        'tpl_subject.checkSpace' => '模板标题不能为空格'
    ];

    protected $scene = [
        'add'  =>  ['to_email','to_name','subject','email_body'],
        'config' => ['smtp_host','smtp_port','smtp_user','from_name','from_email'],
        'tpl'  =>['tpl_name','tpl_subject','tpl_body']
    ];

    protected function checkSpace($value,$rule)
    {
        $value = trim($value);
        return !empty($value) || $value === 0 || $value === '0' ? true : false;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/14
 * Time: 10:46
 */

namespace app\admin\model;

use PHPMailer\PHPMailer\PHPMailer;

class Email extends Base
{
    const EMAIL_STATUS_SEND = 1;
    const EMAIL_STATUS_ERR = 2;
    const EMAIL_STATUS_OK = 3;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'add_time'    =>  'timestamp',
        'err_time'    =>  'timestamp',
        'send_time'    =>  'timestamp'
    ];

    /**
     * @return array
     * 邮件状态数组
     */
    public static function get_status_list()
    {
        return array(self::EMAIL_STATUS_SEND => "发送中", self::EMAIL_STATUS_ERR => "发送失败", self::EMAIL_STATUS_OK => "发送成功");
    }

    public function getAddTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '-';
    }

    public function getSendTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '-';
    }

    public function getErrTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s',$value): '-';
    }



    /**
     * @param $tsg_code
     * @param $email_info
     * @return bool
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 发送邮件
     */
    public function send($tsg_code, $email_info)
    {
        $config_info = Tsg::field("email_cnf")->where(['tsg_code'=>$tsg_code])->find();
        $config = unserialize($config_info["email_cnf"]);
        if (!$config || empty($config["smtp_host"]) || empty($config["from_email"]))
        {
            $this->error = "Email未配置,请先配置Email!";
            return false;
        }
        $mail = new PHPMailer();
        $mail->CharSet = "UTF-8";
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "ssl";
//        $is_ok = $mail->setLanguage("ch");
        $mail->Host = $config["smtp_host"];
        $mail->Port = $config["smtp_port"];
        $mail->Username = $config["smtp_user"];
        $mail->Password = $config["smtp_pass"];


        $mail->SetFrom($config["from_email"], $config["from_name"]);
        $replyEmail = ($config["reply_email"] ? $config["reply_email"] : $config["from_email"]);
        $replyName = ($config["reply_name"] ? $config["reply_name"] : $config["from_name"]);
        $mail->AddReplyTo($replyEmail, $replyName);

        $mail->Subject = $email_info["subject"];
        $mail->MsgHTML($email_info["email_body"]);
        $mail->AddAddress($email_info["to_email"], $email_info["to_name"]);


        // 添加附件
        if (isset($email_info["attachment"]) && is_array($email_info["attachment"])) {
            foreach ($email_info["attachment"] as $file ) {
                is_file($file) && $mail->AddAttachment($file);
            }
        }

        $is_success = $mail->send();
        $this->error = $mail->ErrorInfo;
        return $is_success;
    }

    /**
     * @param $tsg_code
     * @param $email_data
     * @param $op_user
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取post表单数据
     */
    public function genData($tsg_code, $email_data, $op_user)
    {

        $config_info = Tsg::field("email_cnf")->where(['tsg_code'=>$tsg_code])->find();

        $config_info = unserialize($config_info["email_cnf"]);
        if (!$config_info || empty($config_info["smtp_host"]) || empty($config_info["from_email"])) {
            $this->error = "Email未配置,请先配置Email!";
            return false;
        }

        $add_data = array(
            "tsg_code" => $tsg_code,
            "from_email" => $config_info["from_email"],
            "from_name" => $config_info["from_name"],
            "to_email" => isset($email_data["to_email"]) ? trim($email_data["to_email"]) : "",
            "to_name" => isset($email_data["to_name"]) ? trim($email_data["to_name"]) : "",
            "op_user" => $op_user,
            "add_time" => time(),
            "send_status" => self::EMAIL_STATUS_SEND,
            "subject" => isset($email_data["subject"]) ? trim($email_data["subject"]) : "",
            "email_body" => isset($email_data["email_body"]) ? trim($email_data["email_body"]) : "");


        return $add_data;
    }


    /**
     * @param $email_id
     * @return static
     * 设置邮件成功发送
     */
    public static function setOk($email_id)
    {
        return self::update(['send_status'=>self::EMAIL_STATUS_OK,'send_time'=>time()],['email_id'=>$email_id]);
    }


    /**
     * @param $email_id
     * @return static
     * 设置邮件发送失败
     */
    public static function setErr($email_id)
    {
        return self::update(['send_status'=>self::EMAIL_STATUS_ERR,'send_time'=>time()],['email_id'=>$email_id]);
    }

    /**
     * @param $email_id
     * @param $msg
     * @return static
     * 写入邮件日志
     */
    public static function addLog($email_id,$msg)
    {

        $send_log[] = $msg . "     " . date("Y-m-d H:i:s");
        $send_log = serialize($send_log);
        return self::update(['send_log'=>$send_log],['email_id'=>$email_id]);
    }
}
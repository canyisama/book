<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/14
 * Time: 10:49
 */

namespace app\admin\model;


class Sms extends Base
{
    const SMS_STATUS_SEND = 1;
    const SMS_STATUS_ERR = 2;
    const SMS_STATUS_OK = 3;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'add_time'    =>  'timestamp',
        'err_time'    =>  'timestamp',
        'send_time'    =>  'timestamp'
    ];

    public static function get_status_list()
    {
        return array(self::SMS_STATUS_SEND => "发送中", self::SMS_STATUS_ERR => "发送失败", self::SMS_STATUS_OK => "发送成功");
    }

//    public function getAddTimeAttr($value)
//    {
//        return $value ?  date('Y-m-d H:i:s',$value) : '-';
//    }

    public function getSendTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '-';
    }

    public function getErrTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '-';
    }

    public function send($tsg_code,$data,$ext_data)
    {
        $config_info = Tsg::field("sms_cnf,tsg_name")->where("tsg_code='$tsg_code'")->find();
        $config_info = unserialize($config_info["sms_cnf"]);
        if (!$config_info || empty($config_info["sms_user"])) {
            $this->error = "短信未配置,请先配置短信!";
            return false;
        }

        if (!$data || empty($data["phone_mob"])) {
            $this->error = "短信内容或手机号码不能为空!";
            return false;
        }

        import('smsapi', EXTEND_PATH, '.class.php');
        $api = new \SmsApi($config_info['sms_user'],$config_info['sms_pwd']);

        $result = $api->send($data["phone_mob"],$ext_data,$data['templateid']);

        $is_success = ($result['stat'] == "100" ? true : false);
        $this->error = $result['message'];
        return $is_success;
    }

    public function getLave($tsg_code)
    {
        $mod_tsg = d("Tsg");
        $config_info = $mod_tsg->field("sms_cnf")->where(['tsg_code'=>$tsg_code])->find();
        $config_info = unserialize($config_info["sms_cnf"]);
        if (!$config_info || empty($config_info["sms_user"])) {
            $this->error = "短信未配置,请先配置短信!";
            return false;
        }

        import('smsapi', EXTEND_PATH, '.class.php');
        $api = new \SmsApi($config_info['sms_user'],$config_info['sms_pwd']);

        $result = $api->getNumber();
        if ($result['stat'] == '100'){
            return $result['number'];
        }
        $this->error = $result['message'];
        return false;
    }

//    public function mapMsg($key)
//    {
//        $msg_arr = array("100" => "发送成功", "101" => "验证失败", "102" => "短信不足", "103" => "操作失败", "104" => "非法字符", "105" => "内容过多", "106" => "号码过多", "107" => "频率过快", "108" => "号码内容空", "110" => "禁止频繁单条发送", "112" => "号码错误", "113" => "定时时间格式不对", "114" => "账号被锁", "116" => "禁止接口发送", "117" => "绑定IP不正确", "119" => "缺少短信签名", "120" => "系统升级");
//        $msg = (isset($msg_arr[$key]) ? $msg_arr[$key] : $key);
//        return $msg;
//    }

    public function genData($tsg_code, $sms_data, $op_user)
    {
        $mod_tsg = d("Tsg");
        $config_info = $mod_tsg->field("sms_cnf")->where("tsg_code='$tsg_code'")->find();
        $config_info = unserialize($config_info["sms_cnf"]);
        if (!$config_info || empty($config_info["sms_user"])) {
            $this->error = "Sms未配置,请先配置Sms!";
            return false;
        }

        $add_data = array("tsg_code" => $tsg_code, "to_mob" => isset($sms_data["phone_mob"]) ? trim($sms_data["phone_mob"]) : "", "op_user" => $op_user, "add_time" => time(), "send_status" => self::SMS_STATUS_SEND, "sms_body" => isset($sms_data["sms_body"]) ? trim($sms_data["sms_body"]) : "");
        return $add_data;
    }

    public static function setOk($sms_id)
    {
        return self::update(array("send_status" => self::SMS_STATUS_OK, "send_time" => time()), ['sms_id' => $sms_id])->result;
    }

    public static function setErr($sms_id)
    {
        return self::update(array("send_status" => self::SMS_STATUS_ERR, "err_time" => time()), ['sms_id' => $sms_id])->result;
    }

    public static function addLog($sms_id, $msg)
    {
//        $sms_info = self::field("send_log")->where("sms_id=$sms_id")->find();
//        $send_log = unserialize($sms_info["send_log"]);
        $send_log[] = $msg . "     " . date("Y-m-d H:i:s");
        $send_log = serialize($send_log);
        return self::update(array("send_log" => $send_log), ['sms_id' => $sms_id])->result;
    }

}
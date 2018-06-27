<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/22
 * Time: 17:23
 */

namespace app\admin\model;


/**
 * Class SmsTpl
 * @package app\admin\model
 * 短信模板类
 */
class SmsTpl extends Base
{
    const TPL_TYPE_RESER = 1;
    const TPL_TYPE_LEND_OUT = 2;
    const TPL_VERIFY_ADD = 1;
    const TPL_VERIFY_OK = 2;
    const TPL_VERIFY_ERR = 3;

    protected $insert = ['verify' => 1];

    public static function get_tpl_type_list()
    {
        return array(self::TPL_TYPE_RESER => "图书预约", self::TPL_TYPE_LEND_OUT => "借阅超期");
    }

    public static function get_verify_list()
    {
        return [self::TPL_VERIFY_ADD=>'审核中', self::TPL_VERIFY_OK => '审核成功',self::TPL_VERIFY_ERR=>'审核失败'];
    }

    public function getTplTypeAttr($value)
    {
        $type = self::get_tpl_type_list();
        return $type[$value] ?: '-';
    }

    public static function get_lable_list($tpl_type = 0)
    {
        $lable_list = array();

        if ($tpl_type == self::TPL_TYPE_RESER) {
            $lable_list['{$barcode}'] = "到书条码";
            $lable_list['{$title}'] = "书目题名";
            $lable_list['{$mustdate}'] = "取书截止日期";
            $lable_list['{$dzname}'] = "读者姓名";
            $lable_list['{$unitname}'] = "单位名称";
        }
        else if ($tpl_type == self::TPL_TYPE_LEND_OUT) {
            $lable_list['{$barcode}'] = "图书条码";
            $lable_list['{$title}'] = "书目题名";
            $lable_list['{$mustdate}'] = "超期日期";
            $lable_list['{$dzname}'] = "读者姓名";
            $lable_list['{$unitname}'] = "单位名称";
        }

        return $lable_list;
    }

    /**
     * @param $tsg_code
     * @param $tpl_type
     * @param int $sms_tpl_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function unique($tsg_code, $tpl_type, $sms_tpl_id = 0)
    {
        $sms_tpl_id = self::field("sms_tpl_id")->where(array(
            "tsg_code"   => $tsg_code,
            "tpl_type"   => $tpl_type,
            "sms_tpl_id" => array("neq", $sms_tpl_id)
        ))->find();

        if ($sms_tpl_id) {
            return false;
        }
        return true;
    }

    /**
     * 模板 增--删--改
     *
     * @param $param        @api参数
     * @param $tsg_code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *
     */
    public function tpl($param,$tsg_code)
    {
        $config_info = Tsg::field('sms_cnf')->where(['tsg_code'=>$tsg_code])->find();
        $config_info = unserialize($config_info["sms_cnf"]);
        if (!$config_info || empty($config_info["sms_user"])) {
            $this->error = '请先配置短信参数并保存';
            return false;
        }
        import('smsapi',EXTEND_PATH,'.class.php');
        $api = new \SmsApi($config_info['sms_user'],$config_info['sms_pwd']);

        $result = $api->tpl($param);
        if ($result['stat'] == '100'){
            return $result['templateid'];
        }
        $this->error = $result['message'];
        return false;
    }

    public function verify($param,$tsg_code)
    {
        $config_info = Tsg::field('sms_cnf')->where(['tsg_code'=>$tsg_code])->find();
        $config_info = unserialize($config_info["sms_cnf"]);
        if (!$config_info || empty($config_info["sms_user"])) {
            $this->error = '请先配置短信参数并保存';
            return false;
        }
        import('smsapi',EXTEND_PATH,'.class.php');
        $api = new \SmsApi($config_info['sms_user'],$config_info['sms_pwd']);
        $result = $api->verify($param);
        $return = true;

        if ($result['stat'] == 167){
            return $return;
        }else if ($result['stat'] == 100){
            $save_data['verify'] = self::TPL_VERIFY_OK;
        }else if ($result){
            $save_data['verify'] = self::TPL_VERIFY_ERR;
            $this->error = $result['question'];
            $return = false;
        }
        $is_success = self::update($save_data,['templateid'=>$param])->result;

        if ($is_success === false){
            $this->error .= '  '.self::getError();
            return false;
        }
        return $return;
    }

    /**
     * @param $tsg_code
     * @param array $option
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取短信模板列表
     */
    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "sms_tpl_id desc");
        $field = (isset($option["field"]) ? $option["field"] : "sms_tpl_id,tpl_name,tpl_type");
        return self::field($field)->where($where)->order($order)->select();
    }

    /**
     * @param $tpl_info
     * @param array $parse_data
     * @param int $tpl_type
     * 解析模板
     */
    public static function parseTpl(&$tpl_info, $parse_data = array(), $tpl_type = 0)
    {
        $label_list = self::get_lable_list($tpl_type);
        $label_list = array_keys($label_list);
        $replace_list = array();
        import('String', EXTEND_PATH, '.class.php');
        foreach ($label_list as $item ) {
            $tmp_zd = str_replace('{$', "", $item);
            $tmp_zd = str_replace("}", "", $tmp_zd);
            $val_tmp = (isset($parse_data[$tmp_zd]) ? $parse_data[$tmp_zd] : "");
            if (!empty($val_tmp) && ($tmp_zd == "title")) {
                $val_tmp = \String::msubstr($val_tmp, 0, 10);
            }
            $replace_list[] = $val_tmp;
        }
        $tpl_info["tpl_body"] = str_replace($label_list, $replace_list, $tpl_info["tpl_body"]);
    }
}
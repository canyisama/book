<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-18
 * Time: 10:55
 */

namespace app\admin\model;


use think\Loader;

class Dzgl extends Base
{
    const UC_KEY = "c6f8nzssI4WnboDaiIzi5zcPJ+QJG+wbiKnf25g";
    protected $dateFormat = 'Y-m-d';
    protected $type = [
        'dz_add_time' => 'timestamp',
        'beg_time' => 'timestamp',
        'end_time' => 'timestamp'
    ];

    public function opacLog()
    {
        return $this->hasMany('app\opac\model\OpacLog', 'dz_id', 'dz_id');
    }

    public function get_avatar($dz_id, $time_id, $ucapi = "", $type = "virtual", $rehtml = 1)
    {
        $dz_id = intval($dz_id);
        $param_input = $this->uc_api_input("uid=$dz_id&time_id=$time_id");
        $site_url = request()->domain() . "/static/admin/img";
        $ucapi = (!empty($ucapi) ? $ucapi : request()->domain() . "/upload.php");
        $app_id = 1;
        $uc_avatarflash = $site_url . "/camera.swf?inajax=1&appid=" . $app_id .
            "&input=" . $param_input . "&agent=" . md5(request()->header('user-agent')) . "&ucapi=" . urlencode($ucapi) .
            "&avatartype=" . $type . "&uploadSize=2048";

        if ($rehtml) {
            return '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" 
                    codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" 
                    width="450" height="253" id="mycamera" align="middle">
                    <param name="allowScriptAccess" value="always" />
                    <param name="scale" value="exactfit" />
                    <param name="wmode" value="transparent" />
                    <param name="quality" value="high" />
                    <param name="bgcolor" value="#ffffff" />
                    <param name="movie" value="'. $uc_avatarflash .'"/>
                    <param name="menu" value="false" />
                    <embed src="'.$uc_avatarflash .'" quality="high" bgcolor="#ffffff" width="450" height="253" name="mycamera" align="middle" 
                    allowScriptAccess="always" allowFullScreen="false" scale="exactfit"  wmode="transparent" type="application/x-shockwave-flash" 
                    pluginspage="http://www.macromedia.com/go/getflashplayer" /></object>';
        }
        else {
            return array("width", "450", "height", "253", "scale", "exactfit", "src", $uc_avatarflash, "id", "mycamera", "name", "mycamera", "quality", "high", "bgcolor", "#ffffff", "wmode", "transparent", "menu", "false", "swLiveConnect", "true", "allowScriptAccess", "always");
        }
    }

    public function uc_api_input($data)
    {
        $s = urlencode($this->uc_authcode($data . "&sid=" . cookie('weblib_id') . "&time=" . time(), "ENCODE", self::UC_KEY));
        return $s;
    }

    public function uc_authcode($string, $operation = "DECODE", $key = "", $expiry = 0)
    {
        $ckey_length = 4;
        $key = md5($key ? $key : self::UC_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = ($ckey_length ? ($operation == "DECODE" ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : "");
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        $string = ($operation == "DECODE" ? base64_decode(substr($string, $ckey_length)) : sprintf("%010d", $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string);
        $string_length = strlen($string);
        $result = "";
        $box = range(0, 255);
        $rndkey = array();

        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ $box[($box[$a] + $box[$j]) % 256]);
        }

        if ($operation == "DECODE") {
            if (((substr($result, 0, 10) == 0) || (0 < (substr($result, 0, 10) - time()))) && (substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16))) {
                return substr($result, 26);
            }
            else {
                return "";
            }
        }
        else {
            return $keyc . str_replace("=", "", base64_encode($result));
        }
    }


    public function unique($tsg_code, $dz_code, $dz_id = 0)
    {
        $dz_id = $this->field("dz_id")->where(array(
            "dz_code" => $dz_code,
            "dz_id" => array("neq", $dz_id)
        ))->find();

        if ($dz_id) {
            $this->error = "证号重复,已存在此读者证号(全部分馆范围)";
            return false;
        }

        $mod_dck = d("Dck");
        $dck_info = $mod_dck->field("dck_id")->where(array("barcode" => $dz_code))->find();

        if ($dck_info) {
            $this->error = "证号重复,已被图书条码占用(全部分馆范围)";
            return false;
        }

        return true;
    }

    public function add_dz($user_info, $add_data, $fee_type)
    {
        if (!$add_data) {
            $this->error = "读者数据为空";
            return false;
        }

        if (empty($user_info["tsg_code"])) {
            $this->error = "分馆代码为空";
            return false;
        }

        if (!$add_data["dz_code"]) {
            $this->error = l("dz_code_required");
            return false;
        }

        if (!$add_data["real_name"]) {
            $this->error = l("real_name_required");
            return false;
        }

        if (!$add_data["beg_time"]) {
            $this->error = l("beg_time_required");
            return false;
        }

        if (!$add_data["dz_type_code"]) {
            $this->error = l("dz_type_code_required");
            return false;
        }

        if (!$add_data["end_time"]) {
            $this->error = l("end_time_required");
            return false;
        }

        $mod_dz_type = d("Dz_type");
        $dz_type_info = $mod_dz_type->field("dz_type_name,dz_ple_money,gongben_fee,serv_fee,ver_fee")->where("tsg_code='{$user_info["tsg_code"]}' AND dz_type_code='{$add_data["dz_type_code"]}'")->find();

        if (!$dz_type_info) {
            $this->error = "读者类型不存在";
            return false;
        }

        if (!$this->unique($user_info["tsg_code"], $add_data["dz_code"])) {
            return false;
        }

        if (!empty($add_data["unit_name"])) {
            $mod_dz_unit = d("Dz_unit");
            $is_ok = $mod_dz_unit->createUnit($user_info["tsg_code"], $add_data["unit_name"]);

            if ($is_ok === false) {
                $this->error = l("add_dzunit_error");
                return false;
            }
        }

        $add_data["ple_money"] = $dz_type_info["dz_ple_money"];
        $add_data["beg_time"] = mstrtotime($add_data["beg_time"]);
        $add_data["end_time"] = mstrtotime($add_data["end_time"]);
        $add_data["tsg_code"] = $user_info["tsg_code"];
        $add_data["op_user"] = $user_info["user_name"];
        $add_data["dz_add_time"] = time();

        if ($fee_type == Finance::FEE_TYPE_NOPAY) {
            $add_data["owe_money"] = $dz_type_info["dz_ple_money"] + $dz_type_info["gongben_fee"] + $dz_type_info["serv_fee"] + $dz_type_info["ver_fee"];
        }

        $dz_id = $this->create($add_data)->getLastInsID();

        if ($dz_id === false) {
            $this->error = "数据库插入失败";
            return false;
        }

        $mod_finance = d("Finance");
        $finan_data_list = array();
        $finan_data = array();
        $finan_data["tsg_code"] = $user_info["tsg_code"];
        $finan_data["fin_type"] = $fee_type;
        $finan_data["add_time"] = time();
        $finan_data["op_user"] = $user_info["user_name"];
        $finan_data["unit_name"] = $add_data["unit_name"];
        $finan_data["real_name"] = $add_data["real_name"];
        $finan_data["dz_code"] = $add_data["dz_code"];
        $finan_data["dz_id"] = $dz_id;
        $finan_data["fee_type"] = $fee_type;
        $finan_data["fin_type"] = Finance::FIN_TYPE_IN;
        $finan_data["fin_mode"] = Finance::FIN_MODE_MONEY;

        if (0 < $dz_type_info["dz_ple_money"]) {
            $finan_data_tmp = $finan_data;
            $finan_data_tmp["fee_mode"] = Finance::FEE_MODE_DZCARD;
            $finan_data_tmp["fee_money"] = $dz_type_info["dz_ple_money"];
            $finan_data_list[] = $finan_data_tmp;
        }

        if (0 < $dz_type_info["gongben_fee"]) {
            $finan_data_tmp = $finan_data;
            $finan_data_tmp["fee_mode"] = Finance::FEE_MODE_GONGBEN;
            $finan_data_tmp["fee_money"] = $dz_type_info["gongben_fee"];
            $finan_data_list[] = $finan_data_tmp;
        }

        if (0 < $dz_type_info["serv_fee"]) {
            $finan_data_tmp = $finan_data;
            $finan_data_tmp["fee_mode"] = Finance::FEE_MODE_FUWUFEI;
            $finan_data_tmp["fee_money"] = $dz_type_info["serv_fee"];
            $finan_data_list[] = $finan_data_tmp;
        }

        if (0 < $dz_type_info["ver_fee"]) {
            $finan_data_tmp = $finan_data;
            $finan_data_tmp["fee_mode"] = Finance::FEE_MODE_YANZHENG;
            $finan_data_tmp["fee_money"] = $dz_type_info["ver_fee"];
            $finan_data_list[] = $finan_data_tmp;
        }

        if (0 < count($finan_data_list)) {
            $is_success = $mod_finance->insertAll($finan_data_list);

            if ($is_success === false) {
                $this->error = "插入读者押金等财务信息数据失败";
                return false;
            }
        }

        return $dz_id;
    }

    public function update_ext($tsg_code, $where, $save_data)
    {
        if (empty($tsg_code)) {
            $this->error = "分馆代码不能为空";
            return false;
        }

        if (empty($where)) {
            $this->error = "条件数组不能为空";
            return false;
        }

        if (empty($save_data)) {
            $this->error = "更新数据不能为空";
            return false;
        }

        $mod_lend = d("Lend");
        $mod_reser = d("Reser");
        $mod_lend_reser = d("Lend_reser");
        $mod_finance = d("Finance");
        $where["tsg_code"] = $tsg_code;
        $save_data_lend = array();
        isset($save_data["dz_code"]) && ($save_data_lend["dz_code"] = $save_data["dz_code"]);
        isset($save_data["phone_mob"]) && ($save_data_lend["phone_mob"] = $save_data["phone_mob"]);
        isset($save_data["email"]) && ($save_data_lend["email"] = $save_data["email"]);
        isset($save_data["dz_type_code"]) && ($save_data_lend["dz_type_code"] = $save_data["dz_type_code"]);
        isset($save_data["dz_type_name"]) && ($save_data_lend["dz_type_name"] = $save_data["dz_type_name"]);
        isset($save_data["real_name"]) && ($save_data_lend["real_name"] = $save_data["real_name"]);
        isset($save_data["unit_name"]) && ($save_data_lend["unit_name"] = $save_data["unit_name"]);

        if (!empty($save_data_lend)) {
            $is_success = $mod_lend->update($save_data_lend, $where)->result;

            if ($is_success === false) {
                $this->error = "更新流通数据失败";
                return false;
            }
        }

        $save_data_reser = array();
        isset($save_data["dz_code"]) && ($save_data_reser["dz_code"] = $save_data["dz_code"]);
        isset($save_data["phone_mob"]) && ($save_data_reser["phone_mob"] = $save_data["phone_mob"]);
        isset($save_data["email"]) && ($save_data_reser["email"] = $save_data["email"]);
        isset($save_data["dz_type_code"]) && ($save_data_reser["dz_type_code"] = $save_data["dz_type_code"]);
        isset($save_data["dz_type_name"]) && ($save_data_reser["dz_type_name"] = $save_data["dz_type_name"]);
        isset($save_data["real_name"]) && ($save_data_reser["real_name"] = $save_data["real_name"]);
        isset($save_data["unit_name"]) && ($save_data_reser["unit_name"] = $save_data["unit_name"]);

        if (!empty($save_data_reser)) {
            $is_success = $mod_reser->update($save_data_reser, $where)->result;

            if ($is_success === false) {
                $this->error = "更新预约数据失败";
                return false;
            }
        }

        $save_data_lend_reser = array();
        isset($save_data["dz_code"]) && ($save_data_lend_reser["dz_code"] = $save_data["dz_code"]);
        isset($save_data["phone_mob"]) && ($save_data_lend_reser["phone_mob"] = $save_data["phone_mob"]);
        isset($save_data["email"]) && ($save_data_lend_reser["email"] = $save_data["email"]);
        isset($save_data["dz_type_code"]) && ($save_data_lend_reser["dz_type_code"] = $save_data["dz_type_code"]);
        isset($save_data["dz_type_name"]) && ($save_data_lend_reser["dz_type_name"] = $save_data["dz_type_name"]);
        isset($save_data["real_name"]) && ($save_data_lend_reser["real_name"] = $save_data["real_name"]);
        isset($save_data["unit_name"]) && ($save_data_lend_reser["unit_name"] = $save_data["unit_name"]);

        if (!empty($save_data_lend_reser)) {
            $is_success = $mod_lend_reser->update($save_data_lend_reser, $where)->result;

            if ($is_success === false) {
                $this->error = "更新预借数据失败";
                return false;
            }
        }

        $save_data_finance = array();
        isset($save_data["dz_code"]) && ($save_data_finance["dz_code"] = $save_data["dz_code"]);
        isset($save_data["real_name"]) && ($save_data_finance["real_name"] = $save_data["real_name"]);
        isset($save_data["unit_name"]) && ($save_data_finance["unit_name"] = $save_data["unit_name"]);

        if (!empty($save_data_finance)) {
            $is_success = $mod_finance->update($save_data_finance, $where)->result;

            if ($is_success === false) {
                $this->error = "更新财务数据失败";
                return false;
            }
        }

        return true;
    }

    public function get_dz_status_list()
    {
        return array("有效", "挂失", "暂停", "退证", "补办", "注销");
    }

    public function get_dz_status_sound_map()
    {
        return array("挂失" => "20", "暂停" => "16", "退证" => "17", "补办" => "18", "注销" => "15");
    }

    public function get_cred_type_list()
    {
        return array("身份证", "驾驶证", "护照", "军官证", "学生证", "老年证");
    }

    /**
     * @param $tsg_code @分馆代码
     * @param $dz_id @读者id
     * @return bool|int     @返回int为上限
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 更新读者的违章次数---到达违章上限暂停读者使用
     */
    public function addViolateCnt($tsg_code, $dz_id)
    {
        if (empty($tsg_code)) {
            $this->error = "无效的分馆代码";
            return false;
        }

        if (empty($dz_id)) {
            $this->error = "无效的读者ID";
            return false;
        }

        $dz_info = self::field("all_violate_cnt,violate_cnt,dz_type_code,tsg_code")->where(['dz_id' => $dz_id])->find();

        if (empty($dz_info)) {
            $this->error = "读者数据不存在";
            return false;
        }

        $dz_type_model = Loader::model("DzType");
        $dz_type_info = $dz_type_model->isDzType($dz_info['tsg_code'], $dz_info['dz_type_code'], $tsg_code, 'dz_type_name,vr_stop_cnt,is_inter');

        $violate_cnt = $dz_info["violate_cnt"] + 1;
        $save_data = array(
            "all_violate_cnt" => $dz_info["all_violate_cnt"] + 1,
            "violate_cnt" => $violate_cnt);
        $where = [];
        $where['dz_id'] = $dz_id;
        $return = true;
        if (!empty($dz_type_info["vr_stop_cnt"]) && ($dz_type_info["vr_stop_cnt"] <= $violate_cnt)) {
            $save_data["dz_status"] = "暂停";
            $save_data["violate_cnt"] = 0;
            $return = 1;
        }

        $is_success = self::update($save_data, $where)->result;

        if ($is_success === false) {
            $this->error = "更新读者数据失败!";
            return false;
        }
        return $return;
    }

    public function drop_dz($dz_id)
    {
        $mod_lend = d("Lend");

        if ($mod_lend->hasLendBook($dz_id)) {
            $this->error = "读者还有书未归还";
            return false;
        }

        $mod_finance = d("Finance");
        $where_fin = array();
        $where_fin["dz_id"] = $dz_id;
        $where_fin["fee_type"] = Finance::FEE_TYPE_NOPAY;
        $where_fin['fee_money'] = ['neq', 0];
        $finance_info = $mod_finance->where($where_fin)->find();

        if ($finance_info) {
            $this->error = "读者尚有罚款未缴或有退款未支付";
            return false;
        }

        $is_success = $this->where("dz_id=$dz_id")->delete();

        if ($is_success === false) {
            $this->error = "数据库删除失败";
            return false;
        }

        $is_success = $mod_finance->where("dz_id=$dz_id")->delete();
        if ($is_success === false) {
            $this->error = "删除读者财务信息失败";
            return false;
        }
        return true;
    }

}
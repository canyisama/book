<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/19
 * Time: 11:11
 */

namespace app\admin\model;

/**
 * Class Ltrule
 * @package app\admin\model
 * 图书流通规则模型类
 */
class Ltrule extends Base
{
    const LT_IS_RULE_TYPE_BASE = 1;
    const LT_IS_RULE_TYPE_EXT = 2;


    /**
     * @param $mode     @财务字段
     * @param int $all  @是否获取全部数组
     * @return mixed|string
     * 流通类型获取
     */
    public static function getType($mode,$all = 0)
    {
        $type_lists = config('lt_rule_type.'.$mode);
        if ($all === 0){
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

    /**
     * @param $tsg_code
     * @param $dz_type_code
     * @param $ltype_code
     * @param $is_inter
     * @param int $ltrule_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 判断是否唯一流通规则
     */
    public static function unique($tsg_code, $dz_type_code, $ltype_code, $is_inter, $ltrule_id = 0)
    {
        $ltrule_list = self::field("dz_type_code,ltype_code")->where([
            "tsg_code"  => $tsg_code,
            "is_inter"  => $is_inter,
            "rule_type" => self::LT_IS_RULE_TYPE_EXT,
            "ltrule_id" => array("neq", $ltrule_id)
        ])->select();

        if ($is_inter == 0) {
            //
            if (($dz_type_code == "") && ($ltype_code == "") && !empty($ltrule_list)) {
                return false;
            }

            foreach ($ltrule_list as $item ) {
                if ((($item["dz_type_code"] == $dz_type_code) || ($dz_type_code == "") || ($item["dz_type_code"] == ""))
                    && (($item["ltype_code"] == $ltype_code) || ($ltype_code == "") || ($item["ltype_code"] == ""))) {
                    return false;
                }
            }
        }
        else {
            if (($ltype_code == "") && !empty($ltrule_list)) {
                return false;
            }

            foreach ($ltrule_list as $item ) {
                if (($item["ltype_code"] == $ltype_code) || ($ltype_code == "") || ($item["ltype_code"] == "")) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param $tsg_code         @分馆代码
     * @param $dz_type_code     @读者类型代码
     * @param $ltype_code       @流通规则代码
     * @param $tsg_site_code    @馆藏地址代码
     * @return \___PHPSTORM_HELPERS\static|array
     * @throws \think\exception\DbException
     */
    public static function getRule($tsg_code, $dz_type_code, $ltype_code, $tsg_site_code)
    {

        $ltrule_list = self::all(['tsg_code'=>$tsg_code,'is_inter'=>0]);
        $base_rule = array();
        $ext_rule = array();
//        return false;
        foreach ($ltrule_list as $item ) {
            if ($item["rule_type"] == self::LT_IS_RULE_TYPE_BASE) {
                $base_rule = $item;
            }
            else {
                $tsg_site_code_list = explode(",", $item["tsg_site_code"]);
                if ((($item["dz_type_code"] == $dz_type_code) || ($dz_type_code == "") || ($item["dz_type_code"] == ""))
                    && (($item["ltype_code"] == $ltype_code) || ($ltype_code == "") || ($item["ltype_code"] == ""))
                    && (in_array($tsg_site_code, $tsg_site_code_list) || empty($tsg_site_code) || empty($item["tsg_site_code"]))) {
                    $ext_rule = $item;
                    break;
                }
            }
        }

        $re_rule = (!empty($ext_rule) ? $ext_rule : $base_rule);
        return $re_rule;
    }

    /**
     * @param $tsg_code             @分馆代码
     * @param $ltype_code           @流通规则代码
     * @param $tsg_site_code        @馆藏地址代码
     * @return \___PHPSTORM_HELPERS\static|array
     * @throws \think\exception\DbException
     */
    public static function getRuleInter($tsg_code, $ltype_code, $tsg_site_code)
    {
        $ltrule_list = self::all(['tsg_code'=>$tsg_code,'is_inter'=>1]);
        $base_rule = array();
        $ext_rule = array();

        foreach ($ltrule_list as $item ) {
            if ($item["rule_type"] == self::LT_IS_RULE_TYPE_BASE) {
                $base_rule = $item;
            }
            else {
                $tsg_site_code_list = explode(",", $item["tsg_site_code"]);
                if ((($item["ltype_code"] == $ltype_code) || ($ltype_code == "") || ($item["ltype_code"] == ""))
                    && (in_array($tsg_site_code, $tsg_site_code_list) || empty($tsg_site_code) || empty($item["tsg_site_code"]))) {
                    $ext_rule = $item;
                    break;
                }
            }
        }

        $re_rule = (!empty($ext_rule) ? $ext_rule : $base_rule);
        return $re_rule;
    }

    /**
     * @param $dz_id            @读者id
     * @param $rule_info        @流通规则信息
     * @param int $is_inter     @是否馆际
     * @return bool|int|string
     * 获取读者现在借阅数量
     */
    public function getCurrLendNum($dz_id, $rule_info, $is_inter = 0)
    {
        if (empty($dz_id)) {
            $this->error = "错误的读者ID参数";
            return false;
        }

        if (empty($rule_info)) {
            $this->error = "错误的流通规则参数";
            return false;
        }

        $lend_where = array();
        $lend_where["lend_status"] = Lend::LEND_STATUS_ON;
        $lend_where["dz_id"] = $dz_id;
        $lend_where["tsg_code"] = $rule_info["tsg_code"];

//        if ($is_inter == 0) {
//            if (!empty($rule_info["dz_type_code"])) {
//                $lend_where["dz_type_code"] = $rule_info["dz_type_code"];
//            }
//        }
//        else {
//            $lend_where["is_inter_lend"] = 1;
//        }
        $is_inter == 0 ? $lend_where["is_inter_lend"] = 1 : null;

        if (!empty($rule_info["dz_type_code"])) {
            $lend_where["dz_type_code"] = $rule_info["dz_type_code"];
        }

        if (!empty($rule_info["ltype_code"])) {
            $lend_where["ltype_code"] = $rule_info["ltype_code"];
        }

        if (!empty($rule_info["tsg_site_code"])) {
            $lend_where["tsg_site_code"] = array("in", $rule_info["tsg_site_code"]);
        }

        $lend_cnt = Lend::where($lend_where)->count();
        return $lend_cnt;
    }

    public static function addBaseRule($tsg_code, $is_inter)
    {
        $add_data = array(
            "tsg_code" => $tsg_code,
            "is_close" => 0,
            "is_inter" => $is_inter,
            "rule_type" => self::LT_IS_RULE_TYPE_BASE,
            "lend_num" => 5,
            "lend_days" => 30,
            "lose_mode" => 2,
            "lose_type" => 1,
            "lose_rate" => 3,
            "renew_mode" => 2,
            "renew_cnt" => 1,
            "renew_days" => 7,
            "out_max_fine" => 10,
            "dirty_mode" => 2,
            "dirty_type" => 1,
            "dirty_rate" => 1,
            "out_fine" => 0.1);

        return self::create($add_data);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 10:24
 */

namespace app\admin\model;

/**
 * Class Tsg
 * @package app\admin\model
 * 分馆模型类
 */
class Tsg extends Base
{

    public function unique($tsg_code)
    {
        $tsg_code = $this->field("tsg_code")->where(array("tsg_code" => $tsg_code))->find();

        if ($tsg_code) {
            return false;
        }

        return true;
    }

    public function getTsgName($tsg_code)
    {
        if (empty($tsg_code)) {
            return "";
        }

        $tsg_info = $this->field("tsg_name")->where("tsg_code='$tsg_code'")->find();
        return $tsg_info ? $tsg_info["tsg_name"] : "";
    }

    public function addDefaultData($tsg_code)
    {
        if (empty($tsg_code)) {
            $this->error = "分馆代码不能为空";
            return false;
        }

        $bm_cnf = c("catalog");
        $data_arr = $bm_cnf["jd_cnf_default"];
        $mod_jdbm_cnf = d("Jdbm_cnf");

        foreach ($data_arr as $key => $item) {
            foreach ($item as $item1) {
                $is_success = $mod_jdbm_cnf->create(array("tsg_code" => $tsg_code, "cnf_type" => $key, "cnf_val" => $item1[0], "remark" => $item1[1]))->result;

                if ($is_success === false) {
                    $this->error = "插入编目基本配置失败";
                    return false;
                }
            }
        }

        import('DefaultData\DefaultData', EXTEND_PATH, '.class.php');
        $mod_role = d("Role");
        $default_obj = new \DefaultData(\DefaultData::DATA_TSG_ROLE);
        $role_data = $default_obj->getData();

        foreach ($role_data as $key => $item) {
            $role_data[$key]["tsg_code"] = $tsg_code;
        }
        $is_success = $mod_role->insertAll($role_data);
        if ($is_success === false) {
            $this->error = "插入角色失败" . $mod_role->db()->getError();
            return false;
        }

        $mod_role_acl = d("Role_acl");
        $default_obj->setDataType(\DefaultData::DATA_TSG_ROLE_ACL);
        $role_acl_data = $default_obj->getData();

        foreach ($role_acl_data as $key => $item) {
            $role_acl_data[$key]["tsg_code"] = $tsg_code;
        }
        $is_success = $mod_role_acl->insertAll($role_acl_data);
        if ($is_success === false) {
            $this->error = "插入角色权限失败";
            return false;
        }

        $mod_ltype = d("Ltype");
        $ltype_data = array("ltype_code" => "LT001", "ltype_name" => "中文图书", "tsg_code" => $tsg_code);
        $is_success = $mod_ltype->create($ltype_data)->result;

        if ($is_success === false) {
            $this->error = "插入流通类型失败";
            return false;
        }

        $mod_dz_type = d("Dz_type");
        $default_obj->setDataType(\DefaultData::DATA_TSG_DZ_TYPE);
        $dz_type_data = $default_obj->getData();
        $dz_type_data["tsg_code"] = $tsg_code;
        $is_success = $mod_dz_type->create($dz_type_data)->result;

        if ($is_success === false) {
            $this->error = "插入读者类型失败";
            return false;
        }

        $mod_qk_cycle = d("Qk_cycle");
        $default_obj->setDataType(\DefaultData::DATA_TSG_QK_CYCLE);
        $qk_cycle_data = $default_obj->getData();

        foreach ($qk_cycle_data as $key => $item) {
            $qk_cycle_data[$key]["tsg_code"] = $tsg_code;
        }
        $is_success = $mod_qk_cycle->insertAll($qk_cycle_data);
        if ($is_success === false) {
            $this->error = "插入期刊出版周期失败";
            return false;
        }

        $mod_email_tpl = d("Email_tpl");
        $default_obj->setDataType(\DefaultData::DATA_EMAIL_TPL);
        $email_tpl_data = $default_obj->getData();

        foreach ($email_tpl_data as $key => $item) {
            $email_tpl_data[$key]["tsg_code"] = $tsg_code;
        }
        $is_success = $mod_email_tpl->insertAll($email_tpl_data);
        if ($is_success === false) {
            $this->error = "插入邮箱模板数据失败";
            return false;
        }

        $mod_sms_tpl = d("Sms_tpl");
        $default_obj->setDataType(\DefaultData::DATA_SMS_TPL);
        $sms_tpl_data = $default_obj->getData();

        foreach ($sms_tpl_data as $key => $item) {
            $sms_tpl_data[$key]["tsg_code"] = $tsg_code;
        }
        $is_success = $mod_sms_tpl->insertAll($sms_tpl_data);
        if ($is_success === false) {
            $this->error = "插入短信模板数据失败";
            return false;
        }

        $mod_ltrule = d("Ltrule");
        $ltrule_id = $mod_ltrule->addBaseRule($tsg_code, 0)->result;
        if ($ltrule_id === false) {
            $this->error = "插入流通规则失败";
            return false;
        }

        $ltrule_id = $mod_ltrule->addBaseRule($tsg_code, 1)->result;
        if ($ltrule_id === false) {
            $this->error = "插入馆际流通规则失败";
            return false;
        }

        $mod_tsg_site = d("Tsg_site");
        $tsg_site_data = array("tsg_code" => $tsg_code, "tsg_site_code" => "gc01", "site_name" => "中文书库");
        $is_success = $mod_tsg_site->create($tsg_site_data)->result;
        if ($is_success === false) {
            $this->error = "插入馆藏地址失败";
            return false;
        }

        $mod_booklab_cnf = d("Booklab_cnf");
        $is_success = $mod_booklab_cnf->addDefaultData($tsg_code);

        if ($is_success === false) {
            $this->error = "插入书标打印模板失败:" . $mod_booklab_cnf->getError();
            return false;
        }

        $mod_volunt = d("Volunt");
        $is_success = $mod_volunt->addDefaultData($tsg_code);

        if ($is_success === false) {
            $this->error = "插入义工管理相关参数失败:" . $mod_volunt->getError();
            return false;
        }
    }
}
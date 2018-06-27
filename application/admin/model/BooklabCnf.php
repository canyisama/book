<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 11:00
 */

namespace app\admin\model;


use think\Exception;

class BooklabCnf extends Base
{

    public function addDefaultData($tsg_code)
    {
        if (!$tsg_code) {
            $this->error = "分馆代码不能为空";
            return false;
        }

        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $catalog_cnf = c("catalog");

        try {
            $this->startTrans();
            $add_data = $catalog_cnf["booklab_print_cnf"];
            $add_data["cnf_name"] = "默认模板5X10";
            $add_data["tsg_code"] = $tsg_code;
            $field_list = $add_data["fields_cnf"];
            unset($add_data["fields_cnf"]);
            $cnf_id = $this->create($add_data)->getLastInsID();

            if ($cnf_id === false) {
                $this->rollback();
                $this->error = "默认模板5X10增加失败";
                return false;
            }

            foreach ($field_list as $key => $item) {
                $field_list[$key]["booklab_cnf_id"] = $cnf_id;
            }

            $is_success = $mod_booklab_cnf_ext->insertAll($field_list);

            if ($is_success === false) {
                $this->rollback();
                $this->error = "默认模板5X10增加失败";
                return false;
            }

            $add_data = $catalog_cnf["booklab_print_cnf1"];
            $add_data["cnf_name"] = "默认模板4X10";
            $add_data["tsg_code"] = $tsg_code;
            $field_list = $add_data["fields_cnf"];
            unset($add_data["fields_cnf"]);
            $cnf_id = $this->create($add_data)->getLastInsID();

            if ($cnf_id === false) {
                $this->rollback();
                $this->error = "默认模板4X10增加失败";
                return false;
            }

            foreach ($field_list as $key => $item) {
                $field_list[$key]["booklab_cnf_id"] = $cnf_id;
            }

            $is_success = $mod_booklab_cnf_ext->insertAll($field_list);

            if ($is_success === false) {
                $this->rollback();
                $this->error = "默认模板4X10增加失败";
                return false;
            }

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            $this->error = "增加默认模板时出现异常";
            return false;
        }
    }

}
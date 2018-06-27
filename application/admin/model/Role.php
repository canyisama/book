<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:26
 */

namespace app\admin\model;


class Role extends Base
{
    public static function get_has_acl($user_info)
    {
        $user_id = $user_info["user_id"];
        $tsg_code = $user_info["tsg_code"];

        $urList = UserRole::all(['user_id' => $user_id]);
        $urIDList = [];
        foreach ($urList as $item) {
            $urIDList[] = $item['role_code'];
        }

        $has_acl_tmp = RoleAcl::where(['role_code' => ['in', $urIDList], 'tsg_code' => $tsg_code])->select();
        $has_acl = array();
        foreach ($has_acl_tmp as $item) {
            $has_acl[] = $item["acl_id"];
        }
        return $has_acl;
    }

    public function unique($tsg_code, $role_code)
    {
        $role_code = $this->field("role_code")->where(array("role_code" => $role_code, "tsg_code" => $tsg_code))->find();

        if ($role_code) {
            return false;
        }

        return true;
    }

    public function _edit_acl($user_info, $tsg_code, $role_code, $acl_id_list, $method = "", $role_acl_list = NULL)
    {
        $mod_role_acl = d("Role_acl");
        if (empty($role_code)) {
            return false;
        }

        $has_acl = $this->get_has_acl($user_info);
        if ($method == "add") {
            if (empty($acl_id_list)) {
                return true;
            }
            if (!$user_info["is_supper"] && !$user_info["is_admin"]) {
                foreach ($acl_id_list as $key => $item) {
                    if (!in_array($item, $has_acl)) {
                        unset($acl_id_list[$key]);
                    }
                }
            }
            $insertList = [];
            foreach ($acl_id_list as $item) {
                $insertList[] = array("tsg_code" => $tsg_code, "role_code" => $role_code, "acl_id" => $item);
            }
            $mod_role_acl->insertAll($insertList);
            return true;

        } else {
            // TODO
            if (empty($acl_id_list)) {
                $is_success = $mod_role_acl->where("tsg_code='$tsg_code' AND role_code='$role_code'")->delete();
                if ($is_success === false) {
                    return false;
                }
                $acl_id_list = [];
            }
            if (!$user_info["is_supper"] && !$user_info["is_admin"]) {
                foreach ($role_acl_list as $key => $item) {
                    if (!in_array($item["acl_id"], $has_acl)) {
                        unset($role_acl_list[$key]);
                    }
                }
                foreach ($acl_id_list as $key => $item) {
                    if (!in_array($item, $has_acl)) {
                        unset($acl_id_list[$key]);
                    }
                }
            }
            $exist_acl = array();
            foreach ($role_acl_list as $item) {
                if (!in_array($item["acl_id"], $acl_id_list)) {
                    $mod_role_acl->where("role_acl_id={$item["role_acl_id"]}")->delete();
                } else {
                    $exist_acl[] = $item["acl_id"];
                }
            }
            $insertList = [];
            foreach ($acl_id_list as $item) {
                if (!in_array($item, $exist_acl)) {
                    $insertList[] = array("tsg_code" => $tsg_code, "role_code" => $role_code, "acl_id" => $item);
                }
            }
            $mod_role_acl->insertAll($insertList);
            return true;
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/12
 * Time: 10:42
 */

namespace app\admin\controller;


use app\admin\model\Role;
use app\admin\model\SysLog;
use app\admin\model\Tsg;
use think\Lang;

class RoleController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/role.php']);
    }

    public function indexAction()
    {
        $tsg_where = [];
        if ($this->adminInfo['tsg_code'] != '999') {
            $tsg_where['tsg_code'] = $this->adminInfo['tsg_code'];
        }
        $tsg_map = Tsg::field("tsg_code,tsg_name")->where($tsg_where)->select();
        $this->assign("tsg_map", $tsg_map);
        $this->assign('user_tsg', $this->adminInfo['tsg_code']);
        return view();
    }

    public function getJsonListAction()
    {
        $condition = [];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }
        if ($this->_user_info["tsg_code"] == "999") {
            empty($condition["tsg_code"]) && ($condition["tsg_code"] = "999");
        } else {
            $condition["tsg_code"] = $this->_user_info["tsg_code"];
        }
        $list = Role::getPageList($condition, $params->limit, $params->order);
        $count = Role::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        if (!$this->isPost) {
            $this->_assign_acl();
            $is_supper = ($this->_user_info["is_supper"] || $this->_user_info["is_admin"] ? "1" : "");
            $this->assign("is_supper", $is_supper);
            $this->assign("role_acl_list_exist", []);
            return view('edit');
        } else {
            $mod_role = d("Role");
            $add_data = [
                'tsg_code' => input('tsg_code'),
                'role_name' => input('role_name'),
                'role_code' => input('role_code')
            ];

            if (!$add_data["role_code"]) {
                $this->error(l("role_code_require"));
            }
            if (strtolower($add_data["role_code"]) == "admin") {
                $this->error('角色代码不能为admin');
            }
            if (preg_match("/[^0-9a-zA-Z]/", $add_data["role_code"])) {
                $this->error(l("role_code_limit"));
            }

            if ($this->_user_info["is_main_tsg"]) {
                if (!$add_data["tsg_code"]) {
                    $this->error(l("tsg_code_require"));
                }
            } else {
                $add_data["tsg_code"] = $this->_user_info["tsg_code"];
            }
            if (!$mod_role->unique($add_data["tsg_code"], $add_data["role_code"])) {
                $this->error(l("role_code_exist"));
            }
            if (!$add_data["role_name"]) {
                $this->error(l("role_name_require"));
            }
            $max_order_num = $mod_role->where("tsg_code='{$add_data["tsg_code"]}'")->max("order_num");
            $max_order_num = ($max_order_num ? $max_order_num + 1 : 255);
            $add_data["order_num"] = $max_order_num;
            try {
                $mod_role->startTrans();
                $is_success = $mod_role->creeate($add_data)->result;

                if ($is_success === false) {
                    $mod_role->rollback();
                    $this->error('新增失败:插入数据库失败');
                }

                $is_success = $mod_role->_edit_acl($this->_user_info, $add_data["tsg_code"], $add_data["role_code"], $this->_post("acl_item/a"), "add");
                if ($is_success === false) {
                    $mod_role->rollback();
                    $this->error('新增失败:插入数据库失败');
                }

                $mod_role->commit();
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addLog(SysLog::OP_TYPE_ROLE_ADD, $this->_user_info, array("db1" => $add_data["role_code"], "op_desc" => "[#],角色名称:{$add_data["role_name"]}"));
                $this->success('新增成功');
            } catch (Exception $e) {
                $mod_role->rollback();
                $this->error('新增失败:程序处理出现异常');
            }
        }
    }

    public function editAction()
    {
        $tsg_code = input('tsg_code');
        $role_code = input('role_code');

        if (!$tsg_code) {
            $this->alertMsg('分馆代码不能为空');
        }
        $mod_role = d("Role");
        $mod_role_acl = d("Role_acl");
        $role_acl_list = $mod_role_acl->where("tsg_code='$tsg_code' AND role_code='$role_code'")->select();
        $role_info = $mod_role->where("tsg_code='$tsg_code' AND role_code='$role_code'")->find();

        if (!$this->isPost) {
            if (!$role_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if (($role_info["tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
            $role_acl_list_exist = array();
            foreach ($role_acl_list as $item) {
                $role_acl_list_exist[] = $item["acl_id"];
            }
            $this->assign("role_acl_list_exist", $role_acl_list_exist);
            $this->_assign_acl();
            $is_supper = ($this->_user_info["is_supper"] || $this->_user_info["is_admin"] ? "1" : "");
            $this->assign("is_supper", $is_supper);
            $is_edit_admin = ($role_info["role_code"] == "Admin" ? "1" : "");
            $this->assign("is_edit_admin", $is_edit_admin);

            if (!$role_info) {
                $this->alertMsg('未找到此角色数据');
            }
            $this->assign('role_info', $role_info);
            return view('edit');
        } else {
            if (!$role_info) {
                $this->error(l("not_found_data"));
            }
            if (($role_info["tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->error(l("not_access_edit_data"));
            }
            if ($role_info["role_code"] == "admin") {
                $this->error('超级管理员必须拥有全部权限,无法修改!');
            }

            $role_code = $this->_get("role_code");
            if (!$mod_role->_edit_acl($this->_user_info, $tsg_code, $role_code, $this->_post("acl_item/a"), "", $role_acl_list)) {
                $this->error(l("edit_role_code_fail"));
                return false;
            }

            $save_data = [
                'role_name' => input('role_name'),
            ];

            unset($save_data["role_code"]);
            unset($save_data["tsg_code"]);
            $is_success = $mod_role->update($save_data, ['role_code' => $role_code, 'tsg_code' => $tsg_code])->result;

            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_ROLE_SAVE, $this->_user_info, array("db1" => $role_info["role_code"], "op_desc" => "[#],角色名称:{$save_data["role_name"]}"));
                $this->success('保存成功！');
            } else {
                $this->error("保存失败！错误提示:" . $mod_role->getError());
            }
        }
    }

    public function dropAction()
    {
        $tsg_code = input('tsg_code');
        $role_code = input('role_code');

        if ($role_code == "Admin") {
            $this->error('禁止删除超级管理员角色');
        }
        if (!$tsg_code) {
            $this->error('分馆代码不能为空');
        }
        $mod_role = d("Role");
        $role_info = $mod_role->where("tsg_code='$tsg_code' AND role_code='$role_code'")->find();
        if (!$role_info) {
            $this->error(l("not_found_data"));
        }
        if (($role_info["tsg_code"] != $this->_user_info["tsg_code"]) && ($this->_user_info["tsg_code"] != "999")) {
            $this->error(l("not_access_edit_data"));
        }
        if (($role_info["role_code"] == "admin") && ($role_info["tsg_code"] == "999")) {
            $this->error('超级管理员无法删除!');
        }

        $mod_user_role = d("User_role");
        $user_role_list = $mod_user_role->join('lib_user', 'lib_user_role.user_id=lib_user.user_id')
            ->where(['role_code' => $role_code, 'belong_tsg_code' => $tsg_code])
            ->fetchSql(false)->find();

        if (!empty($user_role_list)) {
            $this->error('该角色当前存在用户无法删除');
        }

        $mod_role_acl = d("Role_acl");
        $is_success = $mod_role->where("tsg_code='$tsg_code' AND role_code='$role_code'")->delete();
        $mod_role_acl->where("tsg_code='$tsg_code' AND  role_code='$role_code'")->delete();

        if ($is_success !== false) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_ROLE_DROP, $this->_user_info, array("db1" => $role_info["role_code"], "op_desc" => "[#],角色名称:{$role_info["role_name"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_role->getError());
        }
    }

    public function _assign_acl()
    {
        $mod_acl = d("Acl");
        $mod_role = d("Role");
        $acl_list_tmp = $mod_acl->order(['module', 'mod_split', 'order_num'])->fetchSql(false)->select();
        $has_acl = $mod_role->get_has_acl($this->_user_info);
        $acl_list = array();

        foreach ($acl_list_tmp as $item) {
            if (!in_array($item["acl_id"], $has_acl)) {
                $item["no_has_acl"] = 1;
            } else {
                $item["no_has_acl"] = 0;
            }
            $acl_list[$item["module"]][$item["mod_split"]][] = $item;
        }
        $mod_tsg = d("Tsg");
        $tsg_list = $mod_tsg->getMap();
        $mod_split_map = $this->get_mod_split_map();
        $this->assign("acl_list", $acl_list);
        $this->assign("tsg_list", $tsg_list);
        $this->assign('_user_info', $this->_user_info);
        $this->assign("mod_split_map", $mod_split_map);
    }

    public function get_mod_split_map()
    {
        return array(
            "cf" => array(1 => l("menu_cf_yd"), 2 => l("menu_cf_ys"), 3 => l("menu_cf_report"), 4 => l("menu_cf_param"), 5 => l("menu_cf_log")),
            "bm" => array(1 => l("menu_bm_child"), 2 => l("menu_bm_param"), 3 => l("menu_bm_db"), 4 => l("menu_bm_report"), 5 => l("menu_bm_log")),
            "dc" => array(1 => l("menu_dc_sub"), 2 => l("menu_dc_tsg"), 3 => l("menu_dc_report"), 4 => l("menu_dc_log")),
            "dz" => array(1 => l("menu_lt_dzman"), 2 => l("menu_lt_dzman"), 3 => l("menu_lt_report"), 4 => l("menu_lt_finan"), 5 => l("menu_lt_log")),
            "lt" => array(1 => l("menu_lt_man"), 2 => l("menu_lt_query"), 3 => l("menu_lt_param"), 4 => l("menu_lt_report"), 5 => l("menu_lt_log")),
            "qk" => array(1 => l("menu_qkyd"), 2 => l("menu_qkjd"), 3 => l("menu_qkzd"), 4 => l("menu_qk_param"), 5 => l("menu_qk_report"), 6 => l("menu_qk_log")),
            "sys" => array(1 => l("menu_sys_canshu"), 2 => l("menu_sys_opac"), 3 => l("menu_marctype"), 4 => l("menu_sys_email"), 5 => l("menu_sys_sms"), 6 => l("menu_sys_log"))
        );
    }
}
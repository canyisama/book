<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 17:25
 */

namespace app\admin\controller;


use app\admin\model\SysLog;
use app\admin\model\User;
use think\Lang;

/**
 * 系统管理 - 操作员管理
 * Class UserController
 * @package app\admin\controller
 */
class UserController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/user.php']);
    }

    public function indexAction()
    {
        return view();
    }

    public function getJsonListAction()
    {
        $condition = [];
        if (!$this->_user_info['is_main_tsg']) {
            $condition['belong_tsg_code'] = $this->_user_info['tsg_code'];
        }
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }
        $str_field = array('user_name', 'real_name', 'expiry_date', 'is_close', 'user_dep', 'user_post', 'email', 'user_id', 'belong_tsg_code', 'add_time', 'last_login');
        $list = User::getPageList($condition, $params->limit, $params->order, $str_field);
        $count = User::where($condition)->count();
        foreach ($list as $key => $item) {
            $list[$key]['last_login'] = fmt_date_time($item['last_login']);
            $list[$key]['expiry_date'] = fmt_date_time($item['expiry_date']);
        }
        return $this->echoPageData($list, $count);
    }

    public function _assign_tsg()
    {
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->field("tsg_code,tsg_name")->where("tsg_code='{$this->_user_info["belong_tsg_code"]}'")->find();
        $this->assign("tsg_info", $tsg_info);
        $tsg_list = $mod_tsg->field("tsg_code,tsg_name")->select();
        $this->assign("tsg_list", $tsg_list);
    }

    public function addAction()
    {
        if (!$this->isPost) {
            $user_info = array("expiry_date" => time() + (5 * 365 * 24 * 3600));
            $this->_assign_tsg();
            $mod_role = d("Role");

            if ($this->_user_info["tsg_code"] != "999") {
                $role_list = $mod_role->field("role_code,role_name,tsg_code")->where("tsg_code='{$this->_user_info["belong_tsg_code"]}'")->select();
            } else {
                $belong_tsg_code = (isset($_GET["belong_tsg_code"]) ? trim($_GET["belong_tsg_code"]) : $this->_user_info["belong_tsg_code"]);
                $user_info["belong_tsg_code"] = $belong_tsg_code;
                $role_list = $mod_role->field("role_code,role_name,tsg_code")->where("tsg_code='$belong_tsg_code'")->select();
            }
            $this->assign("user_info", $user_info);
            $this->assign("role_list", $role_list);
            $this->assign("user_role_list_exist", []);
            return view('edit');
        } else {
            $mod_user = d("User");
            $add_data = [
                'belong_tsg_code' => input('belong_tsg_code'),
                'user_name' => input('user_name'),
                'user_pwd' => input('user_pwd'),
                'real_name' => input('real_name'),
                'expiry_date' => input('expiry_date'),
                'is_close' => input('is_close'),
                'user_dep' => input('user_dep'),
                'user_post' => input('user_post'),
                'phone' => input('phone'),
                'mobile' => input('mobile'),
                'email' => input('email'),
                'user_addr' => input('user_addr'),
                'ip_limit' => input('ip_limit')
            ];

            if (!$mod_user->unique($add_data["user_name"])) {
                $this->error(l("user_name_exist"));
            }

            $add_data["user_pwd"] = md5($add_data["user_pwd"]);
            $add_data["add_time"] = time();
            $add_data["belong_tsg_code"] = ($this->_user_info["is_main_tsg"] ? $add_data["belong_tsg_code"] : $this->_user_info["belong_tsg_code"]);
            $add_data["expiry_date"] = mstrtotime($add_data["expiry_date"]);
            $catalog_cnf = c("catalog");
            $add_data["pinyin_config"] = $catalog_cnf["pinyin_config_default"];
            $add_data["desk_cnf"] = "1,2,3,4,5,6,7,8,9,10,11,12";
            $mod_mt = d("Mt");
            $mt_info = $mod_mt->where("mt_id=1")->min("mt_id");
            $add_data["default_mt"] = ($mt_info["mt_id"] ? $mt_info["mt_id"] : 1);
            $mt_info = $mod_mt->where("mt_id=2")->min("mt_id");
            $add_data["qk_default_mt"] = ($mt_info["mt_id"] ? $mt_info["mt_id"] : 2);

            try {
                $mod_user->startTrans();
                $user_id = $mod_user->create($add_data)->getLastInsId();

                if ($user_id === false) {
                    $mod_user->rollback();
                    $this->error("新增失败:插入用户数据失败");
                }
                $is_success = $mod_user->_edit_role($user_id, $this->_post("role_code/a"), "add");
                if ($is_success === false) {
                    $mod_user->rollback();
                    $this->error(l("add_role_code_fail"));
                }

                $mod_user->commit();
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addLog(SysLog::OP_TYPE_USER_ADD, $this->_user_info, array("db1" => $user_id, "op_desc" => "[#],用户名:{$add_data["user_name"]}"));
                $this->success('新增成功');
            } catch (Exception $e) {
                $mod_user->rollback();
                $this->error('新增失败:程序处理出现异常');
            }
        }
    }

    public function editAction()
    {
        $user_id = input('user_id/d');
        $mod_user = d("User");
        $user_info = $mod_user->find($user_id);
        $mod_role = d("Role");
        $belong_tsg_code = (isset($_GET["belong_tsg_code"]) ? trim($_GET["belong_tsg_code"]) : $user_info["belong_tsg_code"]);
        $user_info["belong_tsg_code"] = $belong_tsg_code;
        $role_list = $mod_role->field("role_code,role_name,tsg_code")->where("tsg_code='$belong_tsg_code'")->select();
        $this->assign("role_list", $role_list);
        $mod_user_role = d("User_role");
        $user_role_list = $mod_user_role->where("user_id='$user_id'")->select();

        if (!$this->isPost) {
            $user_role_list_exist = array();

            foreach ($user_role_list as $item) {
                $user_role_list_exist[] = $item["role_code"];
            }
            $this->assign("user_role_list_exist", $user_role_list_exist);
            $this->_assign_tsg();
            $this->assign('user_info', $user_info);
            if (!$user_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if (($user_info["belong_tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
            return view('edit');
        } else {
            if (!$user_info) {
                $this->error(l("not_found_data"));
            }
            if (($user_info["belong_tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->error(l("not_access_edit_data"));
            }
            if (!$mod_user->_edit_role($user_id, $this->_post("role_code/a"), "", $user_role_list)) {
                $this->error(l("edit_role_code_fail"));
            }
            $save_data = [
                'belong_tsg_code' => input('belong_tsg_code'),
                'user_name' => input('user_name'),
                'user_pwd' => input('user_pwd'),
                'real_name' => input('real_name'),
                'expiry_date' => input('expiry_date'),
                'is_close' => input('is_close'),
                'user_dep' => input('user_dep'),
                'user_post' => input('user_post'),
                'phone' => input('phone'),
                'mobile' => input('mobile'),
                'email' => input('email'),
                'user_addr' => input('user_addr'),
                'ip_limit' => input('ip_limit')
            ];
            if (!$mod_user->unique($save_data["user_name"], $user_id)) {
                $this->error(l("user_name_exist"));
            }
            if (!empty($save_data["user_pwd"])) {
                $save_data["user_pwd"] = md5($save_data["user_pwd"]);
            } else {
                unset($save_data["user_pwd"]);
            }

            $save_data["belong_tsg_code"] = ($this->_user_info["is_main_tsg"] ? $save_data["belong_tsg_code"] : $this->_user_info["belong_tsg_code"]);
            $save_data["manage_tsg_code"] = ($this->_user_info["is_main_tsg"] ? $save_data["manage_tsg_code"] : "");
            $save_data["expiry_date"] = mstrtotime($save_data["expiry_date"]);
            unset($save_data["user_id"]);
            $is_success = $mod_user->update($save_data, ['user_id' => $user_id])->result;

            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addLog(SysLog::OP_TYPE_USER_SAVE, $this->_user_info, array("db1" => $user_id, "op_desc" => "[#],用户名:{$save_data["user_name"]}"));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！错误提示:' . $mod_user->getError());
            }
        }
    }

    public function dropAction()
    {
        $user_id = input('user_id/d');
        $mod_user = d("User");
        $user_info = $mod_user->where("user_id=$user_id")->find();

        if (!$user_info) {
            $this->error(l("not_found_data"));
        }
        if (($user_info["belong_tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
            $this->error(l("not_access_edit_data"));
        }
        if ($user_info["user_name"] == "admin") {
            $this->error('超级管理员账户不能删除');
        }
        $mod_user_role = d("User_role");
        $where = array();

        if (!$this->_user_info["is_main_tsg"]) {
            $where["tsg_code"] = $this->_user_info["belong_tsg_code"];
        }

        $where["user_id"] = $user_id;
        $is_success = $mod_user->where($where)->delete();
        $mod_user_role->where("user_id=$user_id")->delete();

        if ($is_success) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addLog(SysLog::OP_TYPE_USER_DROP, $this->_user_info, array("db1" => $user_id, "op_desc" => "[#],用户名:{$user_info["user_name"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_user->getError());
        }
    }

    public function _get_search_fields()
    {
        return array("user_name" => l("user_name"), "real_name" => l("real_name"), "belong_tsg_code" => l("belong_tsg_code"), "email" => l("email"), "phone" => l("phone"), "mobile" => l("mobile"), "user_post" => l("user_post"), "user_dep" => l("user_dep"));
    }

    public function _assign_tsgAction()
    {
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->field("tsg_code,tsg_name")->where("tsg_code='{$this->_user_info["belong_tsg_code"]}'")->find();
        $this->assign("tsg_info", $tsg_info);
        $tsg_list = $mod_tsg->field("tsg_code,tsg_name")->select();
        $this->assign("tsg_list", $tsg_list);
    }

    public function changepwdAction()
    {
        if (!$this->isPost) {
            return view();
        } else {
            $user_id = $this->_user_info["user_id"];
            $old_pwd = (isset($_POST["old_pwd"]) ? trim($_POST["old_pwd"]) : "");
            $new_pwd = (isset($_POST["new_pwd"]) ? trim($_POST["new_pwd"]) : "");
            $new_pwd2 = (isset($_POST["new_pwd2"]) ? trim($_POST["new_pwd2"]) : "");
            $mod_user = d("User");
            $user_info = $mod_user->find($user_id);

            if (!$user_info) {
                $this->error('未找到当前用户信息');
            }
            if (!$old_pwd) {
                $this->error('旧密码不能为空');
            }
            if (!$new_pwd) {
                $this->error('新密码不能为空');
            }
            if ($new_pwd != $new_pwd2) {
                $this->error('两次输入的新密码不一致');
            }
            if ($old_pwd == $new_pwd) {
                $this->error('新密码不能和旧密码相同');
            }
            if (md5($old_pwd) != $user_info["user_pwd"]) {
                $this->error('旧密码验证失败');
            }
            $save_data = array("user_pwd" => md5($new_pwd));
            $is_success = $mod_user->update($save_data, ['user_id' => $user_id])->result;

            if ($is_success !== false) {
                $this->success('更改密码成功');
            } else {
                $this->error('更改密码失败,错误提示:更新数据库失败');
            }
        }
    }

}
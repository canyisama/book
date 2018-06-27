<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/8
 * Time: 17:11
 */

namespace app\admin\controller;


use app\admin\model\SysLog;
use app\admin\model\Tsg;
use app\admin\model\Z3950;

class Z3950Controller extends BaseController
{


    public function testAction()
    {
        $z3950_id = input('z3950_id/d');
        $mod_z3950 = d("Z3950");
        $z3950_info = $mod_z3950->find($z3950_id);

        if (empty($z3950_info)) {
            $this->error('未找到此z39.50地址数据');
        }

        import('Z3950\Z3950', EXTEND_PATH, '.class.php');
        $test_msg = "test";//\Z3950::testConn($z3950_info);
        $this->success($test_msg);
    }

    /**
     * Z39.50管理
     */
    public function indexAction()
    {
        return view();
    }

    /**
     * 异步获取列表
     */
    public function getJsonListAction()
    {
        $condition['tsg_code'] = [['eq', $this->adminInfo['tsg_code']], ['eq', 'sys'], 'or'];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Z3950::getPageList($condition, $params->limit, $params->order);
        $count = Z3950::where($condition)->count();
        $this->echoPageData($list, $count);
    }


    public function addAction()
    {
        if (!$this->isPost) {
            $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
            $this->assign("tsg_info", $tsg_info);

            return view('edit');
        } else {
            $mod_z3950 = d("Z3950");
            $add_data = [];
            $add_data['z3950_name'] = input('z3950_name');
            $add_data['addr'] = input('addr');
            $add_data['port'] = input('port');
            $add_data['db'] = input('db');
            $add_data['charset'] = input('charset');
            $add_data['user_name'] = input('user_name');
            $add_data['pwd'] = input('pwd');

            $add_data["tsg_code"] = $this->adminInfo["tsg_code"];
            $z3950_id = Z3950::create($add_data)->getLastInsID();
            if ($z3950_id !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_Z39_ADD, $this->adminInfo, array("db1" => $z3950_id, "op_desc" => "[#],Z39地址:{$add_data["z3950_name"]}"));
                $this->success("新增成功！");
            } else {
                $this->error("新增失败！错误提示:" . $mod_z3950->getError());
            }
        }
    }

    public function editAction()
    {
        $z3950_id = input('z3950_id/d');
        $mod_z3950 = d("Z3950");
        $z3950_info = $mod_z3950->where(['z3950_id' => $z3950_id])->find();
        if (($z3950_info["tsg_code"] == "sys") && !$this->adminInfo["is_main_tsg"]) {
            $this->alertMsg('无权限编辑此z39.50地址数据,只系统管理员可编辑');
        }

        if (!$this->isPost) {
            if (!$z3950_info) {
                $this->alertMsg(lang("not_found_data"));
            }

            if (($z3950_info["tsg_code"] != $this->adminInfo["tsg_code"]) && !$this->adminInfo["is_main_tsg"]) {
                $this->alertMsg(lang("not_access_edit_data"));
            }
            $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
            $this->assign("tsg_info", $tsg_info);
            $this->assign("info", $z3950_info);
            return view();
        } else {
            if (!$z3950_info) {
                $this->error(lang("not_found_data"));
            }

            if (($z3950_info["tsg_code"] != $this->adminInfo["tsg_code"]) && !$this->adminInfo["is_main_tsg"]) {
                $this->error(lang("not_access_edit_data"));
            }

            $save_data = [];
            $save_data['z3950_name'] = input('z3950_name');
            $save_data['addr'] = input('addr');
            $save_data['port'] = input('port');
            $save_data['db'] = input('db');
            $save_data['charset'] = input('charset');
            $save_data['user_name'] = input('user_name');
            $save_data['pwd'] = input('pwd');

            if (empty($save_data["pwd"])) {
                unset($save_data["pwd"]);
            }
            if (empty($save_data["user_name"])) {
                $save_data["pwd"] = "";
            }
            $is_success = Z3950::update($save_data, ['z3950_id' => $z3950_id])->result;
            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_Z39_SAVE, $this->adminInfo, array("db1" => $z3950_id, "op_desc" => "[#],Z39地址:{$save_data["z3950_name"]}"));
                $this->success("编辑z39.50地址成功！");
            } else {
                $this->error("编辑z39.50地址失败,请修改数据重新提交！");
            }
        }
    }

    public function dropAction()
    {
        $z3950_id = input('z3950_id');
        $mod_z3950 = d("Z3950");
        $z3950_info = $mod_z3950->where(['z3950_id' => $z3950_id])->find();

        if (!$z3950_info) {
            $this->error(lang("not_found_data"));
        }

        if (($z3950_info["tsg_code"] != $this->adminInfo["tsg_code"]) && !$this->adminInfo["is_main_tsg"]) {
            $this->error(lang("not_access_edit_data"));
        }

        if (($z3950_info["tsg_code"] == "sys") && !$this->adminInfo["is_main_tsg"]) {
            $this->error("无权限删除此z39.50地址数据,只总馆管理员可删除");
            return false;
        }

        if ($z3950_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error("无权限删除此z39.50地址数据");
            return false;
        }

        $is_success = $mod_z3950->where(['z3950_id' => $z3950_id])->delete();

        if ($is_success) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_Z39_DROP, $this->adminInfo, array("db1" => $z3950_id, "op_desc" => "[#],Z39地址:{$z3950_info["z3950_name"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_z3950->getError());
        }
    }
}
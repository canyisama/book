<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Pubinfo;
use app\admin\model\SysLog;


class PubinfomanController extends BaseController
{

    /**
     * 出版社管理
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
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Pubinfo::getPageList($condition, $params->limit, $params->order);
        $count = Pubinfo::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $pubinfo_id = input('pubinfo_id/d');
        if ($this->isPost) {
            $data = [];
            $data['cbcode'] = input('cbcode');
            $data['publisher'] = input('publisher');
            $data['pubplace'] = input('pubplace');
            $data['area_code'] = input('area_code');

            if (!Pubinfo::unique($data["cbcode"], $pubinfo_id)) {
                $this->error('出版社代码已存在');
            }

            if ($pubinfo_id) {
                $result = Pubinfo::update($data, ['pubinfo_id' => $pubinfo_id])->result;
                $log_type = SysLog::OP_TYPE_PUB_SAVE;
            } else {
                $result = Pubinfo::create($data)->result;
                $log_type = SysLog::OP_TYPE_PUB_ADD;
            }
            if ($result !== false) {
                SysLog::addLog($log_type, $this->adminInfo, ['db1' => $pubinfo_id]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }
        $info = Pubinfo::get(['pubinfo_id' => $pubinfo_id]);
        $this->assign('info', $info);
        return view('edit');
    }

    public function dropAction()
    {
        $pubinfo_id = input('pubinfo_id');
        $mod_pubinfo = d("Pubinfo");
        $pubinfo_info = $mod_pubinfo->where(['pubinfo_id' => $pubinfo_id])->find();

        if (!$pubinfo_info) {
            $this->error(lang("not_found_data"));
        }

        $is_success = $mod_pubinfo->where(['pubinfo_id' => $pubinfo_id])->delete();
        if ($is_success) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_PUB_DROP, $this->_user_info, array("db1" => $pubinfo_id, "op_desc" => "[#],出版社代码:{$pubinfo_info["cbcode"]},出版社:{$pubinfo_info["publisher"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

}
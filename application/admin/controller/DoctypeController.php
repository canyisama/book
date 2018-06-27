<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/7
 * Time: 9:54
 */

namespace app\admin\controller;

use app\admin\model\Doctype;
use app\admin\model\SysLog;

/**
 * Class CatalogCnfController
 * @package app\admin\controller
 * 期刊通用参数
 */
class DoctypeController extends BaseController
{

    /**
     * 文献类型
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

        $list = Doctype::getPageList($condition, $params->limit, $params->order);
        $count = Doctype::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 新增
     * @return \think\response\View
     */
    public function addAction()
    {
        if (!$this->isPost) {
            return view('edit');
        } else {
            $mod_doctype = d('Doctype');
            $add_data = [
                'dt_name' => input('dt_name'),
                'sort_num' => input('sort_num'),
            ];
            if (!$add_data["dt_name"]) {
                $this->error(lang('dt_name_required'));
            }
            $doctype_id = $mod_doctype->create($add_data)->result;
            if ($doctype_id !== false) {
                $mod_sys_log = d('Sys_log');
                $mod_sys_log->addlog(SysLog::OP_TYPE_DOCTYPE_ADD, $this->adminInfo, array("db1" => $doctype_id, "op_desc" => "[#],图书类型名:{$add_data["dt_name"]}"));
                $this->success('新增成功!');
            } else {
                $this->error('新增失败!');
            }
        }
    }

    /**
     * 编辑
     * @return \think\response\View
     */
    public function editAction()
    {
        $doctype_id = input('doctype_id/d');
        $mod_doctype = d("Doctype");
        $doctype_info = $mod_doctype->where(['doctype_id' => $doctype_id])->find();

        if (!$this->isPost) {
            if (!$doctype_info) {
                $this->alertMsg(lang('not_found_data'));
            }
            $this->assign("info", $doctype_info);
            return view();
        } else {
            if (!$doctype_info) {
                $this->error(lang('not_found_data'));
            }
            $save_data = [
                'dt_name' => input('dt_name'),
                'sort_num' => input('sort_num'),
            ];

            if (!$save_data["dt_name"]) {
                $this->error(lang('dt_name_required'));
            }

            $is_success = $mod_doctype->update($save_data, ['doctype_id' => $doctype_id])->result;

            if ($is_success !== false) {
                $mod_sys_log = d('Sys_log');
                $mod_sys_log->addlog(SysLog::OP_TYPE_DOCTYPE_SAVE, $this->adminInfo, array("db1" => $doctype_id, "op_desc" => "[#],图书类型名:{$doctype_info["dt_name"]}"));
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }
    }

    /**
     * 删除
     */
    public function dropAction()
    {
        $doctype_id = input('doctype_id');
        $mod_mt = d("Mt");
        $mt_info = $mod_mt->where(['doctype_id' => $doctype_id])->find();

        if (!empty($mt_info)) {
            $this->error("该图书类型正被【MARC类型】使用,无法删除!");
        }

        $mod_doctype = d("Doctype");
        $doctype_info = $mod_doctype->where(['doctype_id' => $doctype_id])->find();

        if (!$doctype_info) {
            $this->error(lang("not_found_data"));
        }
        $is_success = $mod_doctype->where(['doctype_id' => $doctype_id])->delete();

        if ($is_success !== false) {
            $mod_sys_log = d('Sys_log');
            $mod_sys_log->addlog(SysLog::OP_TYPE_DOCTYPE_DROP, $this->adminInfo, array("db1" => $doctype_id, "op_desc" => "[#],图书类型名:{$doctype_info["dt_name"]}"));
            $this->success("删除成功! ");
        } else {
            $this->error("删除失败! ");
        }
    }

}
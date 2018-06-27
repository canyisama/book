<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\SysLog;
use app\admin\model\Tsg;
use app\admin\model\TsgSite;


class TsgSiteController extends BaseController
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
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = TsgSite::getPageList($condition, $params->limit, $params->order);
        $count = TsgSite::where($condition)->count();
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        foreach ($list as &$item) {
        $item['tsg_code'] = $tsg_info['tsg_code'] . ' | ' . $tsg_info['tsg_name'];
    }
        unset($item);
        return $this->echoPageData($list, $count);
    }

    /**
     * 新增/编辑
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editAction()
    {
        $tsg_site_code = input('tsg_site_code');
        $mod_tsg_site = d("Tsg_site");
        $tsg_site_info = $mod_tsg_site->where(['tsg_site_code' => $tsg_site_code, 'tsg_code' => $this->adminInfo['tsg_code']])->find();

        if (!$this->isPost) {
            if (!$tsg_site_info) {
                echo lang("not_found_data");
                return false;
            }

            if ($tsg_site_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                echo lang("not_access_edit_data");
                return false;
            }

            $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
            $this->assign("tsg_info", $tsg_info);
            $this->assign("info",$tsg_site_info);
//            $this->tsg_site_info = $tsg_site_info;
            return view();
        }
        else {
            if (!$tsg_site_info) {
                $this->error(lang("not_found_data"));
            }

            if ($tsg_site_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang("not_access_edit_data"));
            }

            $tsg_site_code = $this->_get("tsg_site_code");
//            $save_data = $mod_tsg_site->create();
            $save_data = [];
            $save_data['tsg_site_code'] = input('tsg_site_code');
            $save_data['tsg_code'] = input('tsg_code');
            $save_data['site_name'] = input('site_name');
            $save_data['site_remark'] = input('site_remark');

            if (!$save_data) {
                $this->error("未找到此馆藏地址数据");
            }

//            if (!$save_data["site_name"]) {
////                $this->ajaxReturn("", lang("site_name_require"), 0);
////                return false;
//                $this->error(lang("site_name_require"));
//            }

            unset($save_data["tsg_code"]);
//            $is_success = $mod_tsg_site->where("tsg_code='{$this->adminInfo["tsg_code"]}' AND tsg_site_code='$tsg_site_code'")->save($save_data);
            $where = ['tsg_site_code' => $tsg_site_code, 'tsg_code' => $this->adminInfo['tsg_code']];
            $is_success = TsgSite::update($save_data, $where);

            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_TSG_SITE_SAVE, $this->adminInfo, array("db1" => $tsg_site_info["tsg_site_code"], "op_desc" => "[#],馆藏地址名称:{$save_data["site_name"]}"));
                $this->success("编辑馆藏地址成功！");
            }
            else {
                $this->error("编辑馆藏地址失败,请修改数据重新提交！");
            }
        }
    }

    public function addAction()
    {
        if (!$this->isPost) {
            return view();
        }
        else {
            $mod_tsg_site = d("Tsg_site");
//            $add_data = $mod_tsg_site->create();

            $save_data = [];
            $save_data['tsg_site_code'] = input('tsg_site_code');
            $save_data['tsg_code'] = input('tsg_code');
            $save_data['site_name'] = input('site_name');
            $save_data['site_remark'] = input('site_remark');
            $is_success = TsgSite::create($save_data);
            if (!$save_data) {
                $this->error($mod_tsg_site->getError());
                return false;
            }

//            if (!$add_data["tsg_site_code"]) {
////                $this->ajaxReturn("", lang("tsg_site_code_require"), 0);
////                return false;
//                $this->error(lang("tsg_site_code_require"));
//            }

//            if (preg_match("/[^0-9a-zA-Z]/", $add_data["tsg_site_code"])) {
////                $this->ajaxReturn("", lang("tsg_site_code_limit"), 0);
////                return false;
//                $this->error(lang("tsg_site_code_limit"));
//            }

//            if (!$add_data["site_name"]) {
////                $this->ajaxReturn("", lang("site_name_require"), 0);
////                return false;
//                $this->error(lang("site_name_require"));
//            }

            $add_data["tsg_code"] = $this->adminInfo["tsg_code"];
            $mod_tsg_site->data($add_data);
//            $is_success = $mod_tsg_site->add();

            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_TSG_SITE_ADD, $this->adminInfo, array("db1" => $add_data["tsg_site_code"], "op_desc" => "[#],馆藏地址名称:{$add_data["site_name"]}"));
                $this->success("新增成功！");
            }
            else {
                $this->error("新增失败:插入数据失败");
            }
        }
    }

    /**
     * 删除
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function dropAction()
    {
        $tsg_site_code = input('tsg_site_code');
        $mod_tsg_site = d("Tsg_site");
        $tsg_site_info = $mod_tsg_site->where(['tsg_site_code' => $tsg_site_code, 'tsg_code' => $this->adminInfo['tsg_code']])->find();

        if (!$tsg_site_info) {
            $this->error(lang("not_found_data"));
        }

        if ($tsg_site_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang("not_access_edit_data"));
        }

        $is_success = $mod_tsg_site->where(['tsg_site_code' => $tsg_site_code, 'tsg_code' => $this->adminInfo['tsg_code']])->delete();

        if ($is_success !== false) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_TSG_SITE_DROP, $this->adminInfo, array("db1" => $tsg_site_info["tsg_site_code"], "op_desc" => "[#],馆藏地址名称:{$tsg_site_info["site_name"]}"));
            $this->success("删除成功！");
        }
        else {
            $this->error("删除失败！错误提示:" . $mod_tsg_site->getError());
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Dzgl;
use app\admin\model\DzLog;
use app\admin\model\DzUnit;
use app\admin\model\Tsg;
use app\admin\model\User;
use think\Lang;


class DzUnitController extends BaseController
{

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/dz_unit.php']);
    }

    /**
     * 读者单位管理
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

        $list = DzUnit::getPageList($condition, $params->limit, $params->order);
        $count = DzUnit::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
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
        if ($this->isPost) {
            $mod_dz_unit = d("Dz_unit");
            $dz_unit_id = input('dz_unit_id');

            $data = [];
            $data['unit_name'] = input('unit_name');
            $data['sort_num'] = input('sort_num/d');
            $data['remark'] = input('remark');
            if ($dz_unit_id) {
                $dz_unit_info = DzUnit::get($dz_unit_id);
                if (!$mod_dz_unit->unique($this->_user_info["tsg_code"], $data["unit_name"], $dz_unit_id)) {
                    $this->error(l("unit_name_exist"));
                }
                $result = DzUnit::update($data, ['dz_unit_id' => $dz_unit_id, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
                $log_type = DzLog::OP_TYPE_DZ_UNIT_EDIT;
            } else {
                if (!$mod_dz_unit->unique($this->_user_info["tsg_code"], $data["unit_name"])) {
                    $this->error(l("unit_name_exist"));
                }

                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $result = DzUnit::create($data)->result;
                $log_type = DzLog::OP_TYPE_DZ_UNIT_ADD;
            }
            if ($result !== false) {
                if ($dz_unit_id && $dz_unit_info["unit_name"] != $data["unit_name"]) {
                    Dzgl::update(['unit_name' => $data['unit_name']], ['tsg_code' => $this->_user_info["tsg_code"], 'unit_name' => $dz_unit_info["unit_name"]]);
                }

                DzLog::addLog($log_type, $this->adminInfo, ['db1' => $dz_unit_id]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $dz_unit_id = input('dz_unit_id');
        $info = DzUnit::get(['dz_unit_id' => $dz_unit_id, 'tsg_code' => $this->adminInfo['tsg_code']]);
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();

        $this->assign('info', $info);
        $this->assign("tsg_info", $tsg_info);
        return view('edit');
    }

    /**
     * 删除
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dropAction()
    {
        $dz_unit_id = input('dz_unit_id/d');
        $dz_unit_info = DzUnit::where(['dz_unit_id' => $dz_unit_id])->find();

        if (!$dz_unit_info) {
            $this->error(lang('not_found_data'));
        }
        if ($dz_unit_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }
        $dz_info = Dzgl::where(['tsg_code' => $this->adminInfo['tsg_code'], 'unit_name' => $dz_unit_info['unit_name']])->find();

        if (!empty($dz_info)) {
            $this->error(lang('exist_dz_info'));
        }
        $is_success = DzUnit::where(['dz_unit_id' => $dz_unit_id])->delete();
        if ($is_success !== false) {
            DzLog::addLog(DzLog::OP_TYPE_DZ_UNIT_DROP, $this->adminInfo, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者单位-删除,读者单位名称:【{$dz_unit_info["unit_name"]}】"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败");
        }
    }

}
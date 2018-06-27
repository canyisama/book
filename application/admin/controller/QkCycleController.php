<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/7
 * Time: 11:25
 */

namespace app\admin\controller;


use app\admin\model\Qk;
use app\admin\model\QkCycle;
use app\admin\model\QkLog;
use think\Exception;
use think\Lang;

class QkCycleController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH .'lang' . DS . 'zh-cn' . DS . 'home/qk_cycle.php');
    }

    public function indexAction()
    {
        return view();
    }

    public function getJsonListAction()
    {
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数

        if ($params->search) {
            foreach ($params->search as $search) {
                    $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = QkCycle::getPageList($condition, $params->limit, $params->order);
        $count = QkCycle::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @return bool|\think\response\View
     * 新增
     */
    public function addAction()
    {
        //REG

        if (!$this->isPost) {
            return view('edit');
        }
        try{
            $add_data = $this->request->post();

            if (!$add_data["cycle_name"]) {
                $this->error(lang('cycle_name_required'));
            }

            if (!$add_data["cycle_cnt"]) {
                $this->error(lang('cycle_cnt_required'));
            }

            if (!QkCycle::unique($add_data["cycle_name"],$this->adminInfo['tsg_code'])) {
                $this->error(lang("cycle_name_exist"));
                return false;
            }

            $add_data["tsg_code"] = $this->_user_info["tsg_code"];
            $qk_cycle_info = QkCycle::create($add_data,true)->result;

            if ($qk_cycle_info !== false) {
                QkLog::addlog(QkLog::OP_TYPE_CYCLE_ADD, $this->_user_info, array("db1" => $add_data["cycle_name"], "db2" => $add_data["cycle_cnt"]));
                $this->success('新增成功');
            }
            else {
                $this->error('新增失败!');
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * @return bool|\think\response\View
     * @throws \think\exception\DbException
     * 编辑
     */
    public function editAction()
    {
        $qk_cycle_id = input('qk_cycle_id/d');
        $qk_cycle_info = QkCycle::get($qk_cycle_id);

        if (!$this->isPost) {
            if (!$qk_cycle_info) {
                $this->alertMsg('数据库未找到此数据');
            }
            $this->assign("info", $qk_cycle_info);
            return view();

        }
        try {
            $save_data = $this->request->post();

            if (!$save_data["cycle_name"]) {
                $this->error(lang('cycle_name_required'));
            }

            if (!$save_data["cycle_cnt"]) {
                $this->error(lang('cycle_cnt_required'));
            }

            if (!QkCycle::unique($save_data["cycle_name"],$this->adminInfo['tsg_code'] ,$qk_cycle_id)) {
                $this->error(lang("cycle_name_exist"));
                return false;
            }

            unset($save_data["qk_cycle_id"]);

            $where = ['qk_cycle_id'=>$qk_cycle_id];
            $is_success = QkCycle::update($save_data,$where);

            if ($is_success !== false) {
                QkLog::addlog(QkLog::OP_TYPE_CYCLE_SAVE, $this->_user_info, array("db1" => $qk_cycle_info["cycle_name"], "db2" => $qk_cycle_info["cycle_cnt"]));
                $this->success('保存成功!');
            }
            else {
                $this->error('保存失败!');
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }


    /**
     * 删除
     */
    public function dropAction()
    {
        try{
            $qk_cycle_id = input('qk_cycle_id/d');

            $qk_cycle_info = QkCycle::get($qk_cycle_id);

            if (!$qk_cycle_info) {
                $this->error(\lang('not_found_data'));
            }

            if ($qk_cycle_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(\lang('not_access_edit_data'));
            }

            $where = [
                'tsg_code' => $this->adminInfo['tsg_code'],
                'cycle_name' => $qk_cycle_info['cycle_name']
            ];

            $qk_info = Qk::get($where);

            if (!empty($qk_info)) {
                $this->error('此出版周期存在期刊无法删除');
            }

            $is_success = $qk_cycle_info->delete();

            if ($is_success) {
                QkLog::addlog(QkLog::OP_TYPE_CYCLE_DROP, $this->_user_info, array( "db1" => $qk_cycle_info["cycle_name"], "db2" => $qk_cycle_info["cycle_cnt"]));
                $this->success("删除成功！");
            }
            else {
                $this->error("删除失败！");
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }

}
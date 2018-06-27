<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 11:16
 */

namespace app\admin\controller;


use app\admin\model\CfLog;
use app\admin\model\Cost;
use app\admin\model\Tsg;
use think\Lang;

class CostController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/cost.php']);
    }


    /**
     * 预算管理
     */
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

        $list = Cost::getPageList($condition, $params->limit, $params->order);
        $count = Cost::where($condition)->count();
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        foreach ($list as &$item) {
            $item['tsg_code'] = $tsg_info['tsg_code'] . ' | ' . $tsg_info['tsg_name'];
        }
        unset($item);
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $cost_code = input('cost_code');
        $info = Cost::get(['cost_code' => $cost_code, 'tsg_code' => $this->adminInfo['tsg_code']]);
        if ($this->isPost) {
            $hide_code = input('hide_code');
            $data = [];
            $data['cost_type'] = input('cost_type');
            $data['cost_sour'] = input('cost_sour');
            $data['cost_money'] = input('cost_money');
            $data['cost_remark'] = input('cost_remark');

            if ($hide_code) {
                $log_type = CfLog::OP_TYPE_COST_SAVE;
                $is_success = Cost::update($data, ['cost_code' => $hide_code, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
            } else {
                if (Cost::where(['cost_code' => $cost_code])->count() > 0) {
                    $this->error(lang('cost_code_exist'));
                }
                if (preg_match("/[^0-9a-zA-Z]/", $cost_code)) {
                    $this->error(lang('cost_code_limit'));
                }
                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $data['cost_code'] = $cost_code;
                $log_type = CfLog::OP_TYPE_COST_ADD;
                $is_success = Cost::create($data)->result;
            }

            if ($is_success !== false) {
                CfLog::addLog($log_type, $this->_user_info, ['book_id' => 0, 'db1' => $cost_code ?: $hide_code, 'db2' => $data['seller_name']]);
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        $this->assign('info', $info);
        $this->assign('tsg_info', $tsg_info);
        $this->assign("cost_type_list", array("正常拨款", "赠送(钱)", "赠送(书)"));
        return view('edit');
    }

    public function dropAction()
    {
        $cost_code = input('cost_code');
        $mod_cost = d("Cost");
        $cost_info = $mod_cost->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!$cost_info) {
            $this->error(lang('not_found_data'));
        }
        $mod_destine_batch = d("DestineBatch");
        $destine_batch_info = $mod_destine_batch->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($destine_batch_info)) {
            $this->error("删除失败:已有预订批次使用此预算信息");
        }
        $mod_destine = d("Destine");
        $destine_info = $mod_destine->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($destine_info)) {
            $this->error("删除失败:已有预订信息使用此预算信息");
        }
        $mod_batch = d("Batch");
        $batch_info = $mod_batch->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($batch_info)) {
            $this->error("删除失败:已有验收批次使用此预算信息");
        }
        $mod_ys = d("Ys");
        $ys_info = $mod_ys->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($ys_info)) {
            $this->error("删除失败:已有验收信息使用此预算信息");
        }
        $mod_dck = d("Dck");
        $dck_info = $mod_dck->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($dck_info)) {
            $this->error("删除失败:已有馆藏信息使用此预算信息");
        }
        $is_success = $mod_cost->where("cost_code='$cost_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->delete();
        if ($is_success) {
            CfLog::addLog(CfLog::OP_TYPE_COST_DROP, $this->_user_info, array("db1" => $cost_info["cost_code"]));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败");
        }

    }

}
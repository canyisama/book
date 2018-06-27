<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Bookseller;
use app\admin\model\CfLog;
use app\admin\model\Cost;
use app\admin\model\Destine;
use app\admin\model\DestineBatch;
use app\admin\model\Tsg;
use app\admin\model\User;

class DestineBatchController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    public function indexAction()
    {
        $this->assign('destine_batch_curr', $this->adminInfo['destine_batch_curr']);
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

        $list = DestineBatch::getPageList($condition, $params->limit, $params->order);
        $count = DestineBatch::where($condition)->count();
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
            $hide_code = input('hide_code');
            $destine_batch_code = input('destine_batch_code');

            $data = [];
            $data['remark'] = input('remark');
            $data['cost_code'] = input('cost_code');
            $data['seller_code'] = input('seller_code');
            if ($hide_code) {
                // 更新
                $result = DestineBatch::update($data, ['destine_batch_code' => $hide_code, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_SAVE;
            } else {
                //添加
                $data['status'] = self::BATCH_STATUS_YD;
                $data['add_time'] = time();
                $data['add_user'] = $this->adminInfo['user_name'];
                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $data['destine_batch_code'] = $destine_batch_code;
                $result = DestineBatch::create($data)->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_ADD;
            }
            if ($result !== false) {
                CfLog::addLog($log_type, $this->adminInfo, ['db1' => $hide_code ?: $destine_batch_code]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $destine_batch_code = input('destine_batch_code');
        $info = DestineBatch::get(['destine_batch_code' => $destine_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']]);
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        $cost_list = Cost::where('tsg_code', $this->adminInfo['tsg_code'])->select();
        $bookseller_list = Bookseller::where('tsg_code', $this->adminInfo['tsg_code'])->select();

        $this->assign('info', $info);
        $this->assign("tsg_info", $tsg_info);
        $this->assign("cost_list", $cost_list);
        $this->assign("bookseller_list", $bookseller_list);
        return view('edit');
    }

    /**
     * 设置状态
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setstatusAction()
    {
        $status = input('state/d');
        $destine_batch_code = input('destine_batch_code');
        $condition = ['tsg_code' => $this->adminInfo['tsg_code'], 'destine_batch_code' => $destine_batch_code];

        $destine_batch_info = DestineBatch::where($condition)->find();
        if (empty($destine_batch_info)) {
            $this->error('设置状态失败,数据库未找到此批次,请刷新后再试!');
        }
        if ($destine_batch_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error('无权限设置其他馆批次!');
        }
        $status_list = $this->get_status_list();
        if (!isset($status_list[$status])) {
            $this->error('设置状态失败,无效的状态值!');
        }

        $data = array("status" => $status);
        $is_success = DestineBatch::update($data, $condition);
        if ($is_success !== false) {
            CfLog::addLog(CfLog::OP_TYPE_YD_BATCH_SET_STATUS, $this->adminInfo, ['db1' => $destine_batch_info['destine_batch_code']]);
            $this->success('设置状态成功!');
        } else {
            $this->error('设置状态失败!错误提示:更新数据库失败');
        }
    }

    private function get_status_list()
    {
        return array(self::BATCH_STATUS_YD => "预订状态", self::BATCH_STATUS_YANSHOU => "验收状态", self::BATCH_STATUS_FINISH => "完成状态");
    }

    /**
     * 设置默认批次
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setDefaultAction()
    {
        $destine_batch_code = input('destine_batch_code');
        $destine_batch_info = DestineBatch::where(['destine_batch_code' => $destine_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']])->find();
        $data = array("destine_batch_curr" => $destine_batch_info["destine_batch_code"]);
        $is_success = User::update($data, ['user_id' => $this->adminInfo['user_id']])->result;
        if ($is_success !== false) {
            $this->success("设为默认批次号成功！");
        } else {
            $this->error("设为默认批次号失败！");
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
        $destine_batch_code = input('destine_batch_code');
        $condition = ['destine_batch_code' => $destine_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']];
        $destine_batch_info = DestineBatch::where($condition)->find();

        if (!$destine_batch_info) {
            $this->error(lang('not_found_data'));
        }
        if ($destine_batch_info['tsg_code'] != $this->adminInfo['tsg_code']) {
            $this->error(lang('not_access_edit_data'));
        }

        $destine_info = Destine::where($condition)->find();
        if (!empty($destine_info)) {
            $this->error('当前批次存在预订数据,无法删除!');
        }
        $is_success = DestineBatch::where($condition)->delete();
        if ($is_success) {
            CfLog::addLog(CfLog::OP_TYPE_YD_BATCH_DROP, $this->adminInfo, ['db1' => $destine_batch_info['destine_batch_code']]);
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/27
 * Time: 10:23
 */

namespace app\admin\controller;


use app\admin\model\Bookseller;
use app\admin\model\Cost;
use app\admin\model\Qk;
use app\admin\model\QkBatch;
use app\admin\model\QkLog;
use app\admin\model\Tsg;
use app\admin\model\User;
use think\Exception;
use think\Lang;

class QkBatchController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;


    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'home/qk_batch.php']);
    }

    public function indexAction()
    {
        $this->assign('qk_batch_curr', $this->adminInfo['qk_batch_curr']);
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

        $list = QkBatch::getPageList($condition, $params->limit, $params->order);
        $count = QkBatch::where($condition)->count();
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
        if ($this->isPost) {
            try{
                $hide_code = input('hide_code');
                $qk_batch_code = input('qk_batch_code');

                $data = [];
                $data['remark'] = input('remark');
                $data['cost_code'] = input('cost_code');
                $data['seller_code'] = input('seller_code');
                if ($hide_code) {
                    // 更新
                    $result = QkBatch::update($data, ['qk_batch_code' => $hide_code, 'tsg_code' => $this->adminInfo['tsg_code']],true)->result;
                    $log_type = QkLog::OP_TYPE_YD_BATCH_SAVE;
                } else {
                    //添加
                    if (!$data["qk_batch_code"]) {
                        $this->error(\lang('qk_batch_code_required'));
                    }

                    if (preg_match("/[^0-9a-zA-Z]/", $data["qk_batch_code"])) {
                        $this->error(\lang('qk_batch_code_limit'));
                    }

                    if (!QkBatch::unique($qk_batch_code, $this->adminInfo["tsg_code"])) {
                        $this->error(lang("qk_batch_code_exist"));
                    }

                    $data['status'] = self::BATCH_STATUS_YD;
                    $data['add_time'] = time();
                    $data['add_user'] = $this->adminInfo['user_name'];
                    $data['tsg_code'] = $this->adminInfo['tsg_code'];
                    $data['qk_batch_code'] = $qk_batch_code;
                    $result = QkBatch::create($data,true)->result;
                    $log_type = QkLog::OP_TYPE_YD_BATCH_ADD;
                }
                if ($result !== false) {
                    QkLog::addLog($log_type, $this->adminInfo, ['db1' => $hide_code ?: $qk_batch_code]);
                    $this->success('保存成功!');
                } else {
                    $this->error('保存失败!');
                }
            }catch (Exception $e){
                $this->error($e->getMessage());
            }
        }

        $qk_batch_code = input('qk_batch_code');
        $info = QkBatch::get(['qk_batch_code' => $qk_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']]);
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        $cost_list = Cost::where('tsg_code', $this->adminInfo['tsg_code'])->select();
        $bookseller_list = Bookseller::where('tsg_code', $this->adminInfo['tsg_code'])->select();

        $this->assign('info', $info);
        $this->assign("tsg_info", $tsg_info);
        $this->assign("cost_list", $cost_list);
        $this->assign("bookseller_list", $bookseller_list);
        return view();
    }

    /**
     * 设置状态
     */
    public function setStateAction()
    {
        try{
            $status = input('state/d');
            $qk_batch_code = input('qk_batch_code');
            $condition = ['tsg_code' => $this->adminInfo['tsg_code'], 'qk_batch_code' => $qk_batch_code];

            $qk_batch_info = QkBatch::where($condition)->find();

            if (empty($qk_batch_info)) {
                $this->error('设置状态失败,数据库未找到此批次,请刷新后再试!');
            }
            if ($qk_batch_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error('无权限设置其他馆批次!');
            }
            $status_list = $this->get_status_list();
            if (!isset($status_list[$status])) {
                $this->error('设置状态失败,无效的状态值!');
            }

            $data = array("status" => $status);
            $is_success = QkBatch::update($data, $condition);
            if ($is_success !== false) {
                $this->success('设置状态成功!');
            } else {
                $this->error('设置状态失败!错误提示:更新数据库失败');
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }

    private function get_status_list()
    {
        return array(self::BATCH_STATUS_YD => "预订状态", self::BATCH_STATUS_YANSHOU => "验收状态", self::BATCH_STATUS_FINISH => "完成状态");
    }

    /**
     * 设置默认批次
     */
    public function setDefaultAction()
    {
        try{
            $qk_batch_code = input('qk_batch_code');
            $qk_batch_info = QkBatch::where(['qk_batch_code' => $qk_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']])->find();
            $data = array("qk_batch_curr" => $qk_batch_info["qk_batch_code"]);
            $is_success = User::update($data, ['user_id' => $this->adminInfo['user_id']])->result;
            if ($is_success !== false) {
                $this->success("设为默认批次号成功！");
            } else {
                $this->error("设为默认批次号失败！");
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
            $qk_batch_code = input('qk_batch_code');
            $condition = ['qk_batch_code' => $qk_batch_code, 'tsg_code' => $this->adminInfo['tsg_code']];
            $qk_batch_info = QkBatch::where($condition)->find();

            if (!$qk_batch_info) {
                $this->error(lang('not_found_data'));
            }
            if ($qk_batch_info['tsg_code'] != $this->adminInfo['tsg_code']) {
                $this->error(lang('not_access_edit_data'));
            }

            $qk_info = Qk::where($condition)->find();
            if (!empty($qk_info)) {
                $this->error('当前批次存在预订数据,无法删除!');
            }
            $is_success = QkBatch::where($condition)->delete();
            if ($is_success) {
                QkLog::addLog(QkLog::OP_TYPE_YD_BATCH_DROP, $this->adminInfo, ['db1' => $qk_batch_info['qk_batch_code']]);
                $this->success('删除成功！');
            } else {
                $this->error('删除失败！');
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }
}
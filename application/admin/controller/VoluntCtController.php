<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\CfLog;
use app\admin\model\DzLog;
use app\admin\model\Tsg;
use app\admin\model\VoluntCt;

class VoluntCtController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    /**
     * 义工评价类型
     */
    public function indexAction()
    {
        return view();
    }

    /**
     * 异步获取
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

        $list = VoluntCt::getPageList($condition, $params->limit, $params->order);
        $count = VoluntCt::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    /**
     * @return \think\response\View
     * @throws \think\exception\DbException
     */
    public function editAction()
    {
        $volunt_ct_id = input('volunt_ct_id/d');
        if ($this->isPost) {
            $data = [];
            $data['ct_name'] = input('ct_name');
            $data['ct_cnt'] = input('ct_cnt');
            $data['order_num'] = input('order_num');
            $mod_volunt_ct = d("Volunt_ct");
            if (!$mod_volunt_ct->unique($this->adminInfo['tsg_code'],$data["ct_name"], $volunt_ct_id)) {
                $this->error(l("评价类型名称已存在"));
            }

            if ($volunt_ct_id) {
                // 更新
                $result = VoluntCt::update($data, ['volunt_ct_id' => $volunt_ct_id, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_SAVE;
            } else {
                //添加
                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $result = VoluntCt::create($data)->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_ADD;
            }
            if ($result !== false) {
                CfLog::addLog($log_type, $this->adminInfo, ['db1' => $volunt_ct_id]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $info = VoluntCt::get(['volunt_ct_id' => $volunt_ct_id, 'tsg_code' => $this->adminInfo['tsg_code']]);
        $this->assign('info', $info);
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
        $volunt_ct_id = input('volunt_ct_id');
        $condition = ['volunt_ct_id' => $volunt_ct_id, 'tsg_code' => $this->adminInfo['tsg_code']];
        $volunt_ct_info = VoluntCt::where($condition)->find();

        if (!$volunt_ct_info) {
            $this->error(lang('not_found_data'));
        }
        if ($volunt_ct_info['tsg_code'] != $this->adminInfo['tsg_code']) {
            $this->error(lang('not_access_edit_data'));
        }

        $is_success = VoluntCt::where($condition)->delete();
        if ($is_success) {
            DzLog::addLog(DzLog::OP_TYPE_DZ_BATCH_DROP, $this->adminInfo, ['db1' => $volunt_ct_info['volunt_ct_id']]);
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

}
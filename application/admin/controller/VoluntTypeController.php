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
use app\admin\model\VoluntType;

class VoluntTypeController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    /**
     * 义工出勤类型
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

        $list = VoluntType::getPageList($condition, $params->limit, $params->order);
        $count = VoluntType::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    /**
     * 新增/编辑
     * @return \think\response\View
     * @throws \think\exception\DbException
     */
    public function editAction()
    {
        if ($this->isPost) {
            $volunt_type_id = input('volunt_type_id/d');
            $mod_volunt_type = d("Volunt_type");
            $data = [];
            $data['type_name'] = input('type_name');
            $data['order_num'] = input('order_num');

            if (!$mod_volunt_type->unique($this->adminInfo['tsg_code'], $data["type_name"], $volunt_type_id)) {
                $this->error('出勤类型名称已存在');
            }

            if ($volunt_type_id) {
                // 更新
                $result = VoluntType::update($data, ['volunt_type_id' => $volunt_type_id, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_SAVE;
            } else {
                //添加
                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $result = VoluntType::create($data)->result;
                $log_type = CfLog::OP_TYPE_YD_BATCH_ADD;
            }
            if ($result !== false) {
                CfLog::addLog($log_type, $this->adminInfo, ['db1' => $volunt_type_id]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $volunt_type_id = input('volunt_type_id');
        $info = VoluntType::get(['volunt_type_id' => $volunt_type_id, 'tsg_code' => $this->adminInfo['tsg_code']]);
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
        $volunt_type_id = input('volunt_type_id');
        $condition = ['volunt_type_id' => $volunt_type_id, 'tsg_code' => $this->adminInfo['tsg_code']];
        $volunt_type_info = VoluntType::where($condition)->find();

        if (!$volunt_type_info) {
            $this->error(lang('not_found_data'));
        }
        if ($volunt_type_info['tsg_code'] != $this->adminInfo['tsg_code']) {
            $this->error(lang('not_access_edit_data'));
        }

        $is_success = VoluntType::where($condition)->delete();
        if ($is_success) {
            DzLog::addLog(DzLog::OP_TYPE_DZ_BATCH_DROP, $this->adminInfo, ['db1' => $volunt_type_info['volunt_type_id']]);
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

}
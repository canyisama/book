<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/23
 * Time: 17:19
 */

namespace app\admin\controller;


use app\admin\model\Holiday;
use app\admin\model\LtLog;
use think\Exception;

/**
 * Class HolidayController
 * @package app\admin\controller
 * 假期控制器
 */
class HolidayController extends BaseController
{
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
                switch ($search['field']){
                    case 'date_beg' :
                        $condition[$search['field']] = ['>= time',$search['value']];
                        break;
                    case 'date_end' :
                        $condition[$search['field']] = ['<= time',$search['value']];
                        break;
                    default :
                        $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                        break;
                }
            }
        }

        $list = Holiday::getPageList($condition, $params->limit, $params->order);
        $count = Holiday::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @return \think\response\View
     * @throws \think\exception\DbException
     * 假期新增 ----------- 编辑
     */
    public function editAction()
    {
        $holiday_id = input('holiday_id/d');

        if ($this->request->isPost()) {
            try{
                $add_data = $this->request->post();
                $result = $this->validate($add_data, 'Holiday');

                if ($result !== true){
                    $this->error($result);
                }

                if ($add_data['holiday_id']){
                    $where = [
                        'holiday' => $add_data['holiday_id']
                    ];
                    $is_success = Holiday::update($add_data,$where,true)->result;
//                    $lt_log_type = 12;
                    $lt_log_type = LtLog::OP_TYPE_HOLIDAY_EDIT;
                }else{

                    $add_data['tsg_code'] = $this->adminInfo['tsg_code'];
                    $is_success = Holiday::create($add_data,true)->result;
                    $lt_log_type = LtLog::OP_TYPE_HOLIDAY_ADD;
                }

                if ($is_success === false) {
                    $this->error('更新失败');
                }

                $param = [
                    'op_desc' => '[#],假期名称:【' . $add_data["ho_name"].'】'
                ];

                $is_success = LtLog::addLog($lt_log_type, $this->adminInfo, $param);
                if ($is_success === false) {
                    $this->error('流通日志写入失败，请稍后重试');
                }
                $this->success('更新成功');
            }catch (Exception $e){
                $this->error($e->getMessage());
            }

        }

        $holiday_info = Holiday::get($holiday_id);
        $this->assign('info',$holiday_info);
        return view();
    }

    /**
     * 假期删除
     */
    public function dropAction()
    {
        try{
            $holiday_id = input('holiday_id/d');
            $holiday_info = Holiday::get($holiday_id);
            if (!$holiday_info) {
                $this->error(lang('not_found_data'));
            }

            if ($holiday_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }
            $is_success = $holiday_info -> delete();
            if ($is_success) {
                $param = [
                    'op_desc' => '[#],假期名称:【'.$holiday_info['ho_name'].'】'
                ];
                LtLog::addLog(LtLog::OP_TYPE_HOLIDAY_DROP, $this->adminInfo,$param);
                $this->success("删除成功");
            }
            $this->error('删除失败:更新数据库失败');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }
}
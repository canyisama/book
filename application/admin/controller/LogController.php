<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 14:50
 */

namespace app\admin\controller;


use app\admin\model\BmLog;
use app\admin\model\CfLog;
use app\admin\model\DcLog;
use app\admin\model\DzLog;
use app\admin\model\LtLog;
use app\admin\model\QkLog;
use app\admin\model\SysLog;

/**
 * Class LogController
 * @package app\admin\controller
 * 日志管理
 */
class LogController extends BaseController
{
    /**
     * @return \think\response\View
     * 图书流通日志管理
     */
    public function ltlogAction()
    {
        $status_lists = LtLog::getType();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    /**
     * 读者服务日志查询
     */
    public function dzlogAction()
    {
        $status_lists = DzLog::getType();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    /**
     * 采访日志查询
     */
    public function cflogAction()
    {
        $status_lists = CfLog::get_types();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    public function qklogAction()
    {
        $status_lists = QkLog::getTypes();
        $this->assign('status_lists',$status_lists);
        return view();
    }

    /**
     * 编目日志查询
     */
    public function bmlogAction()
    {
        $status_lists = BmLog::get_types();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    /**
     * 馆藏日志
     */
    public function dclogAction()
    {
        $status_lists = DcLog::get_types();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    public function syslogAction()
    {
        $status_lists = SysLog::get_types();
        $this->assign('status_lists', $status_lists);
        return view();
    }

    /**
     * 异步获取图书流通日志
     */
    public function getJsonListAction()
    {
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $source = input('source');
        $params = $this->getQueryParams();//分页,排序,查询参数
        $start_time = $end_time = '';
        if ($params->search) {
            foreach ($params->search as $search) {
                switch ($search['field']) {
                    case 'op_time_start':
                        $start_time = $search['value'];
                        break;
                    case 'op_time_end':
                        $end_time = $search['value'];
                        break;
                    case 'op_type':
                        $condition[$search['field']] = $search['value'];
                        break;
                    default :
                        $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                        break;
                }
            }
        }
        if ($start_time && $end_time) {
            $condition['op_time'] = ['between time', [$start_time, $end_time]];
        } elseif ($start_time) {
            $condition['op_time'] = ['> time', $start_time];
        } elseif ($end_time) {
            $condition['op_time'] = ['< time', $end_time];
        }

        switch ($source){
            case 'lt_log':
                $list = LtLog::getPageList($condition, $params->limit, $params->order);
                $count = LtLog::where($condition)->count();
                break;
            case 'qk_log':
                $list = QkLog::getPageList($condition, $params->limit, $params->order);
                $count = QkLog::where($condition)->count();
                break;
            case 'bm_log':
                $list = BmLog::getPageList($condition, $params->limit, $params->order);
                $count = BmLog::where($condition)->count();
                break;
            case 'cf_log':
                $list = CfLog::getPageList($condition, $params->limit, $params->order);
                $count = CfLog::where($condition)->count();
                break;
            case 'dz_log':
                $list = DzLog::getPageList($condition, $params->limit, $params->order);
                $count = DzLog::where($condition)->count();
                break;
            case 'dc_log':
                $list = DcLog::getPageList($condition, $params->limit, $params->order);
                $count = DcLog::where($condition)->count();
                break;
            case 'sys_log':
                $list = SysLog::getPageList($condition, $params->limit, $params->order);
                $count = SysLog::where($condition)->count();
                break;
            default :
                $list = [];
                $count = 0;

        }

        return $this->echoPageData($list, $count);
    }

}
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
use app\admin\model\DzType;
use app\admin\model\Tsg;
use think\Lang;

class DzTypeController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/dz_type.php']);
    }

    /**
     * 读者类型设置
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

        $list = DzType::getPageList($condition, $params->limit, $params->order);
        $count = DzType::where($condition)->count();
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
            $dz_type_code = input('dz_type_code');

            $data = array(
                'is_out_can' => input('is_out_can'),
                'dz_type_name' => input('dz_type_name'),
                'is_reser' => input('is_reser'),
                'valid_days' => input('valid_days'),
                'reser_max_days' => input('reser_max_days'),
                'max_lend_num' => input('max_lend_num'),
                'reser_hold_days' => input('reser_hold_days'),
                'dz_ple_money' => input('dz_ple_money'),
                'is_lend_reser' => input('is_lend_reser'),
                'is_inter' => input('is_inter'),
                'lend_reser_max_days' => input('lend_reser_max_days'),
                'inter_lend_num' => input('inter_lend_num'),
                'lend_reser_hold_days' => input('lend_reser_hold_days'),
                'gongben_fee' => input('gongben_fee'),
                'max_own_money' => input('max_own_money'),
                'serv_fee' => input('serv_fee'),
                'vr_stop_cnt' => input('vr_stop_cnt'),
                'ver_fee' => input('ver_fee'),
                'lend_money_limit' => input('lend_money_limit'),
            );
            if ($hide_code) {
                // 更新
                $result = DzType::update($data, ['dz_type_code' => $hide_code, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
                $log_type = DzLog::OP_TYPE_DZ_TYPE_EDIT;
            } else {
                //添加
                $mod_dz_type = d("Dz_type");
                if (!$mod_dz_type->unique($dz_type_code, $this->_user_info["tsg_code"])) {
                    $this->error(l("dz_type_code_exist"));
                }

                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $data['dz_type_code'] = $dz_type_code;
                $result = DzType::create($data)->result;
                $log_type = DzLog::OP_TYPE_DZ_TYPE_ADD;
            }
            if ($result !== false) {
                DzLog::addLog($log_type, $this->adminInfo, ['db1' => $hide_code ?: $dz_type_code]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $dz_type_code = input('dz_type_code');
        $info = DzType::get(['dz_type_code' => $dz_type_code, 'tsg_code' => $this->adminInfo['tsg_code']]);
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
        $dz_type_code = input('dz_type_code');
        $dz_type_info = DzType::where(['tsg_code' => $this->adminInfo['tsg_code'], 'dz_type_code' => $dz_type_code])->find();
        if (!$dz_type_info) {
            $this->error(lang('not_found_data'));
        }
        if ($dz_type_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }
        $dz_info = Dzgl::where(['tsg_code' => $this->adminInfo['tsg_code'], 'dz_type_code' => $dz_type_code])->find();
        if ($dz_info) {
            $this->error('此读者类型存在读者,无法删除');
        }
        $is_success = DzType::where(['tsg_code' => $this->adminInfo['tsg_code'], 'dz_type_code' => $dz_type_code])->delete();
        if ($is_success !== false) {
            DzLog::addLog(DzLog::OP_TYPE_DZ_TYPE_DROP, $this->adminInfo, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者类型-删除,读者类型:【{$dz_type_info["dz_type_code"]}|{$dz_type_info["dz_type_name"]}】"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败");
        }
    }

}
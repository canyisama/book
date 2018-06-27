<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/23
 * Time: 18:54
 */

namespace app\admin\controller;


use app\admin\model\DzType;
use app\admin\model\LtLog;
use app\admin\model\Ltrule;
use app\admin\model\Ltype;
use app\admin\model\TsgSite;
use think\Db;
use think\Exception;

class LtruleController extends BaseController
{
    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function indexAction()
    {
        $is_inter = input('is_inter/d') ?: 0;
        $tsg_code = $this->adminInfo['tsg_code'];
        $where = [
            'tsg_code'  => $tsg_code,
            'is_inter'  => $is_inter,
            'rule_type' => Ltrule::LT_IS_RULE_TYPE_BASE
        ];
        $fields = 'ltrule_id,is_close,rule_type,lend_num,lend_days,lose_mode,lose_type,lose_rate,dirty_mode,dirty_type,dirty_rate,renew_mode,renew_cnt,renew_days,out_max_fine,out_fine,remark';
        $ltrule_info = Ltrule::field($fields)->where($where)->find();

        if (empty($ltrule_info)) {
            $ltrule_info = Ltrule::addBaseRule($tsg_code, $is_inter);
        }

        $this->assign("info", $ltrule_info);
        $this->assign('is_inter',$is_inter);
        $this->assign_common();
        return view();
    }

    /**
     * 重定向至index
     */
    public function index_interAction()
    {
        $this->redirect('index',['is_inter'=>1]);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 图书流通公用规则模板
     */
    private function assign_common()
    {
        $tsg_code = $this->adminInfo['tsg_code'];
        $lose_type_list = Ltrule::getType('lose_type');
        $renew_mode_list = Ltrule::getType('renew_mode');
        $lose_mode_list = Ltrule::getType('lose_mode');
        $dirty_type_list = Ltrule::getType('dirty_type');
        $dirty_mode_list = Ltrule::getType('dirty_mode');
        $is_close_list = Ltrule::getType('is_close');
        $dz_type_code_list = DzType::getMap( 'dz_type_code', 'dz_type_name',$tsg_code);
        $ltype_code_list = Ltype::getMap('ltype_code', 'ltype_name',$tsg_code);
        $tsg_site_code_list = TsgSite::getMap( 'tsg_site_code', 'site_name',$tsg_code);
        $this->assign("lose_type_list", $lose_type_list);
        $this->assign("renew_mode_list", $renew_mode_list);
        $this->assign("lose_mode_list", $lose_mode_list);
        $this->assign("dirty_type_list", $dirty_type_list);
        $this->assign("dirty_mode_list", $dirty_mode_list);
        $this->assign("is_close_list", $is_close_list);
        $this->assign("dz_type_code_list", $dz_type_code_list);
        $this->assign("ltype_code_list", $ltype_code_list);
        $this->assign("tsg_site_code_list", $tsg_site_code_list);
    }

    /**
     * 异步获取---特殊流通规则
     */
    public function getJsonListAction()
    {
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $condition['rule_type'] = Ltrule::LT_IS_RULE_TYPE_EXT;
        $is_inter = input('is_inter/d')?:0;
        $condition['is_inter'] = $is_inter;


        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Ltrule::getPageList($condition, $params->limit, $params->order);
        $count = Ltrule::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 基础规则更新
     */
    public function editAction()
    {
        try{
            $ltrule_id = input('ltrule_id/d');
            $save_data = $this->request->post();
            if (!$ltrule_id){
                $this->error('数据传输错误，请重试');
            }
            if (!$save_data["lend_num"]) {
                $this->error('最大借书数量为空');
            }

            if (!$save_data["lend_days"]) {
                $this->error('最大借书天数为空');
            }
            $where = ['ltrule_id'=>$ltrule_id];
            $is_success = Ltrule::update($save_data,$where)->result;
            if ($is_success === false){
                $this->error('更新失败，请稍后再试');
            }
            $this->success('更新成功');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }



    /**
     * @return string|\think\response\View
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 流通扩展规则新增---编辑
     */
    public function extAction()
    {
        $is_inter = input('is_inter/d');
        $lt_rule_id = input('lt_rule_id/d');

        if (!$this->request->isPost()) {
            $lt_rule_info = Ltrule::get($lt_rule_id);
            if ($lt_rule_info){
                $lt_rule_info['tsg_site_code'] = explode(',', $lt_rule_info['tsg_site_code']);
            }
            $this->assign('info', $lt_rule_info);
            $this->assign_common();
            if ($is_inter === 1){
                return $this->view->fetch('ext_inter');
            }
            return view();
        }
        try{
            $tsg_code = $this->adminInfo['tsg_code'];

            $add_data = $this->request->post();

            if (!$add_data) {
                $this->error('新增数据为空');
            }

            if (!$add_data["lend_num"]) {
                $this->error('最大借书数量为空');
            }

            if (!$add_data["lend_days"]) {
                $this->error('最大借书天数为空');
            }

            $add_data["is_inter"] = $is_inter;

            if (!empty($add_data['tsg_site_code_all'])) {
                $add_data['tsg_site_code'] = '';
                unset($add_data['tsg_site_code_all']);
            } else if (!empty($add_data["tsg_site_code"])) {
                $add_data["tsg_site_code"] = implode(",", $add_data["tsg_site_code"]);
            }

            if ($is_inter == 1) {
                unset($add_data["dz_type_code"]);
            }


            if ($add_data['ltrule_id']) {
                //编辑
                if (!Ltrule::unique($tsg_code, $add_data["dz_type_code"], $add_data["ltype_code"], $is_inter, $add_data['ltrule_id'])) {
                    $this->error('此流通规则已存在');
                }
                $is_success = Ltrule::update($add_data, ['ltrule_id' => $add_data['ltrule_id']],true)->result;
//                $lt_log_type = 9;
                $lt_log_type = LtLog::OP_TYPE_LTRULE_EDIT;
            } else {
                //新增
                $add_data['tsg_code'] = $tsg_code;
                $add_data["rule_type"] = Ltrule::LT_IS_RULE_TYPE_EXT;
                if (!Ltrule::unique($tsg_code, $add_data["dz_type_code"], $add_data["ltype_code"], $is_inter)) {
                    $this->error('此流通规则已存在');
                }
                $is_success = Ltrule::create($add_data,true)->result;
//                $lt_log_type = 8;
                $lt_log_type = LtLog::OP_TYPE_LTRULE_ADD;
            }
            if ($is_success === false) {
                Db::rollback();
                $this->error('新增失败！错误提示:' . $is_success);
            }
            Db::commit();
            $param = [
                'db1' => $is_success,
                'op_desc' => '[#],流通规则ID：【' . $add_data['ltrule_id'] . '】' ?: Ltrule::getLastInsID() .'】'
            ];
            $is_success = LtLog::addlog($lt_log_type, $this->adminInfo, $param);
            if ($is_success === false) {
                $this->success('更新成功,日志写入失败');
            }
            $this->success('更新成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    /**
     * 删除规则
     */
    public function dropAction()
    {
        try{
            $ltrule_id = input('ltrule_id/d');
            $ltrule_info = Ltrule::get($ltrule_id);

            if (!$ltrule_info) {
                $this->error(lang('not_found_data'));
            }

            if ($ltrule_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }

            $is_success = $ltrule_info->delete();

            if ($is_success) {
                $param = [
                    "db1" => $ltrule_id,
                    "op_desc" => "[#],流通规则ID:【".$ltrule_id."】"
                ];
                LtLog::addlog(LtLog::OP_TYPE_LTRULE_DROP, $this->adminInfo,$param);
                $this->success("删除成功！");
            }
            $this->error('删除失败！错误提示:'.$ltrule_info->getError());
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }


    /**
     * 设置规则禁用--------  启用
     */
    public function isCloseAction()
    {
        try{
            $ltrule_id = input('ltrule_id/d');
            $is_close = input('is_close/d');
            $ltrule_info = Ltrule::get($ltrule_id);

            if (!$ltrule_info) {
                $this->error(lang('not_found_data'));
            }

            if ($ltrule_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }

            $set_txt = ($is_close == 1 ? "禁用" : "启用");
            $save_data = ['is_close'=>$is_close];
            $where = ['ltrule_id'=>$ltrule_id];
            $is_success = Ltrule::update($save_data,$where)->result;

            if ($is_success === false) {
                $this->error($set_txt . '规则失败');
            }
            $this->success($set_txt . '规则成功');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }

}
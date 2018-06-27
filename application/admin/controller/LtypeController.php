<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/23
 * Time: 11:18
 */

namespace app\admin\controller;


use app\admin\model\Dck;
use app\admin\model\LtLog;
use app\admin\model\Ltype;
use think\Db;
use think\Exception;

/**
 * Class LtypeController
 * @package app\admin\controller
 * 流通类型
 */
class LtypeController extends BaseController
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
                    $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Ltype::getPageList($condition, $params->limit, $params->order);
        $count = Ltype::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @return \think\response\View
     * @throws \think\exception\DbException
     * 图书类型  新增------编辑
     */
    public function editAction()
    {
        $ltype_code = input('ltype_code');
        if ($this->request->isPost()) {
            try{
                $add_data = $this->request->post();
                $result = $this->validate($add_data, 'Ltype');

                if ($result !== true){
                    $this->error($result);
                }
                Db::startTrans();
                if ($add_data['hide_code']){
                    $where = [
                        'ltype_code' => $add_data['hide_code'],
                        'tsg_code'  => $this->adminInfo['tsg_code']
                    ];
                    $is_success = Ltype::update($add_data,$where,true)->result;
//                    $lt_log_type = 6;
                    $lt_log_type = LtLog::OP_TYPE_LTYPE_EDIT;
                }else{
                    if (!Ltype::unique($add_data["ltype_code"], $this->adminInfo["tsg_code"])) {
                        Db::rollback();
                        $this->error('图书流通类型代码已存在');
                    }
                    $add_data['tsg_code'] = $this->adminInfo['tsg_code'];
                    $is_success = Ltype::create($add_data,true)->result;
                    $lt_log_type = LtLog::OP_TYPE_LTYPE_ADD;
                }
                if ($is_success === false) {
                    Db::rollback();
                    $this->error('更新失败');
                }
                $param = [
                    'op_desc' => '[#],图书流通类型:[' . $add_data["ltype_code"] . '|' . $add_data["ltype_name"] . ']'
                ];

                $is_success = LtLog::addLog($lt_log_type, $this->adminInfo, $param);
                if ($is_success === false) {
                    Db::rollback();
                    $this->error('流通日志写入失败，请稍后重试');
                }
                Db::commit();
                $this->success('更新成功');
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }

        $ltype_info = Ltype::get(['ltype_code'=>$ltype_code,'tsg_code'=>$this->adminInfo['tsg_code']]);
        $this->assign('info',$ltype_info);
        return view();
    }

    /**
     *  删除流通类型
     */
    public function dropAction()
    {
        try{
            $ltype_code = input('post.ltype_code');
            $tsg_code = input('post.tsg_code');
            $where = [
                'tsg_code' => $tsg_code,
                'ltype_code' => $ltype_code
            ];
            $ltype_info = Ltype::get($where);

            if (!$ltype_info) {
                $this->error(lang('not_found_data'));
            }

            if ($ltype_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }

            $dck_info = Dck::field('dck_id')->where(['lt_type'=>$ltype_code])->find();

            if ($dck_info) {
                $this->error('此流通类型正被馆藏库使用,无法删除');
            }

            $is_success = $ltype_info->delete();
            if ($is_success) {
                $param = [
                    'op_desc' => '[#],图书流通类型:【'.$ltype_code.'|'.$ltype_info['ltype_name'].'】'
                ];
                LtLog::addLog(7, $this->adminInfo,$param);
                $this->success('删除成功！');
            }
            $this->error("删除失败！错误提示:".$is_success);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }

    }

}
<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 9:20
 */

namespace app\admin\controller;


use app\admin\model\Reser;
use think\Db;
use think\Exception;

/**
 * Class ReserController
 * @package app\admin\controller
 * 图书预约 --- 预约管理
 */
class ReserController extends BaseController
{
    public function indexAction()
    {
        $status_lists = Reser::getType();
        $this->assign('status_lists',$status_lists);
        return view();
    }

    /**
     * 异步获取
     */
    public function getJsonListAction()
    {
        Reser::clearTimeout();
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                if ($search['field'] == 'reser_time') {
                    $condition[$search['field']] = ['between time', [$search['value'], $search['value'] . '+1 day']];
                } else {
                    $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                }
            }
        }
        $list = Reser::getPageList($condition, $params->limit, $params->order);
        $count = Reser::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 异步获取当前预约信息
     */
    public function getReserAction()
    {
        Reser::clearTimeout();
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        $dz_code = input('dz_code/d') ?: 0;
        $condition['dz_code'] = $dz_code;
        $condition['reser_status'] = ['in',[1,2,3,4]];
        $list = Reser::getPageList($condition, $params->limit, $params->order);
        $count = Reser::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @throws \think\exception\DbException
     * 取消预约
     */
    public function dropAction()
    {
        $reserve_id = input('post.reserve_id/d');
        if (empty($reserve_id)){
            $this->error('id为空');
        }
        Db::startTrans();
        $reserve_model = new Reser();
        $is_success = $reserve_model->dropReserve($reserve_id,$this->adminInfo['tsg_code']);
        if ($is_success === false){
            Db::rollback();
            $this->error($reserve_model->getError());
        }
        Db::commit();
        $this->success('取消预约成功');
    }

    /**
     * @return \think\response\View
     * 预约主页面
     */
    public function opageAction()
    {
        $condition = [
            'isbn' => '标准编号',
            'title'=> '题名',
            'firstauthor'=> '著者',
            'clc'  => '分类号',
            'publisher'  => '出版社',
            'subject'  => '主题词',
            'barcode'  => '图书条码',
            'calino'  => '索书号'
        ];
        $this->assign('reserve_type',2);
        $this->assign('condition_lists',$condition);
        return view();
    }

    /**
     * 预约
     */
    public function reserAction()
    {
        try{
            $book_id = input('get.book_id/d');
            $dz_code = input('get.dz_code/d');
            Db::startTrans();
            $reserve_model = new Reser();
            $is_success = $reserve_model ->bookReserve($dz_code,$this->adminInfo['tsg_code'],$book_id);

            if ($is_success === false){
                Db::rollback();
                $this->error($reserve_model->getError());
            }
            Db::commit();
            $this->success('预约成功，预约到书后保留天数:'.$is_success);
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取预约信息
     */
    public function getinfoAction()
    {
        $reser_id = input('pkey_id/d',0);
        $fields = "reser_id,real_name,unit_name,phone_mob,email,title,must_time,barcode";
        $where = [
            'tsg_code' => $this->adminInfo['tsg_code'],
            'reser_id' => $reser_id
        ];
        $reser_info = Reser::field($fields)->where($where)->find();

        if (!$reser_info) {
            $this->error(l('not_found_data'));
        }

        $reser_info["mustdate"] = $reser_info["must_time"];
        $reser_info['dzname'] = $reser_info['real_name'];
        $reser_info['unitname'] = $reser_info['unit_name'];
        $this->success('','',$reser_info);
    }
}
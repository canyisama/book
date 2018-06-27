<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 10:24
 */

namespace app\admin\controller;


use app\admin\model\Book;
use app\admin\model\Dck;
use app\admin\model\Dzgl;
use app\admin\model\DzType;
use app\admin\model\LendReser;
use think\Db;
use think\Exception;

/**
 * Class LendReserController
 * @package app\admin\controller
 * 图书预借 ---预借管理
 */
class LendReserController extends BaseController
{
    public function indexAction()
    {
        $status_lists = LendReser::getType();
        $this->assign('status_lists',$status_lists);
        return view();
    }

    /**
     * 异步获取
     */
    public function getJsonListAction()
    {
        LendReser::clearTimeout();
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                if($search['field'] == 'lend_reser_time') {
                    $condition[$search['field']] = ['between time',[$search['value'],$search['value'].'+1 day']];
                }else{
                    $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                }
            }
        }

        $list = LendReser::getPageList($condition, $params->limit, $params->order);
        $count = LendReser::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 异步获取当前预借信息
     */
    public function getLendReserAction()
    {
        LendReser::clearTimeout();
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        $dz_code = input('dz_code/d') ?: 0;
        $condition['dz_code'] = $dz_code;
        $condition['lend_reser_status'] = ['in',[1,2]];
        $list = LendReser::getPageList($condition, $params->limit, $params->order);
        $count = LendReser::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 异步获取图书信息
     */

    public function getBookJsonListAction()
    {
        //$is_search 0---不查
        //           1---预借界面图书条件查询
        //           2---预约界面图书条件查询
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $type = input('type/d') ?: 0;
        $params = $this->getQueryParams();//分页,排序,查询参数

            $condition_type = input('condition') ?: 'book_id';
            $condition_value = input('condition_value') ?: 'null';
            if ($condition_type == 'barcode' || $condition_type == 'calino'){
                $field = 'barcode,book_id,status,calino,dck_id';
                if ($condition_type == 'barcode'){
                    $dck_info = Dck::field($field)->where(['barcode'=>$condition_value])->select();
                }else{
                    $dck_info = Dck::field($field)->where(['calino'=>['like','%'.$condition_value.'%']])->select();
                }
                $book_ids =[];
                foreach ($dck_info as $key => $value){
                    $book_ids[] = $value['book_id'];
                }

                $condition['book_id'] = ['in',$book_ids];
            }else{
                $condition[$condition_type] = ['like','%'.$condition_value.'%'];
            }
//        return dump($condition_value);
        if ($type === 2){
            $list = Book::getPageList($condition, $params->limit, $params->order);
        }else if ($type === 1){
            $list = Book::with('dck')->where($condition)->limit($params->limit)->order($params->order)->select();
            if (!empty($list) && $condition_type == 'barcode'){
                foreach ($dck_info as $key => $value){
                    $list[$key]['barcode'] = $value->barcode;
                    $list[$key]['status'] = $value->status;
                    $list[$key]['calino'] = $value->calino;
                    $list[$key]['dck_id'] = $value->dck_id;
                }
            }else{
                foreach ($list as $key => $value){
                    foreach ($value->dck as $item){
                        $list[$key]['barcode'] = $item->barcode;
                        $list[$key]['status'] = $item->status;
                        $list[$key]['calino'] = $item->calino;
                        $list[$key]['dck_id'] = $item->dck_id;
                    }
                }
            }

        }else{
                $list = [];
        }
        $count = Book::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @throws \think\exception\DbException
     * 取消预借
     */
    public function dropAction()
    {
        $reserve_id = input('post.reserve_id/d');
        if (empty($reserve_id)){
            $this->error('id为空');
        }
        Db::startTrans();
        $reserve_model = new LendReser();
        $is_success = $reserve_model->dropLendReserve($reserve_id,$this->adminInfo['tsg_code']);
        if ($is_success === false){
            Db::rollback();
            $this->error($reserve_model->getError());
        }
        Db::commit();
        $this->success('取消预借成功');
    }

    /**
     * @return \think\response\View
     * 预借主页面
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
        $this->assign('reserve_type',1);
        $this->assign('condition_lists',$condition);
        return view();
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取预借|预约读者信息
     */
    public function getDzInfoAction()
    {
        $type = input('get.type/d');
        $dz_code = input('get.dz_code/d');
        if (empty($dz_code)){
            $this->error('读者证号为空');
        }


        $field = 'dz_code,portrait,real_name,unit_name,dz_status,dz_type_code,end_time';
        $dz_info = Dzgl::field($field)->where(['dz_code' => $dz_code])->find();
        if (!$dz_info) {
            $this->error('无此读者信息');
        }

        $dz_info['portrait'] = get_img_full_path($dz_info['portrait']);
        $tsg_code = $this->adminInfo['tsg_code'];
        $where = [
            'tsg_code' => $tsg_code,
            'dz_type_code' => $dz_info['dz_type_code']
        ];
        $field = 'dz_type_name';
        if ($type === 2) {
            $field .= ',is_reser,reser_max_days,reser_hold_days';
        } else {
            $field .= ',is_lend_reser,lend_reser_max_days,lend_reser_hold_days';
        }
        $dz_type_info = DzType::field($field)->where($where)->find();
        if (!$dz_type_info) {
            $this->error('此读者的类型无效');
        }
        $dz_info['dz_type_code'] = $dz_info['dz_type_code'] . '|' . $dz_type_info['dz_type_name'];
        if ($type === 2) {
            $dz_info['is_reserve'] = $dz_type_info['is_reser'];
            $dz_info['max_days'] = $dz_type_info['reser_max_days'];
            $dz_info['hold_days'] = $dz_type_info['reser_hold_days'];
        } else {
            $dz_info['is_reserve'] = $dz_type_info['is_lend_reser'];
            $dz_info['max_days'] = $dz_type_info['lend_reser_max_days'];
            $dz_info['hold_days'] = $dz_type_info['lend_reser_hold_days'];
        }

        $this->success('获取读者信息成功','',$dz_info);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取书籍显示信息
     */
    public function getDckBookInfoAction()
    {
        $condition = input('get.condition');
        $condition_value = input('get.condition_value');
        $where = [];
        $field_dck = 'barcode,calino,price,status,book_id,dck_id';
        $field_book = 'isbn,title,publisher,pubdate,firstauthor,is_verify,clc,book_id,price_ms';

        if ($condition_value && ($condition == 'barcode' || $condition == 'calino')) {
            if ($condition == 'barcode'){
                $where[$condition] = $condition_value;
            }else{
                $where[$condition] = ['like','%'.$condition_value.'%'];
            }

            $dck_info = Dck::field($field_dck)->where($where)->find();
            if (!$dck_info) {
                $this->error('无' . $condition_value . '对应的图书馆藏信息');
            }
            $book_id = $dck_info['book_id'];
            $book_info = Book::field($field_book)->where(['book_id' => $book_id])->find();
            if (!$book_info) {
                $this->error('无' . $condition_value . '对应的图书书目信息');
            }

        } else {
            $where[$condition] = ['like','%'.$condition_value.'%'];
            $book_info = Book::field($field_book)->where($where)->find();
            if (!$book_info) {
                $this->error('无' . $condition_value . '对应的图书书目信息');
            }
        }
        $this->success('获取图书信息成功','',$book_info);
    }

    /**
     * 预借
     */
    public function lendReserAction()
    {
        try{
            $dck_id = input('get.dck_id/d');
            $dz_code = input('get.dz_code/d');

            $reserve_model = new LendReser();
            Db::startTrans();
            $is_success = $reserve_model ->bookLendReserve($dz_code,$dck_id);
            if ($is_success === false){
                Db::rollback();
                $this->error($reserve_model->getError());
            }
            Db::commit();
            $this->success('预借成功，预借最多保留天数:'.$is_success);
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }
}
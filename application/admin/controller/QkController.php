<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/27
 * Time: 11:40
 */

namespace app\admin\controller;


use app\admin\model\Book;
use app\admin\model\Dck;
use app\admin\model\JdbmCnf;
use app\admin\model\Log;
use app\admin\model\Mt;
use app\admin\model\Qk;
use app\admin\model\QkBatch;
use app\admin\model\QkCycle;
use app\admin\model\QkLog;
use app\admin\model\QkRel;
use app\admin\model\Tsg;
use app\admin\model\Upload;
use app\admin\model\User;
use think\Db;
use think\Exception;
use think\Lang;

class QkController extends BaseController
{
    const IMPORT_TASK_NAME = "qk_import";
    const STATUS_YD = 1;
    const STATUS_TD = 2;

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'home/qk.php']);
    }

    /**
     * 期刊预订重定向至图书页面
     */
    public function frameworkAction()
    {
        //return $this->redirect('Catalog/index', ['source' => 'qk', 'curval' => 2]);
        return $this->redirect('book/index', ['source' => 'qk']);
    }

    /**
     * 期刊装订
     */
    public function qk_frameworkAction()
    {
        return $this->redirect('book/index',['source' => 'qk_zd']);
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 期刊预订管理页面
     */
    public function qkyd_manAction()
    {
        $source = input('source') ?: 'qk_man';
        $qk_batch_list = QkBatch::where('tsg_code', $this->adminInfo['tsg_code'])->select();
        $this->assign('qk_batch_list', $qk_batch_list);
        $this->assign('source',$source);
        return view();
    }


    /**
     * 按批次验收
     */
    public function ys_batch_zdAction()
    {
        return $this->redirect('qkyd_man',['source'=>'qk_ys']);
    }

    /**
     * 按批次装订
     */
    public function zdbatchAction()
    {
        return $this->redirect('qkyd_man',['source'=>'qk_zd']);
    }

    /**
     *
     * 期刊预订显示
     */
    public function qklistAction()
    {
        $book_id = input('book_id');
        $condition = array();
        $condition["book_id"] = array("eq", $book_id);
        $condition["dt"] = array("eq", Dck::DT_TYPE_QK);
        if (cookie('show_all_tsg') != 1) {
            $condition['tsg_code'] = $this->adminInfo['tsg_code'];
        }

        $fields = "dck_id,book_id,barcode,price,price_sum,tsg_code,calino,is_close,add_time";
        $params = $this->getQueryParams();//分页,排序,查询参数

        $list = Dck::getPageList($condition, $params->limit, $params->order,$fields);
        $count = Dck::where($condition)->count();

        $dck_cnt_all = 0;
        $dck_cnt_self = 0;
        foreach ($list as $item ) {
            $dck_cnt_all++;
            if ($item["tsg_code"] == $this->adminInfo["tsg_code"]) {
                $dck_cnt_self++;
            }
        }
        return $this->echoPageData($list, $count,['dck_cnt_all'=>$dck_cnt_all,'dck_cnt_self'=>$dck_cnt_self]);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 期刊异步获取数据
     */
    public function getJsonListAction()
    {
        $condition = [];
        $book_condition = [];

        //type为1时为预订界面管理
        $source = input('source') ?: '';

        if($source == 'qk_man' || $source == 'qk_ys'){
            $condition['tsg_code'] = $this->adminInfo['tsg_code'];
            $source == 'qk_ys' ? $condition['status'] = 1 : null;
        }else{
            // 直接预订 => 设置了 cooke show_all_tsg 时查询所有分馆
            if (cookie('show_all_tsg') != 1) {
                $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
            }
            if (input('book_id') || input('book_id/d') === 0){
                $condition['book_id'] = input('book_id/d');
            }
        }

        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                switch ($search['field']) {
                    case 'qk_batch_code':
                        $condition['qk_batch_code'] = $search['value'];
                        break;
                    case 'status':
                        $condition['status'] = $search['value'];
                        break;
                    default:
                        $book_condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                        break;
                }
            }
        }
        if ($book_condition) {
            $book_list1 = Book::where($book_condition)->field('book_id')->select();
            $book_search_ids = [];
            foreach ($book_list1 as $i) {
                $book_search_ids[] = $i['book_id'];
            }

            if ($book_search_ids){
                $condition['book_id'] = ['in', $book_search_ids];
            }else{
                $condition['book_id'] = 0;
            }
        }
//        $list = Qk::getPageList($condition, $params->limit, $params->order);
        $list = Qk::with('qkRel')->where($condition)->limit($params->limit)->order($params->order)->select();

        $count = Qk::where($condition)->count();

        $book_ids = $tsg_code_list = [];
        foreach ($list as $item) {
            $book_ids[] = $item['book_id'];
            $tsg_code_list[] = $item['tsg_code'];
        }
        $tsg_list = Tsg::where(['tsg_code' => ['in', $tsg_code_list]])->select();
        $tsg_list = array_under_reset($tsg_list, 'tsg_code');
        $book_list = Book::where(['book_id' => ['in', $book_ids]])->select();
        $book_list = array_under_reset($book_list, 'book_id');

        foreach ($list as &$item) {
            $tsg = $tsg_list[$item['tsg_code']];
            $book = $book_list[$item['book_id']];
            $item['clc'] = $book['clc'];
            $item['title'] = $book['title'];
            $item['tsg_code'] = $tsg['tsg_code'] . ' | ' . $tsg['tsg_name'];
            $item['publisher'] = $book['publisher'];
            $item['ys_cnt'] = $item->qkRel()->sum('ys_book_cnt');
            unset($item->qkRel);
        }
        unset($item);

        return $this->echoPageData($list, $count);
    }


    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 编辑页面公共渲染
     */
    private function assign_common()
    {
        $tsg_info = Tsg::field("tsg_code,tsg_name")->where(['tsg_code'=>$this->adminInfo['tsg_code']])->find();
        $user_info = User::field("qk_batch_curr")->where(['user_id'=>$this->adminInfo['user_id']])->find();

        if (empty($user_info)) {
            $this->alertMsg('未设定默认预订批次!');
        }

        $where = [
            'qk_batch_code' => $this->adminInfo['qk_batch_curr'],
            'tsg_code' => $this->adminInfo['tsg_code']
        ];

        $batch_info = QkBatch::field("qk_batch_code,seller_code,cost_code,status")->where($where)->find();

        if (empty($batch_info)) {
            $this->alertMsg('默认预订批次数据库不存在,请重新设定!');
        }

        if ($batch_info["status"] != QkBatchController::BATCH_STATUS_YD) {
            $this->alertMsg('默认预订批次必须为预订状态才可编辑预订信息!');
        }

        $status_list = self::get_status_list();
        $this->assign("status_list", $status_list);

        $where = [
            'cnf_type' => '图书来源',
            'tsg_code' => $this->adminInfo['tsg_code']
        ];
        $ly_list = JdbmCnf::field("remark,cnf_val")->where($where)->select();
        $this->assign("ly_list", $ly_list);
        $qk_cycle_list = QkCycle::get_list($this->adminInfo["tsg_code"]);
        $this->assign("qk_cycle_list", $qk_cycle_list);
        $this->assign("tsg_info", $tsg_info);
        $this->assign("user_info", $user_info);
        $this->assign("batch_info", $batch_info);
        $year_list = self::get_year_list();
        $this->assign("year_list", $year_list);
    }

    public static function get_status_list()
    {
        return array(self::STATUS_YD => "预订", self::STATUS_TD => "退订");
    }

    public static function get_year_list()
    {
        $year = date("Y");
        $year_beg = $year - 5;
        $year_end = $year + 5;

        for ($list = array(); $year_beg <= $year_end; $year_beg++) {
            $list[] = $year_beg;
        }

        return $list;
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 添加期刊预订
     */
    public function addAction()
    {
        $book_id = input('book_id/d');
        //reg

        if (!$this->isPost) {
            $qk_info = array("order_year" => date("Y"));
            $this->assign("info", $qk_info);
            $this->assign('book_id', $book_id);
            $this->assign_common();
            return view('edit');
        }
        try{
            $save_data = input('post.');

            if (!$save_data) {
                $this->error('保存失败！数据为空');
            }

            $user_info = User::field("qk_batch_curr")->where(['user_id'=>$this->adminInfo['user_id']])->find();

            if (empty($user_info)) {
                $this->error('请先设定默认预订批次');
            }

            $where = [
                'qk_batch_code' => $this->adminInfo['qk_batch_curr'],
                'tsg_code' => $this->adminInfo['tsg_code']
            ];

            $batch_info = QkBatch::field("qk_batch_code,seller_code,cost_code,status")->where($where)->find();

            if (empty($batch_info)) {
                $this->error('无效默认预订批次,请重新设定默认预订批次!');
            }

            if ($batch_info["status"] != QkBatchController::BATCH_STATUS_YD) {
                $this->error('默认预订批次必须为预订状态,请重新设定默认预订批次!');
            }

            if (!$save_data["order_year"]) {
                $this->error(lang('order_year_required'));
            }

            if (!$save_data["price"]) {
                $this->error('price_required');
            }

            if (!$save_data["year_price"]) {
                $this->error('year_price_required');
            }

            if (!$save_data["qk_cnt"]) {
                $this->error('qk_cnt_required');
            }

            if (!$save_data["book_cnt"]) {
                $this->error('book_cnt_required');
            }

            $save_data["qk_batch_code"] = $batch_info["qk_batch_code"];
            $save_data["seller_code"] = $batch_info["seller_code"];
            $save_data["cost_code"] = $batch_info["cost_code"];
            $save_data["add_time"] = mstrtotime(date("Y-m-d"));
            $save_data["add_user"] = $this->adminInfo["user_name"];
            $save_data["book_id"] = $book_id;
            $save_data["tsg_code"] = $this->adminInfo["tsg_code"];
            $save_data["status"] = self::STATUS_YD;


            Db::startTrans();
            $qk_model = Qk::create($save_data);
            $qk_id = $qk_model['qk_id'];

            if ($qk_id === false) {
                Db::rollback();
                $this->error('新增失败:插入数据库失败');
            }

            $rel_data_list = Qk::get_rel_data($save_data, $qk_id);
            $qk_rel_model = new QkRel();
            $is_success = $qk_rel_model->insertAll($rel_data_list);

            if ($is_success === false) {
                Db::rollback();
                $this->error('新增失败！期刊预订相关数据增加失败!');
            }

            QkLog::addlog(QkLog::OP_TYPE_YD_ADD, $this->adminInfo, array("book_id" => $save_data["book_id"], "db1" => $qk_id));
            Db::commit();
            $this->success('期刊预订新增成功！');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }


    /**
     * @return \think\response\View
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 期刊预订编辑
     */
    public function editAction()
    {
        $qk_id = input('qk_id/d');
        $qk_info = Qk::get($qk_id);

        if (!$this->isPost) {


            if (!$qk_info) {
                $this->alertMsg('数据库未找到此数据！');
            }

            if ($qk_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->alertMsg('无法编辑其他馆期刊！');
            }
            $this->assign_common();
            $this->assign("info", $qk_info);
            return view('edit');
        }
        try{
            if (!$qk_info) {
                $this->error('数据库未找到此数据');
            }

            if ($qk_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error('无法编辑其他馆期刊');
            }

            $save_data = input('post.');

            if (!$save_data) {
                $this->error('保存失败！数据为空');
            }

            if (!$save_data["order_year"]) {
                $this->error(lang('order_year_required'));
            }

            if (!$save_data["price"]) {
                $this->error(l('price_required'));
            }

            if (!$save_data["year_price"]) {
                $this->error(l('year_price_required'));
            }

            if (!$save_data["qk_cnt"]) {
                $this->error(l('qk_cnt_required'));
            }

            if (!$save_data["book_cnt"]) {
                $this->error(l('book_cnt_required'));
            }

            unset($save_data["qk_id"]);


            Db::startTrans();
            $is_success = Qk::update($save_data,['qk_id'=>$qk_id],true)->result;

            if ($is_success === false) {
                Db::rollback();
                $this->error('保存失败!');
            }

            $max_pos = QkRel::where("qk_id=$qk_id")->max("pos");

            if ($max_pos != $save_data["qk_cnt"]) {
                if ($save_data["qk_cnt"] < $max_pos) {
                    $where = [
                        'qk_id' => $qk_id,
                        'pos' => $save_data['qk_cnt']
                    ];
                    $is_success = QkRel::where($where)->delete();

                    if ($is_success === false) {
                        Db::rollback();
                        $this->error("更新期刊预订期数数据失败！");
                        return NULL;
                    }
                }
                else {
                    $rel_data_list = Qk::get_rel_data($save_data, $qk_id);
                    $rel_data_list = array_slice($rel_data_list, $max_pos);
                    $qk_rel_model = new QkRel();
                    $is_success = $qk_rel_model->saveAll($rel_data_list);

                    if ($is_success === false) {
                        Db::rollback();
                        $this->error('更新期刊预订期数数据失败!');
                    }
                }
            }

            QkLog::addlog(QkLog::OP_TYPE_YD_SAVE, $this->adminInfo, array("book_id" => $qk_info["book_id"], "db1" => $qk_id));

            Db::commit();
            $this->success('更新成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 退订/重订
     * @throws \think\exception\DbException
     */
    public function setStateAction()
    {
        $state = input('status/d');
        $qk_id = input('qk_id/d');

        $qk_info = Qk::get($qk_id);
        if (!$qk_info) {
            $this->error(lang('not_found_data'));
        }
        $data = array("status" => $state);
        $is_success = Qk::update($data, ['qk_id' => $qk_id]);
        if ($is_success) {
            QkLog::addLog($state == 1 ? QkLog::OP_TYPE_YD_CD : QkLog::OP_TYPE_YD_TD, $this->adminInfo, ['book_id' => $qk_info['book_id'], 'db1' => $qk_id]);
            $this->success($state == 1 ? '重订成功！' : '退订成功！');
        } else {
            $this->error($state == 1 ? '重订失败！' : '退订失败！');
        }
    }


    /**
     * 删除预定
     */
    public function dropAction()
    {
        try{
            $qk_id = input('qk_id/d');
            $qk_info = Qk::get($qk_id);
            if (!$qk_info) {
                $this->error(lang('not_found_data'));
            }

            $where = [
                'qk_id' => $qk_id,
                'is_ys' => 1
            ];
            $rel_info = QkRel::field("qk_rel_id")->where($where)->find();

            if (!empty($rel_info)) {
                $this->error('本预订数据存在验收信息,无法删除！');
            }

            Db::startTrans();
            $is_success = Qk::where(['tsg_code' => $this->adminInfo['tsg_code'], 'qk_id' => $qk_id])->delete();

            if ($is_success === false) {
                Db::rollback();
                $this->error("删除失败！");
            }

            $is_success = QkRel::where(['qk_id'=>$qk_id])->delete();

            if ($is_success) {
                QkLog::addLog(QkLog::OP_TYPE_YD_DROP, $this->adminInfo, ['book_id' => $qk_info['book_id'], 'db1' => $qk_id]);
                Db::commit();
                $this->success('删除成功！');
            } else {
                Db::rollback();
                $this->error('删除失败！错误提示:删除出版周期数据失败!');
            }
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    /**
     *
     * 期刊验收重定向至图书页面
     */
    public function checkAction()
    {
        return $this->redirect('book/index', ['source' => 'qk_ys']);
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 验收
     */
    public function ysAction()
    {
        $qk_id = input('qk_id/d');

        if (!$this->isPost) {
            $qk_info = Qk::get($qk_id);

            if (!$qk_info) {
                $this->alertMsg('数据库未找到此数据！');
            }

            if ($qk_info["status"] != self::STATUS_YD) {
                $this->alertMsg('期刊目前不是预订状态,无法验收！');
            }

            if ($qk_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->alertMsg('无法验收其他馆期刊！');
            }

            $field = 'lib_book.book_id,title,isbn,clc,firstauthor,publisher,pubplace,pubdate,price_ms,bl_title,othertitle,fjno,fjtitle,marc';
            $book_info = Book::field($field)->where(['book_id'=>$qk_info['book_id']])->find();

            $rel_list_no = QkRel::field("qk_rel_id,pos")->where(['qk_id'=>$qk_id,'is_ys'=>['<>',1]])->select();

            $this->assign("book_info", $book_info);
            $this->assign("rel_list_no", $rel_list_no);
//            $this->assign("rel_list", $rel_list);
            $this->assign("qk_info", $qk_info);
            return view();
        }
        try{
            $qk_rel_id = input('qk_rel_id/d') ?: 0;
            $qk_rel_info = QkRel::get($qk_rel_id);

            if (!$qk_rel_info) {
                $this->error('验收失败!无效的期刊预订期数');
            }

            $where = [
                'qk_id' => $qk_rel_info['qk_id'],
                'tsg_code' => $this->adminInfo['tsg_code']
            ];
            $qk_info = Qk::field("qk_id,book_id")->where($where)->find();

            if (!$qk_info) {
                $this->error('验收失败!不存在该期刊预订信息');
            }

            $ys_book_cnt = input('ys_book_cnt') ?: 0;

            if (!$ys_book_cnt) {
                $this->error('验收册数不能为空');
            }

            $remark = input('remark') ?: '';

            $save_data = array(
                "ys_book_cnt" => $ys_book_cnt,
                "is_ys" => 1,
                "ys_time" => mstrtotime(date("Y-m-d")),
                "ys_user" => $this->adminInfo["user_name"],
                'remark' => $remark
            );


            Db::startTrans();
            $is_success = QkRel::update($save_data,['qk_rel_id'=>$qk_rel_id])->result;

            if ($is_success === false) {
                Db::rollback();
                $this->error('保存失败！');
            }

            $param = [
                'book_id' => $qk_info['book_id'],
                'db1' => $qk_info['qk_id'],
                'op_desc' => '[#],验收期数'.$qk_rel_info['pos'].',验收册数'.$ys_book_cnt
            ];
            QkLog::addlog(QkLog::OP_TYPE_YS_ADD, $this->adminInfo, $param);
            Db::commit();
            $this->success('验收成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 验收数据
     */
    public function ysListAction()
    {
        $qk_id = input('qk_id/d') ?: 0;
        $condition = [
            'is_ys' => 1,
            'qk_id' => $qk_id
        ];
        $params = $this->getQueryParams();//分页,排序,查询参数

//        $list = QkRel::getPageList($condition, $params->limit, $params->order);
        $list = QkRel::with('Qk')->where($condition)->limit($params->limlt)->order($params->order)->select();
        $count = QkRel::where($condition)->count();

        if ($list){
            foreach ($list as &$item){
                $item['book_cnt'] = $item->qk->book_cnt;
                $item['order_year'] = $item->qk->order_year;
                unset($item->qk);
            }
        }
        return $this->echoPageData($list, $count);
    }

    /**
     *取消验收
     */
    public function drop_ysAction()
    {
        try{
            $qk_rel_id = input('qk_rel_id');
            $where = [
                'qk_rel_id' => $qk_rel_id,
                'is_ys' => 1
            ];
            $rel_info = QkRel::field("qk_rel_id,qk_id,pos,ys_book_cnt")->where($where)->find();

            if (empty($rel_info)) {
                $this->error('无效的验收信息,无法取消！');
            }

            $where = [
                'qk_id' => $rel_info['qk_id'],
                'tsg_code' => $this->adminInfo['tsg_code']
            ];
            $qk_info = Qk::field("qk_id,book_id")->where($where)->find();

            if (!$qk_info) {
                $this->error('取消失败!不存在该期刊预订信息');
            }


            $save_data = array(
                "ys_book_cnt" => 0,
                "is_ys" => 0,
                "ys_time" => 0,
                "ys_user" => "");
            Db::startTrans();
            $is_success = QkRel::update($save_data,['qk_rel_id'=>$qk_rel_id])->result;

            if ($is_success === false) {
                Db::rollback();
                $this->error('取消失败！');
            }

            $param = [
                'book_id' => $qk_info['book_id'],
                'db1' => $qk_info['qk_id'],
                'op_desc' => '[#],取消期数'.$rel_info['pos'].',取消册数'.$rel_info['ys_book_cnt']
            ];

            QkLog::addlog(QkLog::OP_TYPE_YS_DROP, $this->adminInfo, $param);
            Db::commit();
            $this->success("取消成功！");
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }


    public function uploadAction()
    {
        if ($this->isPost) {
            Upload::clear_disuse_file();
            $mt_id = input('mt_id_change/d');
            $file_encode = input('file_encode', 'utf-8');
            if (empty($mt_id)) {
                $this->error('请选择有效的MARC类型!');
            }

            $file = request()->file('marc_file');
            if (!$file) {
                $this->error('请上传文件!');
            }
            $error = $_FILES['file']['error'];
            if ($error) {
                $this->error('上传失败，' . $error);
            }
            $dir = config('upload_path') . 'marcfiles/';
            $info = $file->move(ROOT_PATH . 'public/' . $dir);

            $saveName = 'marcfiles/' . $info->getSaveName();
            $data['fileName'] = $saveName;
            $data['filePath'] = get_img_full_path($saveName);
            if ($file->getError()) {
                $this->error($file->getError());
            }
            $file_buff = file_get_contents(ROOT_PATH . 'public/' . $dir . $info->getSaveName());
            $marc_cnt = preg_match_all("/\\x1D/", $file_buff, $matchs);

            $add_data = [];
            $add_data['user_id'] = $this->adminInfo['user_id'];
            $add_data['file_encode'] = $file_encode;
            $add_data['file_name'] = $file->getInfo()['name'];
            $add_data['file_path'] = $saveName;
            $add_data['marc_cnt'] = $marc_cnt;
            $add_data['up_type'] = Upload::UP_TYPE_DESTINE;
            $add_data['add_time'] = time();
            $result = Upload::create($add_data)->result;
            if ($result) {
                // 立即导入文件 TODO
                //$this->redirect('', ['upload_id' => $result, 'mt_id' => $mt_id]);
                $this->success('上传成功');
            } else {
                @unlink(get_img_real_path($add_data["file_path"]));
                $this->error('上传文件失败！');
            }

        }
        $mt_list = Mt::get_list();
        $file_list = Upload::where(['user_id' => $this->adminInfo['user_id'], 'is_add' => 0, 'up_type' => Upload::UP_TYPE_DESTINE])->order('add_time desc')->select();
        $this->assign("mt_id", $this->adminInfo["default_mt"]);
        $this->assign('mt_list', $mt_list);
        $this->assign('file_list', $file_list);
        return view();
    }

    public function importbookAction()
    {
        exit();
        $mt_id = input('mt_id/d');
        $upload_id = input('upload_id/d');
        $mt_info = Mt::get($mt_id);

        if (empty($mt_info)) {
            $this->error("请选择有效的MARC类型!");
        }
        $this->assign("mt_info", $mt_info);
        $file_info = Upload::where(['upload_id' => $upload_id, 'user_id' => $this->adminInfo['user_id'], 'is_add' => 0, 'up_type' => Upload::UP_TYPE_DESTINE])->find();
        if (empty($file_info)) {
            $this->error("在数据库未找到该文件信息！");
        }
        if (!file_exists(get_img_real_path($file_info["file_path"]))) {
            $file_info->delete();
            $this->error("在服务器上未找到该文件！");
        }
        $user_info = User::where('user_id', $this->adminInfo['user_id'])->field("qk_batch_curr")->find();
        if (empty($user_info)) {
            $this->error("请先设定默认预订批次!");
        }
        $batch_info = QkBatch::field("qk_batch_code,seller_code,cost_code,status")
            ->where("qk_batch_code='{$this->adminInfo['qk_batch_curr']}' AND tsg_code='{$this->adminInfo["tsg_code"]}'")->find();
        if (empty($batch_info)) {
            $this->error("无效默认预订批次,请重新设定默认预订批次!");
        }
        if ($batch_info["status"] != QkBatchController::BATCH_STATUS_YD) {
            $this->error("默认预订批次必须为预订状态,请重新设定默认预订批次!");
        }

        if (!$this->isPost) {
            $change_file_encode = input('change_file_encode');
            if (($change_file_encode != "") && ($change_file_encode != $file_info["file_encode"])) {
                Upload::where("upload_id=$upload_id")->save(array("file_encode" => $change_file_encode));
                $file_info["file_encode"] = $change_file_encode;
            }

            import("@.Marc.MARCReader");
            $reader1 = new \MARCReader($file_info["file_path"], $file_info["file_encode"]);
            $marc_list = array();
            $raw_list = array();

            for ($i = 0; ($marc = $reader1->next()) != false; $i++) {
                $marc_list[] = nl2br($marc->toString("", array("zsf_replace" => "_", "field_space_replace" => "&nbsp;", "field_head_replace" => "§")));
                $raw_list[] = $marc->getDataConvEncode("utf-8");
            }

            $this->assign("raw_list", json_encode($raw_list));
            $this->assign("file_info", $file_info);
            $this->assign("batch_info", $batch_info);
            $this->assign("marc_list", $marc_list);
            $this->display();
        } else {
            import('TaskStatus\TaskStatus', EXTEND_PATH, '.class.php');
            \TaskStatus::initTaskValue(self::IMPORT_TASK_NAME);
            $beg_time = time();
            $cnf = $this->getcnf();
            $mod_book = d("Book");
            $mod_qk = d("Qk");
            import("@.Marc.MARCReader");
            import("@.Extend.Scws.Scws");
            import('String', EXTEND_PATH, '.class.php');
            $msg_gbk = array(\String::autoCharset("重复数据", "utf-8", "gbk"), \String::autoCharset("预订数据不完整(单价、折后价、复本数都为空)", "utf-8", "gbk"), \String::autoCharset("插入数据错误", "utf-8", "gbk"), \String::autoCharset("程序处理出现异常", "utf-8", "gbk"), \String::autoCharset("插入预订数据错误", "utf-8", "gbk"), \String::autoCharset("增加书目索引数据时遇到错误", "utf-8", "gbk"));

            if (!class_exists("IsbnBase")) {
                import("@.Extend.Isbn.IsbnBase");
            }

            $reader1 = new \MARCReader($file_info["file_path"], $file_info["file_encode"]);
            $marc_list = array();
            $del_fields = $cnf["del_fields"];
            $import_err = "";
            $import_cnt = 0;
            $import_err_cnt = 0;
            $cq_table = array();
            $cq_where_map = array();

            foreach ($cnf["zd_cnf"] as $key => $item) {
                if (!in_array($key, array("ori_price", "price", "book_cnt", "jzinfo", "order_no", "order_sour", "remark"))) {
                    unset($cnf["zd_cnf"][$key]);
                }
            }

            $index_list =   Book::get_indexrel($mt_id, array("fileds" => "sour_field,dest_mod,order_mod,sour_mfield,order_table"));

            foreach ($index_list as $item) {
                if (in_array($item["sour_field"], $cnf["field_cnf"])) {
                    $cq_table[] = $item["order_table"];
                    $cq_where_map[$item["sour_field"]] = $item["order_table"] . ".val";
                }
            }

            $is_cq = (!empty($cq_table) ? true : false);
            $cq_table_str = "";
            $first_cq_table = "";

            for ($i = 0; $i < count($cq_table); $i++) {
                if ($i == 0) {
                    $first_cq_table = $cq_table[0];
                    $cq_table_str = $cq_table[0];
                } else {
                    $prev_i = $i - 1;
                    $cq_table_str .= " INNER JOIN $cq_table[$i] on $cq_table[$prev_i].bid=$cq_table[$i].bid";
                }
            }

            $all_marc_cnt = $file_info["marc_cnt"];
            $task_per = (1 / $all_marc_cnt) * 100;

            for ($task_cnt = 0; ($marc = $reader1->next()) != false;) {
                $task_cnt += $task_per;
                \TaskStatus::setTaskValue(self::IMPORT_TASK_NAME, $task_cnt);

                try {
                    if (!$marc->hasError()) {
                        $add_data = $marc->getSimpleData($mt_id);

                        if ($is_cq) {
                            $cq_where = array();

                            foreach ($cq_where_map as $key => $item) {
                                $val_tmp = ($add_data[$key] ? $add_data[$key] : "");
                                $cq_where[$item] = array("eq", $val_tmp);
                            }

                            $cq_info = $mod_book->field("$first_cq_table.bid")->table($cq_table_str)->where($cq_where)->find();

                            if (!empty($cq_info)) {
                                $import_err_cnt++;
                                $import_err .= $msg_gbk[0] . "  " . $marc->getMarcRaw() . chr(13);
                                continue;
                            }
                        }

                        $add_data["tsg_code"] = $this->_user_info["tsg_code"];
                        $add_data["cataloger"] = $this->_user_info["user_name"];
                        $add_data["catatime"] = time();
                        $add_data["mt_id"] = $mt_id;
                        $rel_arr = $marc->getRelArray();
                        $marc->dropField($del_fields);
                        $add_data["marc"] = $marc->toString();
                        $yd_data = array();

                        foreach ($cnf["zd_cnf"] as $key => $item) {
                            if ($item["type_sel"] == 1) {
                                if (!empty($item["marc_input"]) && (strlen($item["marc_input"]) == 4)) {
                                    $yd_data[$key] = (!empty($rel_arr[$item["marc_input"]][0]) ? $rel_arr[$item["marc_input"]][0] : "");
                                }
                            } else {
                                $yd_data[$key] = $item["custom_val"];
                            }
                        }

                        if ((empty($yd_data["price"]) && empty($yd_data["ori_price"])) || empty($yd_data["book_cnt"])) {
                            $import_err_cnt++;
                            $import_err .= $msg_gbk[1] . "  " . $marc->getMarcRaw() . chr(13);
                            continue;
                        }

                        $yd_data["price"] = (empty($yd_data["price"]) ? floatval($yd_data["ori_price"]) : floatval($yd_data["price"]));
                        $yd_data["ori_price"] = (empty($yd_data["ori_price"]) ? floatval($yd_data["price"]) : floatval($yd_data["ori_price"]));
                        $mod_book->startTrans();
                        $book_id = $mod_book->addFast($add_data);

                        if ($book_id === false) {
                            $import_err_cnt++;
                            $mod_book->rollback();
                            $import_err .= $msg_gbk[2] . "    " . $marc->getMarcRaw() . chr(13);
                            Log::write("预订数据导入-插入数据错误     " . $mod_book->getError());
                            continue;
                        }

                        $index_add_data = Book::conv_index_data($book_id, $add_data, $rel_arr, $index_list);

                        foreach ($index_add_data as $key => $item) {
                            $mod_dest = d($key);
                            $is_success = $mod_dest->add($item);

                            if ($is_success === false) {
                                $mod_book->rollback();
                                $import_err_cnt++;
                                $import_err .= $msg_gbk[5] . "    " . $marc->getMarcRaw() . chr(13);
                                Log::write("插入书目索引失败    " . $mod_dest->db()->getError());
                                continue 2;
                            }
                        }

                        $yd_data["book_id"] = $book_id;
                        $yd_data["tsg_code"] = $this->_user_info["tsg_code"];
                        $yd_data["qk_batch_code"] = $batch_info["qk_batch_code"];
                        $yd_data["seller_code"] = $batch_info["seller_code"];
                        $yd_data["cost_code"] = $batch_info["cost_code"];
                        $yd_data["discount"] = round(($yd_data["price"] / $yd_data["ori_price"]) * 100, 2);
                        $yd_data["add_time"] = mstrtotime(date("Y-m-d"));
                        $yd_data["add_user"] = $this->_user_info["user_name"];
                        $qk_id = $mod_qk->add($yd_data);

                        if ($qk_id !== false) {
                            $mod_book->commit();
                            $import_cnt++;
                        } else {
                            $mod_book->rollback();
                            $import_err_cnt++;
                            $import_err .= $msg_gbk[4] . "    " . $marc->getMarcRaw() . chr(13);
                            Log::write("预订数据导入-插入预订数据错误     " . $mod_qk->db()->getError());
                        }
                    } else {
                        $mod_book->rollback();
                        $import_err_cnt++;
                        $import_err .= $marc->getError() . "    " . $marc->getMarcRaw() . chr(13);
                    }
                } catch (\Exception $e) {
                    $import_err_cnt++;
                    $import_err .= $msg_gbk[3] . "    " . $marc->getMarcRaw() . chr(13);
                }
            }

            $save_data = array();

            if (0 < $import_err_cnt) {
                $file_name_tmp = basename($file_info["file_path"]);
                $file_name_tmp = explode(".", $file_name_tmp);
                $file_name_tmp = "err_" . $file_name_tmp[0] . ".txt";
                $err_file_path = dirname($file_info["file_path"]) . "/" . $file_name_tmp;
                file_put_contents($err_file_path, $import_err);
                $save_data["err_file"] = $err_file_path;
                $file_info["err_file"] = $err_file_path;
            }

            $save_data["is_add"] = 1;
            $save_data["disuse_time"] = mstrtotime(date("Y-m-d"));
            $mod_upload->where("upload_id=$upload_id")->save($save_data);
            $reader1->closeFile();
            unlink($file_info["file_path"]);
            $end_time = time();
            $use_time = $end_time - $beg_time;
            $use_time = floor($use_time / 3600) . "小时" . floor(($use_time % 3600) / 60) . "分钟" . floor($use_time % 3600 % 60) . "秒";
            $this->assign("use_time", $use_time);
            $this->assign("file_info", $file_info);
            $this->assign("import_cnt", $import_cnt);
            $this->assign("import_err_cnt", $import_err_cnt);
            $this->assign("import_err", $import_err);
            $this->assign("marc_list", $marc_list);
            $this->display("importinfo");
            return NULL;
        }
    }

}
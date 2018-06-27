<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/9
 * Time: 14:27
 */

namespace app\admin\controller;
use app\admin\model\Batch;
use app\admin\model\Book;
use app\admin\model\Bookseller;
use app\admin\model\CheckBatch;
use app\admin\model\Cost;
use app\admin\model\DcDispatch;
use app\admin\model\Dck;
use app\admin\model\DcLog;
use app\admin\model\Dzgl;
use app\admin\model\JdbmCnf;
use app\admin\model\Lend;
use app\admin\model\Ltype;
use app\admin\model\Tsg;
use app\admin\model\TsgSite;
use app\admin\model\Zch;
use think\Db;
use think\Exception;
use think\Session;

/**
 * Class DcbatController
 * @package app\admin\controller
 * 藏书管理
 */
class DcbatController extends BaseController
{
    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 图书入藏登记
     */
    public function regAction()
    {
        $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name');

        if ($this->isPost){
            try{
                $barcode = input('get.barcode') ?: '';
                $tsg_site_code = input('get.tsg_site_code') ?: '';
                $is_confirm = input('get.is_confirm/d') ?: '';
//            $only_self = input('only_self') == '1' ? false : true;
                if (empty($barcode)){
                    $this->error('图书条码为空');
                }

                $where = array();
                $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
                $where["barcode"] = array("eq", $barcode);
                $fields = "dck_id,book_id,barcode,tsg_site_code";
                $dck_info = Dck::field($fields)->where($where)->order('dck_id')->find();

                if (empty($dck_info)) {
                    $this->error('无效图书条码'.$barcode.',本馆不存在此条码!');
                }

                $book_info = self::book_info($dck_info['book_id']);

                if (empty($tsg_site_code) || empty($is_confirm)) {
                    $this->success('馆藏登记未改变馆藏地址!','',$book_info);
                }

                if (!isset($tsg_site_map[$tsg_site_code])) {
                    $this->success('无效馆藏地址,数据库不存在此馆藏地址!','',$book_info);
                }

                Db::startTrans();
                $param = [
                    "dck_id" => $dck_info["dck_id"],
                    "book_id" => $dck_info["book_id"],
                    "op_desc" => "[#],".$dck_info["tsg_site_code"]."=>".$tsg_site_code
                ];
                $is_success = DcLog::addlog(DcLog::OP_TYPE_REG, $this->adminInfo,$param);
                if ($is_success !== false){

                    $save_data = array("tsg_site_code" => $tsg_site_code, "tsg_site_code_has" => $tsg_site_code);
                    $is_success = Dck::update($save_data,$where)->result;

                    if ($is_success !== false) {
                        Db::commit();
                        $msg  = "馆藏登记成功!原馆藏地址:".$dck_info["tsg_site_code"].'|'.$tsg_site_map[$dck_info["tsg_site_code"]];
                        $msg .= '===>更改后馆藏地址为:'.$tsg_site_code.'|'.$tsg_site_map[$tsg_site_code];
                        $this->success($msg,'',$book_info);
                    }
                    else {
                        Db::rollback();
                        $this->success('馆藏登记失败:更新数据库失败','',$book_info);
                    }

                }else {
                    Db::rollback();
                    $this->success('条码' . $barcode . '操作日志记录失败', '', $book_info);
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }

        $this->assign('tsg_site_map',$tsg_site_map);
        return view();
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *异步获取图书信息
     */
    public function getJsonListAction()
    {
        $only_self = input('only_self/d') ?: 0;


        $book_id = input('book_id/d') ?: 0;
        $condition = [];
        $list = [];
        $count = 0;

        if ($book_id === 0){
            return $this->echoPageData($list, $count);
        }
        $condition['book_id'] = $book_id;

        if ($only_self !== 0){
            $condition['tsg_code'] = $this->adminInfo['tsg_code'];
        }
        $params = $this->getQueryParams();//分页,排序,查询参数

        $field = 'barcode,login_no,calino,status,tsg_code,tsg_site_code,lt_type,price,check_batch';
        $list = Dck::getPageList($condition, $params->limit, $params->order,$field);
        $count = Dck::where($condition)->count();
        if ($list){
            $tsg_map = Tsg::getMap('tsg_code','tsg_name');
            $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name',$this->adminInfo['tsg_code']);
            $ltype_map = Ltype::getMap('ltype_code','ltype_name',$this->adminInfo['tsg_code']);
            foreach ($list as &$item){
                $item['tsg_code'] = $item['tsg_code'].'|'.$tsg_map[$item['tsg_code']];
                $item['tsg_site_code'] = $item['tsg_site_code']."|".$tsg_site_map[$item['tsg_site_code']];
                $item['lt_type'] = $item['lt_type'] . '|' . $ltype_map[$item['lt_type']];
            }
            unset($item);
        }
        return $this->echoPageData($list, $count);
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 异步获取图书清点
     */
    public function getHandleListAction()
    {
        $is_search = input('is_search/d') ?: 0;
        $is_excel  = input('is_excel/d') ?: 0;
        $is_list = input('is_list/d') ?: 0;
        $list = [];
        $count=0;

        $fields = "book_id,barcode,calino,tsg_site_code,price,status";

        if ($is_search !== 0) {

            $params = $this->getQueryParams();//分页,排序,查询参数


            if ($params->search) {
                $condition = self::handle_condition($params->search,$is_list);
            }

            $condition['tsg_code'] = $this->adminInfo['tsg_code'];

            $list = Dck::with(['book'=>function($query){
                $query->field('title,isbn,publisher,pubdate,book_id');
            }])->field($fields)->where($condition)->limit($params->limit)->order($params->order)->select();

            $count = Dck::where($condition)->count();

            if ($list) {
                foreach ($list as &$item) {
                    $item['title'] = $item->book->title;
                    $item['isbn'] = $item->book->isbn;
                    $item['publisher'] = $item->book->publisher;
                    $item['pubdate'] = $item->book->pubdate;
                    unset($item->book);
                }
                unset($item);
            }
            if ($is_excel !==0){
                session('excel_data',$list);
                exit();
            }

        }
        return $this->echoPageData($list, $count);

    }

    /**
     * @param $searchs
     * @param int $is_list  0---清点操作  1---清点列表
     * @return array
     * 图书清点公用条件
     */
    private static  function handle_condition($searchs,$is_list = 0)
    {
            $condition = [];
            $start_time = $end_time = '';
            if ($searchs) {
                foreach ($searchs as $search) {
                    switch ($search['field']) {
                        case 'start_time':
                            $start_time = $search['value'];
                            break;
                        case 'end_time':
                            $end_time = $search['value'];
                            break;
                        case 'tsg_site_code':
                            $condition[$search['field']] = $search['value'];
                            break;
                        case 'status':
                            $condition[$search['field']] = ['in', $search['value']];
                            break;
                        case 'check_batch_list':
                            if ($is_list == 0){
                                $condition['check_batch'] = ['neq', $search['value']];
                            }else{
                                $condition['check_batch'] = ['eq', $search['value']];
                            }

                            break;
                    }
                }
            }

            if ($start_time && $end_time) {
                $condition['add_time'] = ['between time', [$start_time, $end_time]];
            } elseif ($start_time) {
                $condition['add_time'] = ['> time', $start_time];
            } elseif ($end_time) {
                $condition['add_time'] = ['< time', $end_time];
            }

            return $condition;
    }


    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 批量入藏
     */
    public function batch_regAction()
    {
        if (!$this->isPost) {
            $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name');
            $this->assign("tsg_site_map", $tsg_site_map);
            return view();
        }
        try{
            $barcode_list = input('post.barcode_list')?:'';
            $tsg_site_code = input('post.tsg_site_code') ?: '';

            if (empty($barcode_list)) {
                $this->error('批量入藏的图书条码不能为空');
            }

            $barcode_list = explode("\n", $barcode_list);
            $where = array();
            $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
            $where["barcode"] = array("in", $barcode_list);
            $tsg_site_info = TsgSite::field("tsg_site_code,site_name")
                ->where(['tsg_code'=>$this->adminInfo['tsg_code'], 'tsg_site_code' => $tsg_site_code])
                ->find();

            if (empty($tsg_site_info)) {
                $this->error('无效馆藏地址,数据库不存在此馆藏地址!');
            }

            Db::startTrans();
            $save_data = array("tsg_site_code" => $tsg_site_code, "tsg_site_code_has" => $tsg_site_code);
            $is_success = Dck::update($save_data,$where)->result;

            if ($is_success !== false) {
                Db::commit();
                DcLog::addlog(DcLog::OP_TYPE_BATCH_REG, $this->_user_info, array("book_id" => 0, "db1" => 0));
                $this->success('批量入藏成功');
            }
            else {
                Db::rollback();
                $this->error('批量入藏失败:更新数据库失败');
            }
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @param $book_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 书籍信息显示
     */
    private static function book_info($book_id)
    {
        $book_field = "book_id,title,isbn,publisher,firstauthor,pubdate";
        $book_info = Book::field($book_field)->where(['book_id' => $book_id])->find();
        return $book_info;
    }

    /**
     * @return \think\response\View
     * 图书剔旧
     */
    public function dropAction()
    {
        if ($this->isPost){
            try{
                $barcode = input('get.barcode') ?: '';
//            $only_self = input('only_self') == '1' ? false : true;
                if (empty($barcode)){
                    $this->error('图书条码为空');
                }
                $where = array();
                $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
                $where["barcode"] = array("eq", $barcode);
                $fields = "dck_id,book_id,barcode,tsg_site_code,status";
                $dck_info = Dck::field($fields)->where($where)->order('dck_id')->find();

                if (empty($dck_info)) {
                    $this->error('无效图书条码' . $barcode . ',本馆不存在此条码!');
                }

                $book_info = self::book_info($dck_info['book_id']);

                Db:: startTrans();
                $param = [
                    "dck_id" => $dck_info["dck_id"],
                    "book_id" => $dck_info["book_id"],
                    "op_desc" => "[#]," . $barcode . $dck_info["status"],
                ];
                $is_success = DcLog::addlog(DcLog::OP_TYPE_DROP, $this->adminInfo, $param);

                if ($is_success === false) {
                    Db::rollback();
                    $this->success('操作日志记录失败！', '', $book_info);
                } else {
                    $save_data = array("status" => "剔除", "is_close" => 1);
                    $is_success = Dck::update($save_data, $where)->result;

                    if ($is_success !== false) {
                        Db::commit();
                        $this->success('图书条码' . $barcode . '剔除成功', '', $book_info);
                    } else {
                        Db::rollback();
                        $this->success('图书条码' . $barcode . '剔除失败', '', $book_info);
                    }
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }
        return view();
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 图书清点
     */
    public function checkAction()
    {
        if ($this->isPost) {
            try{
                $barcode = input('barcode')?:0;
                $batch = input('batch') ?: '';
                $where = array();
                $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
                $where["barcode"] = array("eq", $barcode);
                $fields = "dck_id,book_id,barcode,tsg_site_code";
                $order = 'dck_id';
                $dck_info = Dck::field($fields)->where($where)->order($order)->find();

                if (empty($dck_info)) {
                    $this->error('无效图书条码'.$barcode.'本馆不存在此条码');
                }

                $book_info = self::book_info($dck_info['book_id']);

                $where_check = [
                    'tsg_code' => $this->adminInfo['tsg_code'],
                    'batch' => $batch
                ];

                $tmp_batch_info = CheckBatch::get($where_check);

                if (empty($tmp_batch_info)) {
                    $this->success('无效的清点批次,请重新选择批次!','',$book_info);
                }

                Db::startTrans();
                $param = [
                    "dck_id" => $dck_info["dck_id"],
                    "book_id" => $dck_info["book_id"],
                    "op_desc" => "[#],".$batch,
                ];
                $is_success = DcLog::addlog(DcLog::OP_TYPE_CHECK,$this->adminInfo,$param);

                if ($is_success === false) {
                    Db::rollback();
                    $msg = "条码".$barcode."登记失败,错误提示:清点操作记录失败！";
                    $this->success($msg,'',$book_info);
                }

                $save_data = array("check_batch" => $batch);

                $is_success = Dck::update($save_data,$where)->result;

                if ($is_success !== false) {
                    Db::commit();
                    $this->success('馆藏清点成功','',$book_info);
                }
                else {
                    Db::rollback();
                    $this->success('馆藏清点失败！错误提示:数据库更新失败!','',$book_info);
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }

        $where = [
            'tsg_code' => $this->adminInfo['tsg_code']
        ];

        $check_batch_list = CheckBatch::where($where)->order("add_time desc")->select();
        $site_code_list = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);
        $dc_status_list = Dck::getStatusList();


        $this->assign("check_batch_list", $check_batch_list);
        $this->assign("tsg_site_map", $site_code_list);
        $this->assign("dc_status_list", $dc_status_list);
        return view();
    }

    /**
     * 批量清点图书
     */
    public function batch_checkAction()
    {
        try{
            $barcode_list = input('post.barcode_list') ?: '';
            $batch = input('post.batch') ?: '';

            if (empty($barcode_list)) {
                $this->error('图书条码列表不能为空');
            }

            if (empty($batch)) {
                $this->error('清点批次不能为空');
            }

            $barcode_list = explode("\n", $barcode_list);
            $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
            $where_check = [
                'tsg_code' => $this->adminInfo['tsg_code'],
                'batch' => $batch
            ];
            $tmp_batch_info = CheckBatch::get($where_check);

            if (empty($tmp_batch_info)) {
                $this->error('无效的清点批次');
            }

            $save_data = array("check_batch" => $batch);
            $barcode_list = array_chunk($barcode_list, 200);

            Db::startTrans();
            foreach ($barcode_list as $item) {
                $where["barcode"] = array("in", $item);
                $is_success = Dck::update($save_data, $where)->result;
                if ($is_success === false) {
                    Db::rollback();
                    $this->error('馆藏批量清点失败');
                }
            }
            Db::commit();
            $this->success('馆藏批量清点成功！');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }


    /**
     * @throws \think\exception\DbException
     * 新增清点批次
     */
    public function add_batchAction()
    {
        $batch = input('get.batch')?:'';
        $where = [
            'tsg_code' => $this->adminInfo['tsg_code'],
            'batch' => $batch
        ];
        $tmp_batch_info = CheckBatch::get($where);

        if (!empty($tmp_batch_info)) {
            $this->error('清点批次重复,本馆已存在此批次!');
        }

        $add_data = array("tsg_code" => $this->_user_info["tsg_code"], "batch" => $batch, "add_time" => time());
        $is_success = CheckBatch::create($add_data)->result;

        if ($is_success !== false) {
            $this->success('清点批次增加成功!');
        }
        else {
            $this->error('清点批次增加数据失败');
        }
    }

    /**
     * 删除清点批次
     */
    public function del_batchAction()
    {
        $batch = input('get.batch') ?: '';
        $where = [
            'tsg_code' => $this->adminInfo['tsg_code'],
            'batch' => $batch
        ];
        $is_success = CheckBatch::where($where)->delete();

        if ($is_success !== false) {
            $this->success('批次删除成功!');
        }
        else {
            $this->error('批次删除失败');
        }
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 图书清点操作 --- 列表
     */
    public function handleAction()
    {
        $status_list = array("剔除", "遗失", "损坏", "流失", "停用");

        if ($this->isPost) {
            try{
                $new_status = input('post.dck_status')?:'';
                $where = input('where/a')?:[];

                if (empty($new_status) || !in_array($new_status, $status_list)) {
                    $this->error("请先选择状态再开始清点处理!");
                }

                $where = self::handle_condition($where);

                $where['tsg_code'] = $this->adminInfo['tsg_code'];

                if (!isset($where['tsg_site_code']) && empty($where['tsg_site_code'])) {
                    $this->error("馆藏地址不能为空,请先选择馆藏地址再开始清点处理!");
                }

                if (!isset($where['check_batch'][1]) && empty($where['check_batch'][1])) {
                    $this->error("清点批次不能为空,请先选择清点批次再开始清点处理!");
                }
                Db::startTrans();
                $param = [
                    "dck_id" => 0,
                    "book_id" => 0,
                    "op_desc" => "[#],馆藏地址:".$where['tsg_site_code'].",清点批次:".$where['check_batch'][1].",改变状态至:".$new_status
                ];
                $is_success = DcLog::addlog(DcLog::OP_TYPE_HANDLE,$this->adminInfo,$param);

                if ($is_success === false) {
                    Db::rollback();
                    $this->error("日志记录失败！请稍后再试");
                }

                $save_data = array("status" => $new_status, "is_close" => 1,'check_batch'=>$where['check_batch'][1]);
                $is_success = Dck::update($save_data,$where)->result;

                if ($is_success !== false) {
                    Db::commit();
                    $this->success("清点处理成功！");
                } else {
                    Db::rollback();
                    $this->error("清点处理失败:更新数据库失败");
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }

        $where = [
            'tsg_code' => $this->adminInfo['tsg_code']
        ];

        $check_batch_list = CheckBatch::where($where)->order("add_time desc")->select();
        $site_code_list = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);

        $dc_status_list = Dck::getStatusList();


        $this->assign("check_batch_list", $check_batch_list);
        $this->assign("tsg_site_map", $site_code_list);
        $this->assign("status_list", $status_list);
        $this->assign("dc_status_list", $dc_status_list);
        return view();
    }

    /**
     * session --- excel
     */
    public function export_excelAction()
    {
        $list = session('excel_data') ?: [];
        session('excel_data', null);
        $this->export_excel_handle($list);
        exit();
    }

    /**
     * 缓存 --- excel
     */
    public function export_excel_distAction()
    {
        $list = cache('dist_data') ?: [];
        cache('dist_data', null);
        $this->export_excel($list);
        exit();
    }

    /**
     * @param $datalist
     * 导出图书分布统计excel
     */
    private function export_excel($datalist)
    {
        import("phpExport\phpExport",EXTEND_PATH,'.class.php');
        $exporter = new \ExportDataExcel("browser", l("menu_dc_sub").".xls");
        $exporter->initialize();
        $fields = array("title" => l("title"), "firstauthor" => l("firstauthor"), "barcode" => l("barcode"), "calino" => l("calino"), "status" => l("status"), "tsg_code_has" => "所属馆", "tsg_code" => "所在馆", "tsg_site_code" => "馆藏地址", "lt_type" => "流通类型", "price" => l("price"), "price_sum" => l("price_sum"), "add_time" => "入库日期");
        $head_list = array();

        foreach ($fields as $item ) {
            $head_list[] = $item;
        }

        $exporter->addRow($head_list);
        foreach ($datalist as $item ) {
            $data_row = array();
            foreach ($fields as $key1 => $item1 ) {
                $val_tmp = (isset($item[$key1]) ? $item[$key1] : "");
                $data_row[] = $val_tmp;
            }

            $exporter->addRow($data_row);
            $data_row = array();
        }

        $exporter->finalize();
        exit();
    }

    /**
     * @param $datalist
     *导出图书清点excel
     */
    private function export_excel_handle(&$datalist)
    {
        import("phpExport\phpExport",EXTEND_PATH,'.class.php');
        $exporter = new \ExportDataExcel("browser", l("menu_dc_sub").".xls");
        $exporter->initialize();
        $fields = array(
            "barcode" => l("barcode"),
            "title" => l("title"),
            "isbn" => "ISBN",
            "calino" => l("calino"),
            "status" => l("status"),
            "publisher" => "出版社",
            "pubdate" => "出版日期",
            "price" => l("price"));
        $head_list = array();

        foreach ($fields as $item ) {
            $head_list[] = $item;
        }

        $exporter->addRow($head_list);

        foreach ($datalist as $item ) {
            $data_row = array();

            foreach ($fields as $key1 => $item1 ) {
                $val_tmp = (isset($item[$key1]) ? $item[$key1] : "");
                $data_row[] = $val_tmp;
            }

            $exporter->addRow($data_row);
            $data_row = array();
        }

        $exporter->finalize();
        exit();
    }

    /**
     * @return \think\response\View
     * 图书条码置换
     */
    public function barcode_tabAction()
    {
        if ($this->isPost){
            try{
                $barcode_old = input('barcode_old') ?: 0;
                $barcode_new = input('barcode_new') ?: 0;
                $where = array();
                $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
                $where["barcode"] = array("eq", $barcode_old);
                $fields = "dck_id,book_id,barcode,tsg_site_code,status";
                $dck_info = Dck::field($fields)->where($where)->find();

                if (empty($dck_info)) {
                    $this->error('无效原图书条码' . $barcode_old . '本馆不存在此条码');
                }

                $book_info = self::book_info($dck_info['book_id']);


                $barcode_new_info = Dck::get(['tsg_code'=>$this->adminInfo['tsg_code'],'barcode'=>$barcode_new]);

                if (!empty($barcode_new_info)) {
                    $this->success('新图书条码' . $barcode_new . '重复,请重新指定图书条码','',$book_info);
                }

                $dck_status_list = config('dck.status_list');
                if ($dck_status_list[$dck_info['status']] != 1){
                    $this->success('图书状态为'.$dck_info['status'].'，不可置换','',$book_info);
                }

                Db::startTrans();
                $param = [
                    "dck_id" => $dck_info["dck_id"],
                    "book_id" => $dck_info["book_id"],
                    "op_desc" => "[#],".$barcode_old."=>".$barcode_new,
                ];
                $is_success = DcLog::addlog(DcLog::OP_TYPE_BARCODE_TAB, $this->adminInfo,$param);

                if ($is_success === false) {
                    Db::rollback();
                    $this->success('操作日志记录失败','',$book_info);
                }
                else {
                    $save_data = array("barcode" => $barcode_new);
                    $is_success = Dck::update($save_data,$where)->result;

                    if ($is_success !== false) {
                        Db::commit();
                        $this->success('图书条码'.$barcode_old.'成功置换为'.$barcode_new,'',$book_info);
                    }
                    else {
                        Db::rollback();
                        $this->success('条码置换失败！');
                    }
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        return view();
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 馆藏批修改操作 ---- 页面显示
     */
    public function batch_procAction()
    {
        if ($this->isPost){
            try{
                $data = $this->request->post();
                $where = isset($data['where']) ? $data['where'] : [];
                $save_data = [];
                unset($data['tsg_code']);
                unset($data['where']);

                if (!empty($data) && is_array($data)){
                    foreach ($data as $field => $value){
                        trim($value) ? $save_data[$field] = $value : null;
                    }
                }

                if (empty($save_data)) {
                    $this->error("请先选择修改项再进行批量修改!");
                }

                Db::startTrans();

                $is_success = DcLog::addlog(DcLog::OP_TYPE_BATCH_PROC, $this->adminInfo,['#']);

                if ($is_success === false) {
                    Db::rollback();
                    $this->error("日志记录失败！");
                }

                $where = self::proc_condition($where);
                $is_success = Dck::update($save_data,$where)->result;

                if ($is_success !== false) {
                    Db::commit();
                    $this->success("馆藏批量修改成功！");
                }
                else {
                    Db::rollback();
                    $this->error("馆藏批量修改失败:更新数据库失败");
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }
        $check_batch_list = CheckBatch::where(["tsg_code"=>$this->_user_info["tsg_code"]])->order("add_time desc")->select();
        $site_code_list = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);
        $tsg_list = Tsg::getMap('tsg_code', 'tsg_name',$this->adminInfo['belong_tsg_code']);
        $status_list = c("dck.status_list");
        $bookseller_list = Bookseller::getMap('seller_code', 'seller_name',$this->adminInfo['tsg_code']);
        $cost_list = Cost::getMap('cost_code', 'cost_sour', $this->adminInfo['tsg_code']);
        $batch_list = Batch::field("batch_no")->where(['tsg_code'=>$this->adminInfo['tsg_code']])->select();
        $jz_list = JdbmCnf::field("remark,cnf_val")->where(['cnf_type'=>'介质类型','tsg_code'=>$this->adminInfo['tsg_code']])->select();
        $ly_list = JdbmCnf::field("remark,cnf_val")->where(['cnf_type'=>'图书来源','tsg_code'=>$this->adminInfo['tsg_code']])->select();
        $ltype_list = Ltype::getMap('ltype_code','ltype_name',$this->_user_info["tsg_code"]);

        $search_fields_1_arr = array(
            "isbn" => l("isbn"),
            "title" => l("title"),
            "firstauthor" => l("firstauthor"),
            "publisher" => l("publisher"),
            "subject" => l("subject"),
            "clc" => l("clc"));
        $search_fields_2_arr = array("calino" => l("calino"));

        $this->assign("check_batch_list", $check_batch_list);
        $this->assign("site_code_list", $site_code_list);
        $this->assign("tsg_list", $tsg_list);
        $this->assign("status_list", $status_list);
        $this->assign("bookseller_list", $bookseller_list);
        $this->assign("cost_list", $cost_list);
        $this->assign("batch_list", $batch_list);
        $this->assign("jz_list", $jz_list);
        $this->assign("ly_list", $ly_list);
        $this->assign("ltype_list", $ltype_list);
        $this->assign("search_fields_1_arr", $search_fields_1_arr);
        $this->assign("search_fields_2_arr", $search_fields_2_arr);
        return view();
    }

    /**
     * @param $searchs
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 公用批修改条件
     */
    private static function proc_condition($searchs)
    {
        $condition = [];
        $condition_book = [];
        $search_field_1 = $search_field_2 = $search_val_1 = $search_val_2 = $barcode_beg = $barcode_end = $add_time_beg = $add_time_end = '';

        if ($searchs) {
            foreach ($searchs as $search) {
                switch ($search['field']){
                    case 'search_field_1':
                        $search_field_1 = $search['value'];
                        break;
                    case 'search_field_2':
                        $search_field_2 = $search['value'];
                        break;
                    case 'search_val_1':
                        $search_val_1 = $search['value'];
                        break;
                    case 'search_val_2':
                        $search_val_2 = $search['value'];
                        break;
                    case 'barcode_beg':
                        $barcode_beg = $search['value'];
                        break;
                    case 'barcode_end':
                        $barcode_end = $search['value'];
                        break;
                    case 'add_time_beg':
                        $add_time_beg = $search['value'];
                        break;
                    case 'add_time_end':
                        $add_time_end = $search['value'];
                        break;
                    default:
                        $condition[$search['field']] = $search['value'];
                        break;
                }
            }
        }

        if ($search_val_1){
            $condition_book[$search_field_1] = ['like','%'.$search_val_1.'%'];
        }
        if ($search_val_2){
            $condition[$search_field_2] = ['like','%'.$search_val_2.'%'];
        }
        if ($barcode_beg && $barcode_end){
            $condition['barcode'] = ['between',[$barcode_beg,$barcode_end]];
        }else if($barcode_beg){
            $condition['barcode'] = ['>=',intval($barcode_beg)];
        }else if ($barcode_end){
            $condition['barcode'] = ['<=',intval($barcode_end)];
        }

        if ($add_time_beg && $add_time_end){
            $condition['add_time'] = ['between',[$add_time_beg,$add_time_end]];
        }elseif ($add_time_beg){
            $condition['add_time'] = ['>= time',$add_time_beg];
        }elseif ($add_time_end){
            $condition['add_time'] = ['<=',$add_time_end];
        }

        $book_ids = null;
        if ($condition_book){
            $book_ids = [0];
            $book_list = Book::field('book_id')->where($condition_book)->select();
            if ($book_list){
                foreach ($book_list as $item){
                    $book_ids[] = $item->book_id;
                }
            }
        }

        if ($book_ids !== null){
            $condition['book_id'] = ['in',$book_ids];
        }

        return $condition;
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 异步获取馆藏信息批修改
     */
    public function getProcListAction()
    {
        $is_search = input('is_search/d') ?: 0;
        $list = [];
        $count = 0;
        if ($is_search !== 0){
            $condition = [];
            $params = $this->getQueryParams();//分页,排序,查询参数
            if ($params->search) {
                $condition = self::proc_condition($params->search);
            }

            $fields = 'book_id,barcode,calino,status,price,book_sour,lt_type,jz_type,seller_code,cost_code,tsg_site_code';
            $list = Dck::with('book')->field($fields)->where($condition)->limit($params->limit)->order($params->order)->select();

            $count = Dck::where($condition)->count();

            if ($list) {

                $tsg_site_map = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);

                foreach ($list as &$item) {
                    $item['title'] = $item->book->title;
                    $item['isbn'] = $item->book->isbn;
                    $item['tsg_site_code'] = $item['tsg_site_code'] . "|" . $tsg_site_map[$item['tsg_site_code']];
                    unset($item->book);
                }
                unset($item);
            }
        }

        return $this->echoPageData($list, $count);
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 馆藏分布统计列表
     */
    public function distAction()
    {
        $ltype_list = Ltype::getMap('ltype_code', 'ltype_name', $this->adminInfo['tsg_code']);
        $tsg_site_list = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);
        $tsg_list = Tsg::getMap('tsg_code', 'tsg_name');
        $this->assign('ltype_list',$ltype_list);
        $this->assign('tsg_site_list',$tsg_site_list);
        $this->assign('tsg_list',$tsg_list);
        return view();
    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 异步获取馆藏分布统计
     */
    public function getDistListAction()
    {
        $condition = [];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search){
                if ($search['field'] == 'stype_0') {
                    $condition['tsg_code_has'] = $this->adminInfo['tsg_code'];
                    $condition['tsg_code'] = ['neq', $this->adminInfo['tsg_code']];
                }elseif ($search['field'] == 'stype_1') {
                    $condition['tsg_code_has'] = ['neq', $this->adminInfo['tsg_code']];
                    $condition['tsg_code'] = $this->adminInfo['tsg_code'];
                }else{
                    $condition[$search['field']] = $search['value'];
                }

            }
        }

        $fields = 'dck_id,book_id,barcode,price,price_sum,tsg_code,tsg_code_has,calino,add_time,status,tsg_site_code,lt_type';
        $list = Dck::with(['book'=>function($query){
            $query->field('book_id,title,bl_title,othertitle,fjno,fjtitle,firstauthor');
        }])->field($fields)->where($condition)->limit($params->limit)->order($params->order)->select();
        $count = Dck::where($condition)->count();

        if ($list) {
            $tsg_site_map = TsgSite::getMap('tsg_site_code', 'site_name', $this->adminInfo['tsg_code']);
            foreach ($list as &$item) {
                $item['title'] = Book::getFullTitle($item->book);
                $item['firstauthor'] = $item->book->firstauthor;
                $item['tsg_site_code'] = $item['tsg_site_code'] . "|" . $tsg_site_map[$item['tsg_site_code']];
                unset($item->book);
            }
            unset($item);
        }
            cache('dist_data',$list,600);

        return $this->echoPageData($list, $count);
    }


    /**
     * @return \think\response\View
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 馆藏调拨
     */
    public function dispatchAction()
    {
        $tsg_list = Tsg::getMap('tsg_code', 'tsg_name');
        $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name');

        if ($this->isPost){
            try{
                $barcode = input('barcode');
                $tsg_code_sour = input('tsg_code_sour');
                $tsg_code_dest = input('tsg_code_dest');
                $tsg_site_code = input('tsg_site_code');
                $lt_type = input('lt_type');

                $where = array();
                $where["tsg_code"] = array("eq", $tsg_code_sour);
                $where["barcode"] = array("eq", $barcode);
//            $fields = "dck_id,book_id,barcode,tsg_site_code,tsg_code";
                $dck_info = Dck::get($where);
                if (empty($dck_info) || ($tsg_code_sour != $this->_user_info["tsg_code"])) {
                    $this->error('无效图书条码' . $barcode . ',本馆不存在此条码!');
                }
                else {
                    $dck_info = $dck_info->toArray();
                    $book_info = Book::get($dck_info['book_id']);
                    if (empty($book_info) || ($tsg_code_sour != $this->_user_info["tsg_code"])) {
                        $this->error('无效图书条码' . $barcode . ',此条码无对应书目信息!');
                    } else {
                        $book_info = $book_info->toArray();
                        Db::startTrans();
                        $dc_dispatch_data = array_merge($book_info, $dck_info);
                        $dc_dispatch_data["dispatch_time"] = mstrtotime(date("Y-m-d"));
                        $dc_dispatch_data["dispatch_user"] = $this->_user_info["user_name"];
                        $dc_dispatch_data["tsg_code_dest"] = $tsg_code_dest;
                        $dc_dispatch_data["tsg_name_dest"] = $tsg_list[$tsg_code_dest];
                        $dc_dispatch_data["site_name"] = $tsg_site_map[$dc_dispatch_data["tsg_site_code"]];
                        !empty($tsg_site_code) && ($dc_dispatch_data["tsg_site_code_dest"] = $tsg_site_code);
                        !empty($tsg_site_code) && ($dc_dispatch_data["site_name_dest"] = $tsg_site_map[$tsg_site_code]);
                        !empty($lt_type) && ($dc_dispatch_data["lt_type_dest"] = $lt_type);


                        $is_success = DcDispatch::create($dc_dispatch_data,true)->result;
//                        print_r(111);exit();
                        if ($is_success === false) {
                            Db::rollback();
                            $this->success('条码' . $barcode . '调拨失败，更新数据库失败','',$book_info);
                        } else {
                            $save_data = array();
                            !empty($tsg_code_dest) && ($save_data["tsg_code"] = $tsg_code_dest);
                            !empty($tsg_code_dest) && ($save_data["tsg_code_has"] = $tsg_code_dest);
                            !empty($tsg_site_code) && ($save_data["tsg_site_code"] = $tsg_site_code);
                            !empty($tsg_site_code) && ($save_data["tsg_site_code_has"] = $tsg_site_code);
                            !empty($lt_type) && ($save_data["lt_type"] = $lt_type);


                            $is_success = Dck::update($save_data, $where)->result;

                            if ($is_success !== false) {

                                $param = [
                                    'dck_id' => $dck_info['dck_id'],
                                    'book_id' => $dck_info['book_id'],
                                    'op_desc' => '[#],'.$tsg_code_dest.$tsg_site_code.$lt_type
                                ];

                                $is_success = DcLog::addlog(DcLog::OP_TYPE_BATCH_DISPATCH, $this->adminInfo, $param);

                                if ($is_success === false) {
                                    Db::rollback();
                                    $this->success('条码' . $barcode . '日志记录失败','',$book_info);
                                } else {
                                    $zch_data = array("tsg_code" => $tsg_code_dest);

                                    $is_success = Zch::update($zch_data,['dck_id'=>$dck_info['dck_id']])->result;

                                    if ($is_success !== false) {
                                        Db::commit();
                                        $this->success('条码' . $barcode . '调拨成功', '', $book_info);
                                    } else {
                                        Db::rollback();
                                        $this->success('条码'.$barcode.'调拨失败！错误提示:更新种次号数据失败!','',$book_info);
                                    }
                                }
                            } else {
                                Db::rollback();
                                $this->success('条码' . $barcode . '调拨失败:更新数据库失败','',$book_info);
                            }
                        }
                    }
                }
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }

        }

        $tsg_list_old = [
            $this->adminInfo['tsg_code'] => $tsg_list[$this->adminInfo['tsg_code']]
        ];

        $lt_type_list = Ltype::getMap('ltype_code', 'ltype_name');

        $this->assign('tsg_list', $tsg_list);
        $this->assign('tsg_list_old',$tsg_list_old);
        $this->assign('tsg_site_map',$tsg_site_map);
        $this->assign('lt_type_list', $lt_type_list);
        return view();
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *批量调拨
     */
    public function batch_dispatchAction()
    {
        $tsg_list = Tsg::getMap('tsg_code', 'tsg_name');
        $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name');

        if ($this->isPost){
            try{
                $barcode_list = input('barcode_list') ?: '';
                $tsg_site_code = input('tsg_site_code') ?: '';
                $tsg_code_sour = input('tsg_code_sour') ?: '';
                $tsg_code_dest = input('tsg_code_dest') ?: '';
                $lt_type = input('lt_type') ?: '';

                if (empty($barcode_list)) {
                    $this->error('批量调拨的图书条码不能为空');
                }

                $barcode_list = explode("\n", $barcode_list);


                foreach ($barcode_list as $item ) {
                    $dck_info = Dck::field("dck_id")
                        ->where(['tsg_code'=>$tsg_code_sour,'barcode'=>$item])->find();

                    if ($dck_info) {
                        Db::startTrans();
                        $dc_dispatch_model = new DcDispatch();
                        $is_success = $dc_dispatch_model
                            ->disOne($dck_info["dck_id"], $tsg_code_dest, $tsg_site_code, $lt_type, $this->_user_info, $tsg_list, $tsg_site_map);
                        if ($is_success === false) {
                            Db::rollback();
                            $this->error('批量调拨错误:'.$dc_dispatch_model->getError());
                        }
                    }
                }
                Db::commit();
                $this->success('批量调拨成功');
            }catch (Exception $e){
                $this->error($e->getMessage());
            }
        }
        $tsg_list = Tsg::getMap('tsg_code', 'tsg_name');
        $tsg_site_map = TsgSite::getMap('tsg_site_code','site_name');
        $tsg_list_old = [
            $this->adminInfo['tsg_code'] => $tsg_list[$this->adminInfo['tsg_code']]
        ];

        $lt_type_list = Ltype::getMap('ltype_code', 'ltype_name');

        $this->assign('tsg_list', $tsg_list);
        $this->assign('tsg_list_old',$tsg_list_old);
        $this->assign('tsg_site_map',$tsg_site_map);
        $this->assign('lt_type_list', $lt_type_list);
        return view();
    }

    /**
     * @return \think\response\View|void
     * 分馆信息显示
     */
    public function tsgInfoAction()
    {
        if ($this->isAjax){
            try{
                $tsg_map = Tsg::getMap('tsg_code','tsg_name');
                $group = 'tsg_code';
                $list_default = ['book_cnt'=>0,'dck_all_cnt'=>0,'dck_all_money'=>0,'dz_cnt'=>0,'dz_valid_cnt'=>0,'lend_cnt'=>0,'lend_valid_cnt'=>0];
                $dck_list = Dck::field("count(DISTINCT book_id) as book_cnt,count(0) as dck_all_cnt,sum(price) as dck_all_money,tsg_code")
                    ->group($group)->select();
                $dck_list = collection(array_under_reset($dck_list, $group))->toArray();

                $dz_list = Dzgl::field('tsg_code,count("dz_id") as dz_cnt')->group($group)->select();
                $dz_list =  collection(array_under_reset($dz_list, $group))->toArray();

                $dz_where = ['dz_status'=>'有效'];
                $dz_valid_list  = Dzgl::field('tsg_code,count(dz_id) as dz_valid_cnt')->where($dz_where)->group($group)->select();
                $dz_valid_list = collection(array_under_reset($dz_valid_list, $group))->toArray();
                $lend_list = Lend::field('tsg_code,count(lend_id) as lend_cnt')->group($group)->select();
                $lend_list = collection(array_under_reset($lend_list, $group))->toArray();
                $lend_where = ['lend_status' => Lend::LEND_STATUS_ON];
                $lend_valid_list = Lend::field('tsg_code,count(lend_id) as lend_valid_cnt')->where($lend_where)->group($group)->select();
                $lend_valid_list = collection(array_under_reset($lend_valid_list, $group))->toArray();

                $list = [];
                $i = 0;
                foreach ($tsg_map as $key => $value){
                    $list[$i] = array_merge($list_default,$dck_list[$key]?:[],$dz_list[$key]?:[],$dz_valid_list[$key]?:[],
                        $lend_list[$key]?:[],$lend_valid_list[$key]?:[]);
                    isset($list[$i]) ? $list[$i][$group] = $key.'|'.$value : $list[$i] = [];
                    $i++;
                }
                cache('excel_tsg_list', $list,600);
                return $this->echoPageData($list,count($tsg_map));
            }catch (Exception $e){
                $this->alertMsg($e->getMessage());
            }
        }
        return view('tsg_info');
    }

    /**
     * 缓存 --- excel
     */
    public function export_excel_tsgAction()
    {
        $list = cache('excel_tsg_list') ?: [];
        cache('excel_tsg_list', null);
        $this->export_excel_tsg($list);
        exit();
    }

    /**
     * @param $datalist
     *导出图书清点excel
     */
    private function export_excel_tsg(&$datalist)
    {
        import("phpExport\phpExport",EXTEND_PATH,'.class.php');
        $exporter = new \ExportDataExcel("browser", l("tsg_list").".xls");
        $exporter->initialize();
        $fields = array(
            'tsg_code'=>'分馆名称',
            'book_cnt'=>'馆藏种数',
            'dck_all_cnt'=>'馆藏总册数(含期刊)',
            'dck_all_money'=>'馆藏总金额',
            'dz_cnt'=>'读者总数',
            'dz_valid_cnt'=>'有效读者数',
            'lend_cnt'=>'借书总数',
            'lend_valid_cnt'=>'当前借书数'
            );
        $head_list = array();

        foreach ($fields as $item ) {
            $head_list[] = $item;
        }

        $exporter->addRow($head_list);

        foreach ($datalist as $item ) {
            $data_row = array();

            foreach ($fields as $key1 => $item1 ) {
                $val_tmp = (isset($item[$key1]) ? $item[$key1] : "");
                $data_row[] = $val_tmp;
            }

            $exporter->addRow($data_row);
            $data_row = array();
        }

        $exporter->finalize();
        exit();
    }

}
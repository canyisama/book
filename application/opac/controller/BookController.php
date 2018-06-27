<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/16
 * Time: 14:26
 */

namespace app\opac\controller;


use app\admin\model\Finance;
use app\admin\model\Reser;
use app\admin\model\Tsg;
use app\admin\model\Lend;
use app\opac\model\BookCollect;
use app\admin\model\LendReser;
use app\opac\model\OpacLog;
use think\Db;
use think\Exception;

class BookController extends UserBaseController
{
    public function indexAction()
    {
        return view();
    }

    /**
     * table
     */
    public function getListAction()
    {
        $source = input('source/s');
        $condition['dz_id'] = $this->_dz_info['dz_id'];
        $params = $this->getQueryParams();//分页,排序,查询参数

        switch ($source){
            case 'book_collect':
                $list = BookCollect::getPageList($condition, $params->limit, $params->order);
                $count = BookCollect::getCount($condition);
                break;
            case 'lend':
                $condition['lend_status'] = Lend::LEND_STATUS_ON;
                $list = Lend::getPageList($condition, $params->limit, $params->order);
                $count = Lend::getCount($condition);
                break;
            case 'lend_history':
                $condition['lend_status'] = Lend::LEND_STATUS_OFF;
                $list = Lend::getPageList($condition, $params->limit, $params->order);
                $count = Lend::getCount($condition);
                break;
            case 'reser':
                Reser::clearTimeout();
                $condition['reser_status'] = ['in',[Reser::RESER_STATUS_ADD,Reser::RESER_STATUS_BOOK,Reser::RESER_STATUS_NOITE]];
                $list = Reser::getPageList($condition, $params->limit, $params->order);
                $count = Reser::getCount($condition);
                break;
            case 'reser_history':
                Reser::clearTimeout();
                $condition['reser_status'] = ['in',[Reser::RESER_STATUS_OUT, Reser::RESER_STATUS_CANCEL, Reser::RESER_STATUS_FINISH]];
                $list = Reser::getPageList($condition, $params->limit, $params->order);
                $count = Reser::getCount($condition);
                break;
            case 'lend_reser':
                LendReser::clearTimeout();
                $condition['lend_reser_status'] = ['in',[LendReser::LEND_RESER_STATUS_ADD]];
                $list = LendReser::getPageList($condition, $params->limit, $params->order);
                $count = LendReser::getCount($condition);
                break;
            case 'lend_reser_history':
                LendReser::clearTimeout();
                $condition['lend_reser_status'] = ['in',[LendReser::LEND_RESER_STATUS_OUT, LendReser::LEND_RESER_STATUS_CANCEL, LendReser::LEND_RESER_STATUS_FINISH]];
                $list = LendReser::getPageList($condition, $params->limit, $params->order);
                $count = LendReser::getCount($condition);
                break;
            case 'lend_out':
                $condition['fee_mode'] = Finance::FEE_MODE_LEND_OUT;
                $list = Finance::getPageList($condition, $params->limit, $params->order);
                $count = Finance::getCount($condition);
                break;
            case 'lend_lose':
                $condition['fee_mode'] = Finance::FEE_MODE_LEND_LOSE;
                $list = Finance::getPageList($condition, $params->limit, $params->order);
                $count = Finance::getCount($condition);
                break;
            case 'lend_dirty':
                $condition['fee_mode'] = Finance::FEE_MODE_LEND_DIRTY;
                $list = Finance::getPageList($condition, $params->limit, $params->order);
                $count = Finance::getCount($condition);
                break;
            case 'finan_list':
                $list = Finance::getPageList($condition, $params->limit, $params->order);
                $count = Finance::getCount($condition);
                break;
            case 'login_list':
                $list = OpacLog::getPageList($condition, $params->limit, $params->order);
                $count = OpacLog::getCount($condition);
                break;
            default :
                $list = '';
                $count = 0;
        }

        if (isset($list[0]['tsg_code'])){
            $tsg_map = Tsg::getMap('tsg_code', 'tsg_name');
            foreach ($list as &$item){
                $item['tsg_code'] = $tsg_map[$item['tsg_code']];
            }
            unset($item);
        }

        return $this->echoPageData($list, $count);
    }

    /********************************************
     * 图书收藏
     ********************************************/
    /**
     * @return \think\response\View
     * 主页面
     */
    public function collect_listAction()
    {
        return view();
    }

    /**
     * 删除
     */
    public function dropCollectAction()
    {
        $book_collect_id = input('book_collect_id/d')?:0;
        if ($book_collect_id){
            $is_success = BookCollect::destroy($book_collect_id);
            if ($is_success !== false){
                $this->success('取消收藏成功');
            }
        }
        $this->error('取消收藏失败');
    }


    /**********************************************
     * 借阅管理
     ******************************************/

    public function lendAction()
    {
        return view();
    }

    /**
     * @return \think\response\View
     * 预约图书
     */
    public function reserAction()
    {
        if ($this->isPost){
            try{
                $book_id = input('post.book_id/d');
                $dz_code = input('post.dz_code/d');
                $tsg_code = input('post.tsg_code/d');
                Db::startTrans();
                $reserve_model = new Reser();
                $is_success = $reserve_model ->bookReserve($dz_code,$tsg_code,$book_id);

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
        return view();
    }

    /**
     * @return \think\response\View
     * 预借图书
     */
    public function lend_reserAction()
    {
        if ($this->isPost){
            try{
                $dck_id = input('post.dck_id/d');
                $dz_code = input('post.dz_code/d');

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
        return view();
    }

    public function lend_historyAction()
    {
        return view();
    }

    public function reser_historyAction()
    {
        return view();
    }

    public function lend_reser_historyAction()
    {
        return view();
    }

    /**
     * 取消预约
     */
    public function dropReserAction()
    {
        try{
            $reserve_id = input('reserve_id/d');
            if (empty($reserve_id)){
                $this->error('id为空');
            }
            Db::startTrans();
            $reserve_model = new Reser();
            $is_success = $reserve_model->dropReserve($reserve_id);
            if ($is_success === false){
                Db::rollback();
                $this->error($reserve_model->getError());
            }
            Db::commit();
            $this->success('取消预约成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    /**
     * 取消预借
     */
    public function dropLendReserAction()
    {
        try{
            $reserve_id = input('reserve_id/d');
            if (empty($reserve_id)){
                $this->error('id为空');
            }
            Db::startTrans();
            $reserve_model = new LendReser();
            $is_success = $reserve_model->dropLendReserve($reserve_id);
            if ($is_success === false){
                Db::rollback();
                $this->error($reserve_model->getError());
            }
            Db::commit();
            $this->success('取消预借成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    public function lend_outAction()
    {
        return view();
    }

    public function lend_loseAction()
    {
        return view();
    }

    public function lend_dirtyAction()
    {
        return view();
    }

    public function finan_listAction()
    {
        return view();
    }

    public function login_listAction()
    {
        return view();
    }
}
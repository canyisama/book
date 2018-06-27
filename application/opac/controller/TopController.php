<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/18
 * Time: 16:30
 */

namespace app\opac\controller;


use app\admin\model\Annou;
use app\admin\model\LendReser;
use app\opac\model\BookCollect;
use app\admin\model\Lend;
use app\admin\model\Reser;
use app\admin\model\Tsg;
use think\Exception;
use think\Request;

class TopController extends BaseController
{
    public function lend_msgAction()
    {
        return view();
    }

    /**
     * table
     */
    public function getListAction()
    {
        $source = input('source/s');
        $params = $this->getQueryParams();//分页,排序,查询参数
        if (cookie('tsg_code')){
            $condition['tsg_code'] = cookie('tsg_code');
        }

        if ($params->search){
            foreach ($params->search as $search){
                $condition[$search['field']] = ['like',['%'.$search['value'].'%']];
            }
        }

        switch ($source){
            case 'lend_msg':
                $condition['lend_status'] = Lend::LEND_STATUS_ON;
                $condition['must_time'] = ['< time',time()];
                $list = Lend::getPageList($condition, $params->limit, $params->order);
                $count = Lend::getCount($condition);
                break;
            case 'reser_msg':
                $condition['reser_status'] = ['in',[Reser::RESER_STATUS_BOOK, Reser::RESER_STATUS_NOITE]];
                $list = Reser::getPageList($condition, $params->limit, $params->order);
                $count = Reser::getCount($condition);
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

    public function reser_msgAction()
    {
        return view();
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 馆内公告列表清单
     */
    public function msgAction()
    {
        if (cookie('tsg_code')){
            $condition['tsg_code'] = cookie('tsg_code');
        }

        $order = 'add_time desc';
        $page_size = 10;
        $fields = "annou_id,subject,body,add_time,tsg_code";
        $annou_list = Annou::field($fields)->where($condition)->order($order)->paginate($page_size);
        $this->assign("list", $annou_list);
        $this->assign('page',$annou_list->render());
        $tsg_map = Tsg::getMap('tsg_code','tsg_name');
        $this->assign("tsg_map", $tsg_map);
        return view();
    }

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *馆内公告详细显示
     */
    public function msg_viewAction()
    {
        $annou_id = input('get.annou_id/d')?:0;
        $where["annou_id"] = $annou_id;
        $fields = "annou_id,subject,body,add_time,tsg_code";
        $annou_info = Annou::field($fields)->where($where)->find();
        $annou_info['tsg_name'] = Tsg::where(['tsg_code' => $annou_info['tsg_code']])->value('tsg_name');
        $this->assign("info", $annou_info);
        return view();
    }

    /**
     * @return \think\response\View
     * 借阅排行榜
     */
    public function lendAction()
    {
        try{
            $fields = "count(lend_id) as lend_cnt,book_id";
            $book_fields = "lib_book.book_id,lib_book.tsg_code,title,isbn,clc,firstauthor,publisher,pubdate,price_ms,bl_title,othertitle,fjno,fjtitle,is_verify,";
            $book_fields .= "CONCAT(title,IF(bl_title!='','=',''),bl_title,IF(othertitle!='',':',''),othertitle,IF(fjno!='','.',''),fjno,IF(fjtitle!='',',',''),fjtitle) as title_all";

            $where = array("lend_status" => Lend::LEND_STATUS_OFF);

            if (cookie('tsg_code')) {
                $where["tsg_code"] = cookie('tsg_code');
            }
            $page_size = 10;
            $join = [
                ['lib_index_tsg_code','book_id=bid']
            ];
            $count = Lend::join($join)->where($where)->count("DISTINCT(bid)");
            $count = ($count <= 100 ? $count : 100);

            $lend_list = Lend::join($join)->with(['book'=>function($query) use($book_fields){
                $query->field($book_fields);
            }])
                ->field($fields)
                ->where($where)
                ->group("book_id")->order("lend_cnt desc")->paginate($page_size,$count,['query'=>Request::instance()->get()]);
            if($lend_list){
                $coll_ids = [];

                if (isset($this->_dz_info['dz_id']) && $this->_dz_info['dz_id']){
                    $coll_list = BookCollect::field('book_id')->where(['dz_id'=>$this->_dz_info['dz_id']])->select();

                    foreach ($coll_list as $item ) {
                        $coll_ids[] = $item["book_id"];
                    }
                }
                $i = (input('page/d',1)-1)*$page_size+1;
                foreach ($lend_list as $key => $item ) {
                    $lend_list[$key]["is_coll"] = (in_array($item["book_id"], $coll_ids) ? 1 : 0);
                    $lend_list[$key]["top_num"] = $i;
                    $i++;
                }
            }
            $this->assign("list", $lend_list);
            $this->assign("page", $lend_list->render());
            return view();
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * @return \think\response\View
     * 收藏排行榜
     */
    public function collectAction()
    {
        try{
            $fields = "count(book_collect_id) as collect_cnt,book_id";
            $book_fields = "lib_book.book_id,lib_book.tsg_code,title,isbn,clc,firstauthor,publisher,pubdate,price_ms,bl_title,othertitle,fjno,fjtitle,is_verify,";
            $book_fields .= "CONCAT(title,IF(bl_title!='','=',''),bl_title,IF(othertitle!='',':',''),othertitle,IF(fjno!='','.',''),fjno,IF(fjtitle!='',',',''),fjtitle) as title_all";

            $where = [];

            $page_size = 10;
            $join = [
                ['lib_index_tsg_code','book_id=bid']
            ];
            $count = BookCollect::join($join)->where($where)->count("DISTINCT(bid)");
            $count = ($count <= 100 ? $count : 100);

            $collect_list = BookCollect::join($join)->with(['book'=>function($query) use($book_fields){
                $query->field($book_fields);
            }])
                ->field($fields)
                ->where($where)
                ->group("book_id")->order("collect_cnt desc")->paginate($page_size,$count,['query'=>Request::instance()->get()]);

            if($collect_list){
                $coll_ids = [];
                if (isset($this->_dz_info['dz_id']) && $this->_dz_info['dz_id']){
                    $coll_list = BookCollect::field('book_id')->where(['dz_id'=>$this->_dz_info['dz_id']])->select();

                    foreach ($coll_list as $item ) {
                        $coll_ids[] = $item["book_id"];
                    }
                }
                $i = (input('page/d',1)-1)*$page_size+1;

                foreach ($collect_list as $key => $item ) {
                    $collect_list[$key]["is_coll"] = (in_array($item["book_id"], $coll_ids) ? 1 : 0);
                    $collect_list[$key]["top_num"] = $i;
                    $i++;
                }
            }
            $this->assign("list", $collect_list);
            $this->assign("page", $collect_list->render());
            return view();
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

}
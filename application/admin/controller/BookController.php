<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 11:40
 */

namespace app\admin\controller;


use app\admin\model\BmLog;
use app\admin\model\Book;
use app\admin\model\Destine;
use app\admin\model\Qk;
use app\admin\model\Ys;

class BookController extends BaseController
{

    /**
     * 直接预定(source:destine),直接验收(source:ys),编目录入(source:dck)  通用页面
     * @return \think\response\View
     */
    public function indexAction()
    {
        $source = input('source');
        $this->assign('source', $source);
        // 判断是否是本馆图书
        $this->assign('tsg_code', $this->adminInfo['tsg_code']);
        return view();
    }

    public function getJsonListAction()
    {
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Book::getPageList($condition, $params->limit, $params->order);
        $count = Book::where($condition)->count();
        $book_ids = [];
        foreach ($list as $item) {
            $book_ids[] = $item['book_id'];
        }
        $ys_count_list = Ys::where(['book_id' => ['in', $book_ids], 'tsg_code' => $this->adminInfo['tsg_code']])->field('book_id,COUNT(book_id) as count')->group('book_id')->select();
        $ys_count_list = array_under_reset($ys_count_list, 'book_id');
        $destine_count_list = Destine::where(['book_id' => ['in', $book_ids], 'tsg_code' => $this->adminInfo['tsg_code']])->field('book_id,COUNT(book_id) as count')->group('book_id')->select();
        $destine_count_list = array_under_reset($destine_count_list, 'book_id');

        $where_qk = [
            'tsg_code' => $this->adminInfo['tsg_code'],
            'book_id' => ['in', $book_ids],
            'status' => QkController::STATUS_YD
        ];
        $qk_count_list = Qk::where($where_qk)->field('book_id,COUNT(book_id) as count')->group('book_id')->select();
        $qk_count_list = array_under_reset($qk_count_list, 'book_id');
        foreach ($list as &$item) {
            $item['ys_count'] = intval($ys_count_list[$item['book_id']]['count']);   // 图书验收数量
            $item['destine_count'] = intval($destine_count_list[$item['book_id']]['count']);   // 图书预订数量
            $item['qk_count'] = intval($qk_count_list[$item['book_id']]['count']); // 期刊数量
        }
        unset($item);
        return $this->echoPageData($list, $count);
    }

}
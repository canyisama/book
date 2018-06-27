<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 9:20
 */

namespace app\admin\controller;


class ClcsearchController extends BaseController
{

    public function indexAction()
    {
        return view();
    }

    public function getJsonListAction()
    {
        $clc_str = input('clc', '');
        if (!$clc_str) {
            return;
        }
        $book_list = array();

        if ($clc_str) {
            $params = $this->getQueryParams();//分页,排序,查询参数

            $mod_book = d("Book");
            $where = array();
            $where["lib_index_tsg_code.val"] = $this->_user_info["tsg_code"];
            $where["lib_index_clc_order.val"] = array("like", $clc_str . "%");
            $table_str = "lib_index_clc_order INNER JOIN lib_index_tsg_code on lib_index_clc_order.bid=lib_index_tsg_code.bid";

            $count_sql = "select count(*) as count from " . $table_str . ' where lib_index_tsg_code.val=' . $this->_user_info['tsg_code'] . " and lib_index_clc_order.val like '{$clc_str}%' ";
            $select_sql = "select lib_index_clc_order.bid from " . $table_str . ' where lib_index_tsg_code.val=' . $this->_user_info['tsg_code'] . " and lib_index_clc_order.val like '{$clc_str}%' limit " . $params->limit;
            $count = $mod_book->query($count_sql);
            $count = $count ? $count[0]['count'] : 0;
            $bid_list = $mod_book->query($select_sql);

            $bid_arr = array();
            foreach ($bid_list as $item) {
                $bid_arr[] = $item["bid"];
            }
            if ($bid_arr) {
                $where_book = ["book_id" => ["in", $bid_arr]];
                $book_id_str = implode(",", $bid_arr);
                $book_list = $mod_book->where($where_book)->orderRaw("FIND_IN_SET(book_id,'$book_id_str')")->select();
            }
            return $this->echoPageData($book_list, $count);
        }
    }

    public function getClcListAction()
    {
        $node_id = input('id/d');
        $clc_list = d("Clc")->getListByParent($node_id);
        return json($clc_list);
    }

}
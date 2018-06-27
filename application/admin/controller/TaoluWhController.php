<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 17:00
 */

namespace app\admin\controller;


use app\admin\model\BmLog;
use think\Exception;

class TaoluWhController extends BaseController
{

    public function indexAction()
    {
        $mod_user = d("User");
        $op_user_list = $mod_user->field("user_id,user_name")->where("belong_tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $this->assign("op_user_list", $op_user_list);
        return view();
    }

    public function getJsonListAction()
    {
        $cmd = input('cmd', '');
        $mod_book_share = d("Book_share");
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $search_tj = array();
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $search_tj[$search['field']] = $search['value'];
            }
        }

        if (!empty($search_tj["add_time_beg"]) || !empty($search_tj["add_time_end"])) {
            import('DataTool\DataTool', EXTEND_PATH, '.class.php');
            $date_arr = \DataTool::getDateBetween($_GET, "add_time_beg", "add_time_end");
            $search_tj["add_time"] = $date_arr;
            unset($search_tj["add_time_beg"]);
            unset($search_tj["add_time_end"]);
        }

        $str_field = array("title", "isbn", "firstauthor", "publisher", "clc");

        foreach ($search_tj as $key => $item) {
            if (in_array($key, $str_field)) {
                $where[$key] = trim($item);
            } else if ($key == "op_user") {
                $where[$key] = $item;
            } else if ($key == "add_time") {
                $where[$key] = $item;
            }
        }
        $share_fields = "book_share_id,title,isbn,clc,firstauthor,publisher,pubplace,pubdate,price_ms,bl_title,othertitle,fjno,fjtitle,op_user,add_time";
        $search_share = array("field" => $share_fields, "where" => $where,
            "user_id" => $this->_user_info["user_id"],
            "order_field" => input('sort'), "order_seq" => input('order'),
            "page" => input('offset/d') / input('limit/d') + 1,
            "page_size" => input('limit/d'));

        if ($cmd == "drop_query") {
            $is_success = $mod_book_share->fullSearch($search_share, 3);

            if ($is_success === false) {
                $this->error('删除失败！错误提示:' . $mod_book_share->getError());
            } else {
                $mod_bm_log = d("Bm_log");
                $mod_bm_log->addlog(BmLog::OP_TYPE_TAOLU_WH_BAT, $this->_user_info, array("db1" => 0));
                $this->success('删除本次查询结果成功！');
            }
        }
        $count = $mod_book_share->fullSearch($search_share, 2);

        if ($cmd == "cnt") {
            $this->result($count, 1);
        }
        $book_share_list = $mod_book_share->fullSearch($search_share);
        $this->echoPageData($book_share_list, $count);
    }

    public function dropAction()
    {
        $book_share_id = input('book_share_id/d');
        try {
            $mod_book_share = d("Book_share");
            $mod_book_share->startTrans();
            $is_success = $mod_book_share->where("book_share_id=$book_share_id")->delete();

            if ($is_success !== false) {
                $mod_user = d("User");
                $mt_id = $mod_user->get_mt_id($this->_user_info["user_id"]);
                $mod_indexrel = d("indexrel");
                $index_list = $mod_indexrel->field("dest_mod,order_mod")->where("sour_mod='Book_share' AND mt_id=$mt_id")->select();

                foreach ($index_list as $item) {
                    $mod_dest = d($item["dest_mod"]);
                    $is_success = $mod_dest->where("bid=$book_share_id")->delete();

                    if ($is_success === false) {
                        $mod_book_share->rollback();
                        $this->error('删除失败！错误提示:删除书目索引数据时遇到错误');
                    }

                    if ($item["dest_mod"] != $item["order_mod"]) {
                        $mod_order = d($item["order_mod"]);
                        $is_success = $mod_order->where("bid=$book_share_id")->delete();

                        if ($is_success === false) {
                            $mod_book_share->rollback();
                            $this->error('删除失败！错误提示:删除书目索引数据时遇到错误');
                        }
                    }
                }

                $mod_bm_log = d("Bm_log");
                $mod_bm_log->addlog(BmLog::OP_TYPE_TAOLU_WH_DEL, $this->_user_info, array("db1" => $book_share_id));
                $mod_book_share->commit();
                $this->success("删除成功！");
            } else {
                $mod_book_share->rollback();
                $this->error("删除失败！错误提示:" . $mod_book_share->getError());
            }
        } catch (Exception $e) {
            $mod_book_share->rollback();
            $this->error("删除失败！错误提示:程序出现异常!");
            return false;
        }
    }

    public function initTaoluAction()
    {
        try {
            $mod_book_share = d("Book_share");
            $mod_user = d("User");
            $mt_id = $mod_user->get_mt_id($this->_user_info["user_id"]);
            $mod_indexrel = d("indexrel");
            $mod_index_tsg_code_s = d("index_tsg_code_s");
            $index_list = $mod_indexrel->field("dest_mod,order_mod")->where("sour_mod='Book_share' AND mt_id=$mt_id")->select();
            $mod_book_share->startTrans();
            $ids_tmp = $mod_index_tsg_code_s->field("bid")->where("val='{$this->_user_info["tsg_code"]}'")->select();
            $ids_tmp = array_chunk($ids_tmp, 3000);
            if (!$ids_tmp || !$index_list) {
                $this->success('初始化套录库成功');
            }
            $task_cnt = 0;
            $task_per = 100 / (count($ids_tmp) * count($index_list));

            foreach ($ids_tmp as $item) {
                $ids = array();
                foreach ($item as $item1) {
                    $ids[] = $item1["bid"];
                }

                if (!empty($ids)) {
                    $ids = implode(",", $ids);
                    $is_success = $mod_book_share->where("book_share_id in($ids)")->delete();

                    if ($is_success === false) {
                        $mod_book_share->rollback();
                        $this->error('初始化套录库失败:更新数据库失败');
                    }

                    foreach ($index_list as $item) {
                        $mod_dest = d($item["dest_mod"]);
                        $is_success = $mod_dest->where("bid in($ids)")->delete();

                        if ($is_success === false) {
                            $mod_book_share->rollback();
                            $this->error('初始化套录库索引数据失败:更新数据库失败');
                        }

                        if ($item["dest_mod"] != $item["order_mod"]) {
                            $mod_order = d($item["order_mod"]);
                            $is_success = $mod_order->where("bid in($ids)")->delete();

                            if ($is_success === false) {
                                $mod_book_share->rollback();
                                $this->error('初始化套录库排序索引数据失败:更新数据库失败');
                            }
                        }
                        $task_cnt += $task_per;
                    }

                    $mod_bm_log = d("Bm_log");
                    $mod_bm_log->addlog(BmLog::OP_TYPE_TAOLU_WH_INIT, $this->_user_info, array("db1" => 0));
                    $mod_book_share->commit();
                }
            }
            $this->success('初始化套录库成功！');
            return true;
        } catch (Exception $e) {
            $mod_book_share->rollback();
            $this->error('初始化套录库失败:程序执行出现异常');
        }
    }

    public function marc_showAction()
    {
        $book_share_id = input('book_share_id/d');
        $mod_book_share = d("Book_share");
        $book_share_info = $mod_book_share->field("marc")->where("book_share_id=$book_share_id")->find();

        if (empty($book_share_info)) {
            echo "数据库不存在此条套录数据!";
            exit();
        }

        import('Marc\MARC', EXTEND_PATH, '.class.php');
        $marc_arr = \MARC::readMarcByStr($book_share_info["marc"]);
        $marc_obj = new \MARC("");
        $marc_obj->setData($marc_arr);
        $marc_str = $marc_obj->toString("", array("zsf_replace" => "_", "field_space_replace" => "&nbsp;", "field_head_replace" => "§"));
        $marc_str = nl2br($marc_str);
        $this->assign("marc_str", $marc_str);
        return view();
    }

}
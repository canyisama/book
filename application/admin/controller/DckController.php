<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 11:43
 */

namespace app\admin\controller;


use app\admin\model\Book;
use app\admin\model\Dck;
use app\admin\model\Destine;
use app\admin\model\Ltype;
use app\admin\model\Mt;
use app\admin\model\QkLog;
use app\admin\model\Tsg;
use app\admin\model\TsgSite;
use app\admin\model\Ys;
use app\admin\model\Zch;
use think\Exception;
use think\Lang;

class DckController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/dck.php']);
    }

    public function indexAction()
    {

    }

    public function getJsonListAction()
    {
        $book_id = input('book_id/d');
        $mod_dck = d("Dck");
        $where = array();
        $where["book_id"] = $book_id;
        $where_str = 'book_id=' . $book_id;
        if (cookie('show_all_tsg') != 1) {
            $where["lib_dck.tsg_code"] = $this->_user_info["tsg_code"];
            $where_str .= ' and lib_dck.tsg_code=' . $this->_user_info["tsg_code"];
        }

        $where["dt"] = array("eq", Dck::DT_TYPE_BOOK);
        $where_str .= ' and dt=' . Dck::DT_TYPE_BOOK;
        $order = "dck_id";
        $fields = "dck_id,book_id,barcode,price,price_sum,lib_dck.tsg_code,tsg_code_has,calino,is_close,add_time,status,lib_dck.tsg_site_code,lt_type,site_name";
        //$dck_list = $mod_dck->table("lib_dck LEFT JOIN lib_tsg_site ON lib_dck.tsg_code=lib_tsg_site.tsg_code AND lib_dck.tsg_site_code=lib_tsg_site.tsg_site_code")->field($fields)->where($where)->order($order)->select();
        $select_sql = "select {$fields} from lib_dck 
                      left join lib_tsg_site ON lib_dck.tsg_code=lib_tsg_site.tsg_code AND lib_dck.tsg_site_code=lib_tsg_site.tsg_site_code
                      WHERE {$where_str}
                      ORDER BY dck_id ";
        $dck_list = $mod_dck->query($select_sql);
        $dck_cnt_all = 0;
        $dck_cnt_self = 0;
        $tsg_code_list = [];
        $ltype_code_list = [];
        $tsg_site_code_list = [];
        foreach ($dck_list as $item) {
            $dck_cnt_all++;
            if ($item["tsg_code"] == $this->_user_info["tsg_code"]) {
                $dck_cnt_self++;
            }
            $tsg_code_list[] = $item['tsg_code'];
            $tsg_code_list[] = $item['tsg_code_has'];
            $ltype_code_list[] = $item['lt_type'];
            $tsg_site_code_list[] = $item['tsg_site_code'];
        }
        $tsg_list = Tsg::where(['tsg_code' => ['in', $tsg_code_list]])->select();
        $tsg_list = array_under_reset($tsg_list, 'tsg_code');
        $lt_type_list = Ltype::where(['tsg_code' => $this->adminInfo['tsg_code'], 'ltype_code' => ['in', $ltype_code_list]])->select();
        $lt_type_list = array_under_reset($lt_type_list, 'ltype_code');
        $tsg_site_list = TsgSite::where(['tsg_code' => $this->adminInfo['tsg_code'], 'tsg_site_code' => ['in', $tsg_site_code_list]])->select();
        $tsg_site_list = array_under_reset($tsg_site_list, 'tsg_site_code');

        foreach ($dck_list as $key => $item) {
            $item['lt_type'] .= ' | ' . $lt_type_list[$item['lt_type']]['ltype_name'];
            $item['tsg_code'] .= ' | ' . $tsg_list[$item['tsg_code']]['tsg_name'];
            $item['tsg_site_code'] .= ' | ' . $tsg_site_list[$item['tsg_site_code']]['site_name'];
            $item['tsg_code_has'] .= ' | ' . $tsg_list[$item['tsg_code_has']]['tsg_name'];
            $item['add_time'] = fmt_date_time($item['add_time']);
            $dck_list[$key] = $item;
        }
        $this->echoPageData($dck_list, 0, ['dck_cnt_all' => $dck_cnt_all, 'dck_cnt_self' => $dck_cnt_self]);
    }

    public function addAction()
    {

        $book_id = input('book_id/d');
        $dt = input('dt/d', Dck::DT_TYPE_BOOK);

        if ($this->isPost) {
            import('Marc\MARC', EXTEND_PATH, '.class.php');
            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');

            $mod_dck = d("Dck");
            $add_list = input('dck_data/a');
            if (!$add_list) {
                $this->error("新增提交的数据为空!");
            }

            $mod_book = d("Book");
            $book_info = $mod_book->field("lib_book.book_id,firstauthor,mt_id,marc")->where("book_id=$book_id")->find();
            if (!$book_info) {
                $this->error("书目信息不存在,无法增加馆藏!");
            }

            $marc_obj = new \MARC();
            $mdata = \MARC::readMarcByStr($book_info["marc"]);
            $marc_obj->setData($mdata);
            $rel_arr = $marc_obj->getRelArray();
            $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);
            $mod_tsg = d("Tsg");

            $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
            $static_zch_obj = array();
            $mod_zch = d("zch");
            $barcode_list = array();

            foreach ($add_list as $key => $item) {
                if (empty($item["barcode"])) {
                    $this->error("有图书条码为空,请修改后再提交!");
                }
                if (!empty($tsg_info["barcode_len"])) {
                    if (strlen($item["barcode"]) != $tsg_info["barcode_len"]) {
                        $this->error("图书条码{$item["barcode"]}长度错误,已设置条码长度为{$tsg_info["barcode_len"]},请修改后再提交!");
                    }
                }
                $barcode_list[] = $item["barcode"];
            }

            $tmp_arr = array();
            $repeat_arr = array();
            foreach ($barcode_list as $item) {
                if (in_array($item, $tmp_arr)) {
                    $repeat_arr[] = $item;
                } else {
                    $tmp_arr[] = $item;
                }
            }
            if (!empty($repeat_arr)) {
                $this->error("提交的数据中有图书条码" . implode("、", $repeat_arr) . "重复,请修改后再提交!");
            }
            $is_unique = $mod_dck->isUniqueBarcode($this->_user_info["tsg_code"], $barcode_list);
            if ($is_unique === false) {
                $tmp_arr1 = $mod_dck->errorData["code_list"];
                $this->error($mod_dck->errorData["msg"] . ",重复条码:" . implode("、", $tmp_arr1));
            }

            $add_time_curr = time();
            $clc_list = array();
            foreach ($add_list as $key => $item) {
                $add_list[$key]["dt"] = $dt;
                $add_list[$key]["book_id"] = $book_id;
                $add_list[$key]["tsg_code"] = $this->_user_info["tsg_code"];
                $add_list[$key]["tsg_code_has"] = $this->_user_info["tsg_code"];
                $add_list[$key]["tsg_site_code_has"] = ($item["tsg_site_code"] ? $item["tsg_site_code"] : "");
                $add_list[$key]["add_time"] = time();
                $add_list[$key]["add_user"] = $this->_user_info["user_name"];
                $add_list[$key]["edit_time"] = $add_time_curr;
                $add_list[$key]["edit_user"] = $this->_user_info["user_name"];

                if (!empty($tsg_info["loginno_accord"])) {
                    $add_list[$key]["login_no"] = $item["barcode"];
                }
                $zch_add_data = array();
                if (isset($static_zch_obj[$item["calino"]])) {
                    $calino_obj = $static_zch_obj[$item["calino"]];
                } else {
                    $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $item["calino"], "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));
                    if ($calino_obj->hasError()) {
                        $this->error($calino_obj->getError());
                    }
                    $static_zch_obj[$item["calino"]] = $calino_obj;
                }

                if (!$calino_obj->unique()) {
                    $this->error("索书号重复");
                }
                $add_list[$key]["calino"] = $calino_obj->autoCalino();
                $zch_add_data = array("calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"], "tsg_code" => $this->_user_info["tsg_code"], "clc" => $calino_obj->getClc(), $calino_obj->getDiffname($tsg_info["calino_type"]) => $calino_obj->getDiffCode(), "fzno" => $calino_obj->getFzno());
                $clc_list[] = $zch_add_data["clc"];
                $add_list[$key]["zch_add_data"] = $zch_add_data;
            }

            $dck_ids = array();
            try {
                $mod_dck->startTrans();
                foreach ($add_list as $item) {
                    $add_data = $item;
                    $zch_add_data = $item["zch_add_data"];
                    unset($add_data["zch_add_data"]);
                    $dck_id = $mod_dck->create($add_data,true)->getLastInsID();

                    if ($dck_id === false) {
                        $mod_dck->rollback();
                        $this->error("新增失败:插入馆藏数据失败");
                    }

                    $dck_ids[] = $dck_id;
                    $zch_add_data["dck_id"] = $dck_id;
                    $zch_id = $mod_zch->create($zch_add_data,true)->getLastInsID();
                    if ($zch_id === false) {
                        $mod_dck->rollback();
                        $this->error("种次号增加失败!");
                    }
                }
                $clc_list = array_unique($clc_list);
                \BookCalino::upMaxZch($this->_user_info["tsg_code"], $book_info["mt_id"], $clc_list);

                if ($dt == Dck::DT_TYPE_BOOK) {
                } else {
                    $dck_ids_str = implode(",", $dck_ids);
                    $barcode  = implode(',', $barcode_list);
                    QkLog::addLog(QkLog::OP_TYPE_ZD_ADD, $this->_user_info, ['book_id' => $book_id, 'db1' => $dck_ids_str, 'op_desc' => "[#],图书条码:",$barcode]);
                }

                $mod_dck->commit();
                $this->success("新增成功！");
            } catch (Exception $e) {
                $mod_dck->rollback();
                $this->error("新增失败！错误提示:" .$e->getMessage() .$mod_dck->getError());
            }
        }

        $this->dck_assign_common($book_id, "add");
        $this->assign("form_title", $dt == Dck::DT_TYPE_QK ? lang("qk_form_title_add") : lang("form_title_add"));


        if ($dt == Dck::DT_TYPE_QK) {
            return view('qkform');
        } else {
            return view('form');
        }
    }

    public function editAction()
    {
        $dck_id = input('dck_id/d');
        $dt = input('dt/d', Dck::DT_TYPE_BOOK);
        $mod_dck = d("Dck");
        $dck_info = $mod_dck->where("dck_id=$dck_id AND dt=$dt")->find();

        if (!$this->isPost) {
            if (!$dck_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if ($dck_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
            $this->assign("dck_info", $dck_info);
            $this->dck_assign_common($dck_info["book_id"], "edit");
            $this->assign("form_title", $dt == Dck::DT_TYPE_QK ? l("qk_form_title_edit") : l("form_title_edit"));

            if ($dt == Dck::DT_TYPE_QK) {
                return view('qk_form_edit');
                //$this->display("qk_form_edit");
            } else {
                return view('form_edit');
            }
        } else {
            if (!$dck_info) {
                $this->error(l("not_found_data"));
            }
            if ($dck_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(l("not_access_edit_data"));
            }

            $book_id = $dck_info["book_id"];
            if (empty($dck_info)) {
                $this->error('不存在的馆藏信息,无法更新!');
            }
            $mod_book = d("Book");
            $book_info = $mod_book->field("book_id,firstauthor,mt_id,marc")->where("book_id=$book_id")->find();
            if (!$book_info) {
                $this->error("书目信息不存在,无法更新馆藏!");
            }

            import('Marc\MARC', EXTEND_PATH, '.class.php');
            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');

            $marc_obj = new \MARC();
            $mdata = \MARC::readMarcByStr($book_info["marc"]);
            $marc_obj->setData($mdata);
            $rel_arr = $marc_obj->getRelArray();
            $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);

            $save_data = array(
                'barcode' => input('barcode'),
                'login_no' => input('login_no'),
                'calino' => input('calino'),
                'jzh' => input('jzh'),
                'tsg_site_code' => input('tsg_site_code'),
                'lt_type' => input('lt_type'),
                'status' => input('status'),
                'seller_code' => input('seller_code'),
                'price' => input('price'),
                'currency' => input('currency'),
                'price_sum' => input('price_sum'),
                'cost_code' => input('cost_code'),
                'book_sour' => input('book_sour'),
                'batch_no' => input('batch_no'),
                'jz_type' => input('jz_type'),
            );

            if (empty($save_data["barcode"])) {
                $this->error("图书条码为空,请修改后再提交!");
            }

            $mod_tsg = d("Tsg");
            $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();

            if (!empty($tsg_info["barcode_len"])) {
                if ($tsg_info["barcode_len"] < strlen($save_data["barcode"])) {
                    $this->error("图书条码{$save_data["barcode"]}长度错误,已设置条码长度为{$tsg_info["barcode_len"]},请修改后再提交!");
                }
            }

            $is_unique = $mod_dck->isUniqueBarcode($this->_user_info["tsg_code"], $save_data["barcode"], $dck_id);

            if ($is_unique === false) {
                $tmp_arr1 = $mod_dck->errorData["code_list"];
                $this->error($mod_dck->errorData["msg"] . ",重复条码:" . implode("、", $tmp_arr1));
                return false;
            }

            if (!empty($tsg_info["loginno_accord"])) {
                $save_data["login_no"] = $save_data["barcode"];
            }

            unset($save_data["tsg_code"]);
            unset($save_data["dt"]);
            $mod_zch = d("zch");
            $zch_add_data = array();
            $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $save_data["calino"], "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));

            if ($calino_obj->hasError()) {
                $this->error($calino_obj->getError());
            }

            if (!$calino_obj->unique()) {
                $this->error("索书号重复");
            }

            $save_data["calino"] = $calino_obj->autoCalino();
            $zch_add_data = array("calino_type" => $tsg_info["calino_type"], "tsg_code" => $this->_user_info["tsg_code"], "clc" => $calino_obj->getClc(), $calino_obj->getDiffname($tsg_info["calino_type"]) => $calino_obj->getDiffCode(), "fzno" => $calino_obj->getFzno());

            try {
                $mod_dck->startTrans();
                $is_ok = $mod_zch->update($zch_add_data, ['dck_id' => $dck_id])->result;

                if ($is_ok === false) {
                    $mod_dck->rollback();
                    $this->error("种次号更新失败!");
                }

                \BookCalino::upMaxZch($this->_user_info["tsg_code"], $book_info["mt_id"], array($zch_add_data["clc"]));
                $save_data["edit_time"] = time();
                $save_data["edit_user"] = $this->_user_info["user_name"];
                $is_success = $mod_dck->update($save_data, ['dck_id' => $dck_id])->result;

                if ($is_success === false) {
                    $mod_dck->rollback();
                    $this->error('编辑馆藏失败,请修改数据重新提交！');
                }

                $mod_qk_log = d("Qk_log");
                $mod_qk_log->addlog(QkLog::OP_TYPE_ZD_SAVE, $this->_user_info, array("book_id" => $book_id, "db1" => $dck_id, "op_desc" => "[#],图书条码:{$save_data["barcode"]}"));
                $mod_dck->commit();
                $this->result($save_data["calino"], 1, '更新馆藏数据成功');
            } catch (Exception $e) {
                $mod_dck->rollback();
                $this->error('更新馆藏信息失败！错误提示:' . $mod_dck->getError());
            }
        }
    }

    public function edit_batchAction()
    {
        $book_id = input('book_id/d');
        $dck_id = input('dck_id');
        $mod_dck = d("Dck");

        if (!$this->isPost) {
            if (!$book_id) {
                $this->alertMsg('无效的书目ID参数');
            }
            if (!$dck_id) {
                $this->alertMsg('无效的馆藏ID参数');
            }
            $this->dck_assign_common($book_id, 'edit');
            return view();
        } else {
            if (!$book_id) {
                $this->error('无效的书目ID参数');
            }
            if (!$dck_id) {
                $this->error('无效的馆藏ID参数');
            }

            $mod_zch = d("zch");
            $tmp_ids = explode(",", $dck_id);
            $ids = array();
            foreach ($tmp_ids as $key => $item) {
                if (!empty($item)) {
                    $ids[] = intval($item);
                }
            }

            if (empty($ids)) {
                $this->error('批量修改失败:无效的馆藏库ID参数!');
            }
            $ids = implode(",", $ids);
            $tmp_zdlist_arr = input('zdlist/a');
            $zdlist = array();

            foreach ($tmp_zdlist_arr as $key => $item) {
                if (!empty($item)) {
                    $item = trim($item);
                    $zdlist[$item] = $item;
                }
            }
            if (empty($zdlist)) {
                $this->error('请选择批修改字段');
            }
            $field_list = array("calino", "jzh", "tsg_site_code", "lt_type", "status", "seller_code", "price", "currency", "price_sum", "cost_code", "pro_code", "batch_no", "book_sour", "jz_type");
            $save_data = array();

            foreach ($zdlist as $item) {
                if (in_array($item, $field_list)) {
                    if (in_array($item, array("price", "price_sum"))) {
                        $val = (isset($_POST[$item]) ? floatval($_POST[$item]) : 0);
                    } else {
                        $val = (isset($_POST[$item]) ? trim($_POST[$item]) : "");
                    }
                    $save_data[$item] = $val;
                }
            }

            if (empty($save_data)) {
                $this->error('请选择有效的批修改字段');
            }
            if (isset($save_data["calino"]) && empty($save_data["calino"])) {
                $this->error('索书号不能批修改为空');
            }
            if (isset($save_data["price"]) && empty($save_data["price"])) {
                $this->error('单价不能批修改为空');
            }
            $mod_book = d("Book");
            try {
                $mod_dck->startTrans();
                if (isset($save_data["calino"])) {
                    import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
                    $book_info = $mod_book->field("book_id,mt_id,firstauthor,marc")->where("book_id=$book_id")->find();

                    if (empty($book_info)) {
                        $mod_dck->rollback();
                        $this->error('馆藏所属书目信息未找到');
                    }

                    import('Marc\MARC', EXTEND_PATH, '.class.php');
                    $marc_obj = new \MARC();
                    $mdata = \MARC::readMarcByStr($book_info["marc"]);
                    $marc_obj->setData($mdata);
                    $rel_arr = $marc_obj->getRelArray();
                    $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);
                    $mod_tsg = d("Tsg");
                    $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
                    $mod_zch = d("zch");
                    $zch_add_data = array();
                    $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $save_data["calino"], "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));

                    if ($calino_obj->hasError()) {
                        $this->error('索书号查重出现错误:' . $calino_obj->getError());
                    }
                    if (!$calino_obj->unique()) {
                        $this->error("索书号重复！");
                    }

                    $save_data["calino"] = $calino_obj->autoCalino();
                    $zch_add_data = array("calino_type" => $tsg_info["calino_type"], "clc" => $calino_obj->getClc(), $calino_obj->getDiffname($tsg_info["calino_type"]) => $calino_obj->getDiffCode(), "fzno" => $calino_obj->getFzno());
                    $is_ok = $mod_zch->update($zch_add_data, ['tsg_code' => $this->_user_info["tsg_code"], 'dck_id' => ['in', $ids]])->result;

                    if ($is_ok === false) {
                        $mod_dck->rollback();
                        $this->error("种次号更新失败!");
                    }
                    \BookCalino::upMaxZch($this->_user_info["tsg_code"], $book_info["mt_id"], array($zch_add_data["clc"]));
                }

                $is_success = $mod_dck->update($save_data, ['tsg_code' => $this->_user_info["tsg_code"], 'dck_id' => ['in', $ids]])->result;
                if ($is_success === false) {
                    $mod_dck->rollback();
                    $this->error('批量修改失败!错误提示:更新数据库失败');
                }

                $mod_dck->commit();
                $this->success('批量修改成功');
            } catch (Exception $e) {
                $mod_dck->rollback();
                $this->error('更新馆藏信息失败！');
            }
        }
    }

    public function showzchAction()
    {
        $clc = input('clc_str','');
//        $mt_id = input('get.mt_id/d', 0);
        $mod_mt = d("Mt");
        $mt_list = $mod_mt->get_list();
        if (!empty($mt_list) && empty($_GET["mt_id"])){
            $_GET["mt_id"] = $mt_list[0]["mt_id"];
        }
        if ($this->isAjax){
            $param = $this->getQueryParams();
            $condition['lib_zch.tsg_code'] = $this->_user_info['tsg_code'];
            if ($param->search){
                foreach ($param->search as $search){
                    switch ($search['field']){
                        case 'clc':
                            $condition['lib_zch.clc'] = $search['value'];
                            $clc = $search['value'];
                            break;
                        case 'mt_id':
                            $condition['lib_zch.mt_id'] = $search['value'];
                            break;
                    }
                }
            }
            $join = [
                ['lib_dck','lib_zch.dck_id=lib_dck.dck_id','LEFT'],
                ['lib_book','lib_dck.book_id=lib_book.book_id','LEFT']
            ];
            $field = 'title,lib_zch.zch,lib_zch.fzno,lib_zch.clc';
            $zch_list = Zch::join($join)->field($field)->where($condition)
                ->group('zch')->order('lib_zch.zch lib_zch.fzno')->limit($param->limit)->select();
            $zch_list = collection($zch_list)->toArray();
            $count = count($zch_list);

            $re_zch_list = [];
            if (!empty($zch_list)) {
                $max_i = $zch_list[count($zch_list) - 1]["zch"];
                reset($zch_list);
                $first_val = current($zch_list);
                if ($first_val["zch"] == 0) {
                    array_shift($zch_list);
                }
                for ($i = 1; $i <= $max_i; $i++) {
                    $item = current($zch_list);
                    if ($i != $item["zch"]) {
                        $re_zch_list[] = array("title" => "", "clc" => $clc, "zch" => $i, "fzno" => "", "status" => "空缺");
                        $count += 1;
                    } else {
                        $item["status"] = "占用";
                        $re_zch_list[] = $item;
                        next($zch_list);
                    }
                }
            }
            return $this->echoPageData($re_zch_list,$count);
        }

        $this->assign("mt_list", $mt_list);
        return view();
    }

    public function dropAction()
    {
        $dck_id = input('dck_id');
        $mod_dck = d("Dck");
        $mod_zch = d("zch");
        $mod_ys = d("Ys");
        $tmp_ids = explode(",", $dck_id);
        $ids = array();

        foreach ($tmp_ids as $key => $item) {
            if (!empty($item)) {
                $ids[] = intval($item);
            }
        }
        if (empty($ids)) {
            $this->error('删除馆藏失败:无效的馆藏库ID参数!');
        }
        $ids = implode(",", $ids);
        $dck_list = $mod_dck->field("tsg_code")->where("dck_id in($ids)")->select();
        if (!$dck_list) {
            $this->error(lang('not_found_data'));
        }
        foreach ($dck_list as $item) {
            if ($item["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }
        }
        import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
        try {
            $mod_dck->startTrans();
            $clc_info = $mod_zch->field("clc,mt_id")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dck_id in($ids) AND calino_type=" . \BookCalino::CLC_TYPE_ZCH)->group("clc,mt_id")->select();
            $is_success = $mod_zch->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dck_id in($ids)")->delete();

            if ($is_success === false) {
                $mod_dck->rollback();
                $this->error("删除馆藏失败:种次号数据清除失败!");
            }

            if (!empty($clc_info)) {
                $tmp_arr = array();
                foreach ($clc_info as $item) {
                    $tmp_arr[$item["mt_id"]][] = $item["clc"];
                }
                foreach ($tmp_arr as $key => $item) {
                    \BookCalino::upMaxZch($this->_user_info["tsg_code"], $key, $item);
                }
            }

            $ys_ids = $mod_dck->field("ys_id")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dck_id in($ids)")->group("ys_id")->select();
            $is_success = $mod_dck->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dck_id in($ids)")->delete();
            if ($is_success === false) {
                $mod_dck->rollback();
                $this->error('删除馆藏失败！错误提示:' . $mod_dck->getError());
            }
            foreach ($ys_ids as $item) {
                if (!empty($item["ys_id"])) {
                    $ys_cnt = $mod_dck->where("ys_id={$item["ys_id"]}")->count();
                    empty($ys_cnt) && ($ys_cnt = 0);
                    $is_success = $mod_ys->update(['ys_cnt' => $ys_cnt], ['ys_id' => $item['ys_id']]);
                    if ($is_success === false) {
                        $mod_dck->rollback();
                        $this->error('删除馆藏时更新验收数据失败！错误提示:' . $mod_ys->getError());
                    }
                }
            }

            $mod_qk_log = d("Qk_log");
            $mod_qk_log->addLog(QkLog::OP_TYPE_ZD_DROP, $this->_user_info, array("db1" => $ids));
            $mod_dck->commit();
            $this->success('删除馆藏成功');
        } catch (Exception $e) {
            $mod_dck->rollback();
            $this->error("删除失败！错误提示:" . $e->getMessage());
        }
    }

    public function showcalinoAction()
    {
        $calino_str = input('clc_str');
        $clc = $calino_str;
        $zch = "";
        $first_pos = strpos($calino_str, "/");

        if ($first_pos !== false) {
            $clc = substr($calino_str, 0, $first_pos);
            $calino_split = substr($calino_str, $first_pos + 1);
            $zch = $calino_split;
            $sep_char = array();

            if (preg_match("/[^[:alnum:]]/u", $calino_split, $sep_char, PREG_OFFSET_CAPTURE)) {
                $zch = substr($calino_split, 0, $sep_char[0][1]);
            }
        }

        import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
        $mod_dck = d("Dck");
        $mod_zch = d("zch");
        $mt_id = input('mt_id/d');
        $mod_mt = d("Mt");
        $mt_list = $mod_mt->get_list();
        if (!empty($mt_list) && empty($_GET["mt_id"])) {
            $mt_id = $_GET["mt_id"] = $mt_list[0]["mt_id"];
        }

        $this->assign("mt_list", $mt_list);
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->field("calino_type")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        $zch_type_name = \BookCalino::getDiffname($tsg_info["calino_type"]);
        $this->assign("zch_type_name", $zch_type_name);
        $select_sql = "select title,lib_zch.zch,lib_zch.fzno,lib_zch.clc,lib_zch.sj_code,lib_zch.author_code,barcode from lib_zch 
                      left join lib_dck on lib_zch.dck_id=lib_dck.dck_id
                      left join lib_book on lib_dck.book_id=lib_book.book_id
                      where lib_zch.tsg_code='{$this->_user_info["tsg_code"]}' AND lib_zch.clc = '$clc' AND lib_zch.$zch_type_name = '$zch' AND lib_zch.mt_id=$mt_id AND calino_type={$tsg_info["calino_type"]} 
                      GROUP BY calino order by lib_dck.dck_id desc ";
        $zch_list = $mod_zch->query($select_sql);
        if ($this->isAjax) {
            $this->result($zch_list);
        } else {
            $cali_types = array(\BookCalino::CLC_TYPE_ZCH => lang("CLC_TYPE_ZCH"), \BookCalino::CLC_TYPE_AUTHORCODE => lang("CLC_TYPE_AUTHORCODE"), \BookCalino::CLC_TYPE_SJCODE => lang("CLC_TYPE_SJCODE"));
            $this->assign("calino_type", $tsg_info["calino_type"]);
            $this->assign("cali_types", $cali_types);
            $this->assign("zch_list", $zch_list);
            return view();
        }
    }

    public function getsjcodeAction()
    {
        $author = input('author_str');
        import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
        $sjcode = \BookCalino::genSjcode($author);
        $this->result($sjcode);
    }

    public function showAction()
    {
        $barcode = input('barcode');
        $book_id = input('book_id/d');
        header("Content-Type:text/html;charset=utf-8");
        if (empty($barcode) && empty($book_id)) {
            $this->error('无效的图书条码或系统记录号参数');
        }

        if (!empty($barcode)) {
            $dck_info = Dck::with('book')->field('book_id,calino')->where(['tsg_code'=>$this->_user_info['tsg_code'],'barcode'=>$barcode])->find();

            if (empty($dck_info)) {
                $this->error('馆藏数据库未找到此图书条码,请重新尝试!');
            }
            $book_info = $dck_info->book;
        }
        else if (!empty($book_id)) {
            $book_info = Book::get($book_id);
        }
        if (empty($book_info)) {
            $this->error('数据库未找到此书目信息,请重新尝试!');
        }else{
            $mt_info = Mt::field("mt_code")->where(['mt_id'=>$book_info['mt_id']])->find();
            $book_info['mt_code'] = isset($mt_info['mt_code']) ? $mt_info['mt_code'] : '';
            $this->assign("info", $book_info);
        }
        return view();
    }

    public function showListAction()
    {
        $book_id = input('book_id/d',0);
        $condition = ['book_id'=>$book_id];
        $fields = "dck_id,book_id,barcode,price,price_sum,tsg_code,tsg_code_has,calino,is_close,add_time,status,tsg_site_code,lt_type,dt";
        $param = $this->getQueryParams();
        $list = Dck::getPageList($condition, $param->limit, $param->order,$fields);
        $count = Dck::getCount($condition);
        if ($list){
            $tsg_map = Tsg::getMap('tsg_code', 'tsg_name');
            $tsg_site_map = TsgSite::getMap('tsg_site_code', 'site_name',$this->adminInfo['tsg_code']);
            $ltype_map = Ltype::getMap('ltype_code', 'ltype_name', $this->adminInfo['tsg_code']);
            foreach ($list as $key => $item ) {
                $type_str = ($item["dt"] == Dck::DT_TYPE_BOOK ? "图书" : "期刊");
                $list[$key]["type_str"] = $type_str;
                $list[$key]['tsg_code_has'] = $item['tsg_code'].'|'.$tsg_map[$item['tsg_code']];
                $list[$key]['tsg_code'] = $item['tsg_code'].'|'.$tsg_map[$item['tsg_code']];
                $list[$key]['tsg_site_code'] = $item['tsg_site_code'] . '|' . $tsg_site_map[$item['tsg_site_code']];
                $list[$key]['lt_type'] = $item['lt_type'] . '|' . $ltype_map[$item['lt_type']];
            }


        }
        return $this->echoPageData($list,$count);
    }

}
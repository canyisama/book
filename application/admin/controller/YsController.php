<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/20
 * Time: 10:28
 */

namespace app\admin\controller;


use app\admin\model\Batch;
use app\admin\model\Book;
use app\admin\model\CfLog;
use app\admin\model\Dck;
use app\admin\model\Destine;
use app\admin\model\DestineBatch;
use app\admin\model\User;
use app\admin\model\Ys;
use think\Db;
use think\Exception;
use think\Lang;

/**
 * 图书验收 => 预订验收
 * Class YsController
 * @package app\admin\controller
 */
class YsController extends BaseController
{
    const STATUS_YD = 1;
    const YS_TYPE_YD = 1;
    const YS_TYPE_ZJ = 2;
    const YS_BATCH_STATUS_YS = 1;
    const YS_BATCH_STATUS_FINISH = 2;
    const BATCH_STATUS_YANSHOU = 2;

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/ys.php']);
    }

    public function frameworkAction()
    {
        return $this->redirect('book/index', ['source' => 'ys']);
    }

    public function framework1Action()
    {
        $user_info = User::get($this->adminInfo['user_id']);
        if (empty($user_info)) {
            $this->error('请先设定默认验收批次!');
        }
        $batch_info = Batch::where('batch_no', $user_info['batch_no_curr'])->find();
        if (empty($batch_info)) {
            $this->assign('msg', '无效默认验收批次,请重新设定默认验收批次!');
            $this->assign('type', 0);
            return view('public/error');
            $this->alertMsg('无效默认验收批次,请重新设定默认验收批次!');
        }
        if ($batch_info['status'] != self::YS_BATCH_STATUS_YS) {
            $this->error('默认验收批次必须为验收状态,请重新设定默认验收批次!');
        }

        $destine_batch_list = DestineBatch::where(['tsg_code' => $this->adminInfo['tsg_code']])->select();
        $this->assign('source', 'ys');
        $this->assign('destine_batch_list', $destine_batch_list);
        $this->assign('destine_batch_curr', $user_info['destine_batch_curr']);
        return view();
    }

    /**
     * 验收管理
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ys_manAction()
    {
        $batch_list = Batch::where(['tsg_code' => $this->adminInfo['tsg_code']])->field('batch_no')->select();
        $this->assign('batch_no', $this->adminInfo['batch_no_curr']);
        $this->assign('batch_list', $batch_list);
        return view();
    }

    public function addAction()
    {
        $book_id = input('book_id/d');
        $ys_type = input('ys_type', self::YS_TYPE_ZJ);
        $destine_id = input('destine_id/d');
        if (($ys_type != self::YS_TYPE_ZJ) && ($ys_type != self::YS_TYPE_YD)) {
            $this->alertMsg('无效的验收类型,无法验收!');
        }

        $mod_book = d("Book");
        $mod_user = d("User");
        $mod_batch = d("Batch");
        $mod_destine = d("Destine");
        $mod_destine_batch = d("Destine_batch");
        $book_info = $mod_book->field("lib_book.book_id,firstauthor,mt_id,marc")->where("book_id=$book_id")->find();
        if (!$book_info) {
            $this->alertMsg('书目信息不存在,无法增加验收!');
        }

        $user_info = $mod_user->field("batch_no_curr")->where("user_id={$this->_user_info["user_id"]}")->find();
        if (empty($user_info)) {
            $this->alertMsg('请先设定默认验收批次!');
        }
        $batch_info = $mod_batch->field("batch_no,status,seller_code,cost_code")->where("batch_no='{$user_info["batch_no_curr"]}'")->find();
        if (empty($batch_info)) {
            $this->alertMsg('无效默认验收批次,请重新设定默认验收批次!');
        }
        if ($batch_info["status"] != self::YS_BATCH_STATUS_YS) {
            $this->alertMsg('默认验收批次必须为验收状态,请重新设定默认验收批次!');
        }
        if ($ys_type == self::YS_TYPE_YD) {
            $destine_info = $mod_destine->field("destine_id,status,destine_batch_code,jzinfo,ori_price,price,discount,order_sour")->where("destine_id=$destine_id")->find();
            if (empty($destine_info)) {
                $this->alertMsg('无效的预订信息,无法验收!');
            }
            if ($destine_info["status"] != self::STATUS_YD) {
                $this->alertMsg('只有预订状态的预订信息才可验收!');
            }
            $destine_batch_info = $mod_destine_batch->field("seller_code,cost_code,status")->where("destine_batch_code='{$destine_info["destine_batch_code"]}' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
            if (empty($destine_batch_info)) {
                $this->alertMsg('无效的预订批次,预订批次不存在,无法验收!');
            }
            if ($destine_batch_info["status"] != self::BATCH_STATUS_YANSHOU) {
                $this->alertMsg('预订批次的状态错误,必须为验收状态!');
            }
            $ys_info = array("book_sour" => $destine_info["order_sour"], "batch_no" => $batch_info["batch_no"], "jzh" => $destine_info["jzinfo"], "ori_price" => $destine_info["ori_price"], "price" => $destine_info["price"], "discount" => $destine_info["discount"], "seller_code" => $destine_batch_info["seller_code"], "cost_code" => $destine_batch_info["cost_code"]);
            $this->assign("ys_info", $ys_info);
        } else if ($ys_type == self::YS_TYPE_ZJ) {
            $ys_info = array("batch_no" => $batch_info["batch_no"], "seller_code" => $batch_info["seller_code"], "cost_code" => $batch_info["cost_code"]);
            $this->assign("ys_info", $ys_info);
        }

        // 开始提交
        if ($this->isPost) {
            $mod_ys = d("Ys");
            $mod_tsg = d("Tsg");
            $mod_zch = d("zch");
            $mod_dck = d("Dck");
            $mod_destine = d("Destine");
            $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();

            $add_data = array(
                'calino' => input('calino'),
                'jzh' => input('jzh'),
                'book_sour' => input('book_sour'),
                'ori_price' => input('ori_price'),
                'discount' => input('discount'),
                'price' => input('price'),
                'seller_code' => input('seller_code'),
                'jz_type' => input('jz_type'),
                'price_sum' => input('price_sum'),
                'currency' => input('currency'),
                'cost_code' => input('cost_code'),
            );

            if (!$add_data["calino"]) {
                $this->error(lang("batch_no_required"));
            }
            if (!$add_data["ori_price"]) {
                $this->error(lang("ori_price_required"));
            }
            if (!$add_data["price"]) {
                $this->error(lang("price_required"));
            }
            if (!$add_data["discount"]) {
                $this->error(lang("discount_required"));
            }
            import('Marc\MARC', EXTEND_PATH, '.class.php');
            $marc_obj = new \MARC();
            $mdata = \MARC::readMarcByStr($book_info["marc"]);
            $marc_obj->setData($mdata);
            $rel_arr = $marc_obj->getRelArray();
            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
            $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);
            $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $add_data["calino"], "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));

            if ($calino_obj->hasError()) {
                $this->error($calino_obj->getError());
            }
            if (!$calino_obj->unique()) {
                $this->error("索书号重复");
                return false;
            }

            $add_data["calino"] = $calino_obj->autoCalino();
            $zch_add_data = array("calino_type" => $tsg_info["calino_type"], "tsg_code" => $this->_user_info["tsg_code"], "clc" => $calino_obj->getClc(), $calino_obj->getDiffname($tsg_info["calino_type"]) => $calino_obj->getDiffCode(), "fzno" => $calino_obj->getFzno());
            $clc_list[] = $zch_add_data["clc"];
            $add_data["book_id"] = $book_id;
            $add_data["batch_no"] = $batch_info["batch_no"];
            $add_data["tsg_code"] = $this->_user_info["tsg_code"];
            $ori_price = floatval($add_data["ori_price"]);
            $discount = floatval($add_data["discount"]);
            $add_data["price"] = round(($ori_price * $discount) / 100, 2);
            $add_data["ys_type"] = $ys_type;
            $add_data["ys_time"] = time();
            $add_data["ys_user"] = $this->_user_info["user_name"];

            if ($ys_type == self::YS_TYPE_YD) {
                $add_data["destine_id"] = $destine_id;
                $add_data["seller_code"] = $destine_batch_info["seller_code"];
                $add_data["cost_code"] = $destine_batch_info["cost_code"];
            }

            $dck_data_tmp = array();
            $dck_data_tmp["book_id"] = $add_data["book_id"];
            $dck_data_tmp["tsg_code"] = $add_data["tsg_code"];
            $dck_data_tmp["tsg_code_has"] = $add_data["tsg_code"];
            $dck_data_tmp["batch_no"] = $add_data["batch_no"];
            $dck_data_tmp["seller_code"] = $add_data["seller_code"];
            $dck_data_tmp["cost_code"] = $add_data["cost_code"];
            $dck_data_tmp["price"] = $add_data["ori_price"];
            $dck_data_tmp["calino"] = $add_data["calino"];
            $dck_data_tmp["currency"] = $add_data["currency"];
            $dck_data_tmp["price_sum"] = $add_data["price_sum"];
            $dck_data_tmp["status"] = "分编";
            $dck_data_tmp["jz_type"] = $add_data["jz_type"];
            $dck_data_tmp["jzh"] = $add_data["jzh"];
            $dck_data_tmp["book_sour"] = $add_data["book_sour"];
            $dck_data_tmp["add_time"] = time();
            $dck_data_tmp["add_user"] = $this->_user_info["user_name"];
            $dck_data_tmp["edit_time"] = $dck_data_tmp["add_time"];
            $dck_data_tmp["edit_user"] = $this->_user_info["user_name"];

            if (empty(input('barcode/a'))) {
                $this->error('图书条码为空,无法保存！');
            }

            $add_list = array();
            $barcode_list = array();
            foreach (input('barcode/a') as $key => $item) {
                $barcode_tmp = trim($item);
                if (empty($barcode_tmp)) {
                    $this->error("有图书条码为空,请修改后再提交!");
                }
                if (!empty($tsg_info["barcode_len"])) {
                    if (strlen($barcode_tmp) != $tsg_info["barcode_len"]) {
                        $this->error("图书条码{$barcode_tmp}长度错误,已设置条码长度为{$tsg_info["barcode_len"]},请修改后再提交!");
                    }
                }

                $barcode_list[] = $barcode_tmp;
                $dck_data_tmp["barcode"] = $barcode_tmp;
                $dck_data_tmp["login_no"] = (!empty($tsg_info["loginno_accord"]) ? $dck_data_tmp["barcode"] : trim(input('login_no/a')[$key]));
                $dck_data_tmp["tsg_site_code"] = trim(input('tsg_site_code/a')[$key]);
                $dck_data_tmp["tsg_site_code_has"] = $dck_data_tmp["tsg_site_code"];
                $dck_data_tmp["lt_type"] = trim($_POST["lt_type"][$key]);
                $add_list[] = $dck_data_tmp;
            }

            $add_data["ys_cnt"] = count($add_list);
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

            try {
                $mod_ys->startTrans();
                $ys_id = $mod_ys->create($add_data)->getLastInsID();

                if (!$ys_id) {
                    $mod_ys->rollback();
                    $this->error("插入验收数据失败:插入数据库失败");
                }

                foreach ($add_list as $item) {
                    $dck_add_data = $item;
                    $dck_add_data["ys_id"] = $ys_id;
                    $dck_id = $mod_dck->create($dck_add_data)->getLastInsID();

                    if (!$dck_id) {
                        $mod_ys->rollback();
                        $this->error("新增馆藏失败:插入数据库失败");
                    }

                    $zch_add_data["dck_id"] = $dck_id;
                    $zch_id = $mod_zch->create($zch_add_data)->getLastInsID();

                    if (!$zch_id) {
                        $mod_ys->rollback();
                        $this->error("种次号增加失败:插入数据库失败");
                    }
                }

                if ($ys_type == self::YS_TYPE_YD) {
                    $ys_cnt_sum = $mod_ys->where("destine_id=$destine_id")->sum("ys_cnt");
                    $is_success = $mod_destine->update(['ys_cnt' => $ys_cnt_sum], ['destine_id' => $destine_id])->result;

                    if ($is_success === false) {
                        $mod_ys->rollback();
                        $this->error("更新预订信息失败:更新数据库失败");
                    }
                }

                \BookCalino::upMaxZch($this->_user_info["tsg_code"], $book_info["mt_id"], $clc_list);
                CfLog::addLog(CfLog::OP_TYPE_YS_ADD, $this->_user_info, array("book_id" => $book_id, "db1" => $ys_id));
                $mod_ys->commit();
                $this->success("保存验收成功！");
            } catch (Exception $e) {
                $mod_ys->rollback();
                $this->error("新增验收异常！错误提示:" . $e->getMessage());
            }
        }

        if ($ys_type == self::YS_TYPE_YD) {
            $this->assign("form_title", lang("yd_form_title_add"));
        } else if ($ys_type == self::YS_TYPE_ZJ) {
            $this->assign("form_title", lang("zj_form_title_add"));
        }

        $this->dck_assign_common($book_id, "add");
        $this->assign("is_ys", self::YS_TYPE_YD);
        $this->assign("batch_info", $batch_info);
        $this->assign("ys_type", $ys_type);
        $this->assign('_ACTION_NAME_', 'add');
        return view('edit');
    }

    public function editAction()
    {
        $ys_id = input('ys_id/d');
        if (!$ys_id) {
            return $this->addAction();
        }
        $mod_ys = d("Ys");
        $ys_info = $mod_ys->where("ys_id=$ys_id")->find();
        if (!$ys_info) {
            $this->alertMsg('验收信息不存在,无法编辑验收!');
        }
        $this->assign('_ACTION_NAME_', 'edit');
        $book_id = input('book_id/d');
        $ys_type = $ys_info['ys_type'] ?: self::YS_TYPE_ZJ;
        $mod_destine = d("Destine");
        $mod_user = d("User");
        $mod_batch = d("Batch");
        $mod_destine_batch = d("Destine_batch");
        $mod_book = d("Book");
        $mod_dck = d("Dck");
        $mod_destine = d("Destine");
        $book_info = $mod_book->field("lib_book.book_id,firstauthor,mt_id,marc")->where("book_id=$book_id")->find();

        if (!$book_info) {
            $this->alertMsg('验收对应的书目信息不存在,无法编辑验收!');
        }
        $user_info = $mod_user->field("batch_no_curr")->where("user_id={$this->_user_info["user_id"]}")->find();
        if (empty($user_info)) {
            $this->alertMsg('请先设定默认验收批次!');
        }
        $batch_info = $mod_batch->field("batch_no,status")->where("batch_no='{$user_info["batch_no_curr"]}'")->find();
        if (empty($batch_info)) {
            $this->alertMsg('无效默认验收批次,请重新设定默认验收批次!');
        }
        if ($batch_info["status"] != self::YS_BATCH_STATUS_YS) {
            $this->alertMsg('默认验收批次必须为验收状态,请重新设定默认验收批次!');
        }
        $dck_list = $mod_dck->field("dck_id,barcode,login_no,lt_type,tsg_site_code")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND book_id=$book_id AND ys_id=$ys_id")->select();

        if ($this->isPost) {
            $mod_tsg = d("Tsg");
            $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
            $add_data = array("calino" => isset($_POST["calino"]) ? trim($_POST["calino"]) : "", "jzh" => isset($_POST["jzh"]) ? trim($_POST["jzh"]) : "", "book_sour" => isset($_POST["book_sour"]) ? trim($_POST["book_sour"]) : "", "ori_price" => isset($_POST["ori_price"]) ? intval($_POST["ori_price"]) : 0, "price" => isset($_POST["price"]) ? intval($_POST["price"]) : 0, "discount" => isset($_POST["discount"]) ? intval($_POST["discount"]) : 0, "price_sum" => isset($_POST["price_sum"]) ? intval($_POST["price_sum"]) : 0, "jz_type" => isset($_POST["jz_type"]) ? trim($_POST["jz_type"]) : "", "currency" => isset($_POST["currency"]) ? trim($_POST["currency"]) : "");

            if (!$add_data) {
                $this->error('新增失败！');
            }
            if (!$add_data["calino"]) {
                $this->error(lang('batch_no_required'));
            }
            if (!$add_data["ori_price"]) {
                $this->error(lang('ori_price_required'));
            }
            if (!$add_data["price"]) {
                $this->error(lang('price_required'));
            }
            if (!$add_data["discount"]) {
                $this->error(lang('discount_required'));
            }

            import('Marc\MARC', EXTEND_PATH, '.class.php');
            $marc_obj = new \MARC();
            $mdata = \MARC::readMarcByStr($book_info["marc"]);
            $marc_obj->setData($mdata);
            $rel_arr = $marc_obj->getRelArray();
            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
            $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);
            $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $add_data["calino"], "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));

            if ($calino_obj->hasError()) {
                $this->error($calino_obj->getError());
            }
            if (!$calino_obj->unique()) {
                $this->error("索书号重复");
            }
            $add_data["calino"] = $calino_obj->autoCalino();
            $zch_add_data = array("calino_type" => $tsg_info["calino_type"], "tsg_code" => $this->_user_info["tsg_code"], "clc" => $calino_obj->getClc(), $calino_obj->getDiffname($tsg_info["calino_type"]) => $calino_obj->getDiffCode(), "fzno" => $calino_obj->getFzno());
            $clc_list[] = $zch_add_data["clc"];
            $ori_price = floatval($add_data["ori_price"]);
            $discount = floatval($add_data["discount"]);
            $add_data["price"] = round(($ori_price * $discount) / 100, 2);

            $barcode_post_list = input('barcode/a');
            if (empty($barcode_post_list)) {
                $this->error('图书条码为空,无法保存！');
            }
            $dck_save_data_tmp = array();
            $dck_save_data_tmp["edit_time"] = time();
            $dck_save_data_tmp["edit_user"] = $this->_user_info["user_name"];
            $diff_field = array("price" => "ori_price", "calino" => "calino", "currency" => "currency", "price_sum" => "price_sum", "jz_type" => "jz_type", "jzh" => "jzh", "book_sour" => "book_sour");

            foreach ($diff_field as $key => $item) {
                if ($ys_info[$item] != $add_data[$item]) {
                    $dck_save_data_tmp[$key] = $add_data[$item];
                }
            }

            $dck_add_data_tmp = array();
            $dck_add_data_tmp["book_id"] = $ys_info["book_id"];
            $dck_add_data_tmp["tsg_code"] = $ys_info["tsg_code"];
            $dck_add_data_tmp["batch_no"] = $ys_info["batch_no"];
            $dck_add_data_tmp["seller_code"] = $ys_info["seller_code"];
            $dck_add_data_tmp["cost_code"] = $ys_info["cost_code"];
            $dck_add_data_tmp["price"] = $add_data["ori_price"];
            $dck_add_data_tmp["calino"] = $add_data["calino"];
            $dck_add_data_tmp["currency"] = $add_data["currency"];
            $dck_add_data_tmp["price_sum"] = $add_data["price_sum"];
            $dck_add_data_tmp["status"] = "分编";
            $dck_add_data_tmp["jz_type"] = $add_data["jz_type"];
            $dck_add_data_tmp["jzh"] = $add_data["jzh"];
            $dck_add_data_tmp["book_sour"] = $add_data["book_sour"];
            $dck_add_data_tmp["add_time"] = time();
            $dck_add_data_tmp["add_user"] = $this->_user_info["user_name"];
            $dck_add_data_tmp["edit_time"] = $dck_add_data_tmp["add_time"];
            $dck_add_data_tmp["edit_user"] = $this->_user_info["user_name"];
            $dck_ids = array();

            foreach ($dck_list as $item) {
                $dck_ids[] = $item["dck_id"];
            }

            $add_list = array();
            $save_list = array();
            $barcode_list = array();
            $dck_id_post_list = input('dck_id/a');
            foreach ($barcode_post_list as $key => $item) {
                $is_save = in_array($dck_id_post_list[$key], $dck_ids);
                $dck_data_tmp = ($is_save ? $dck_save_data_tmp : $dck_add_data_tmp);
                $barcode_tmp = trim($item);
                if (empty($barcode_tmp)) {
                    $this->error("有图书条码为空,请修改后再提交!");
                }

                if (!empty($tsg_info["barcode_len"])) {
                    if (strlen($barcode_tmp) != $tsg_info["barcode_len"]) {
                        $this->error("图书条码{$barcode_tmp}长度错误,已设置条码长度为{$tsg_info["barcode_len"]},请修改后再提交!");
                    }
                }

                $barcode_list[] = $barcode_tmp;
                $dck_data_tmp["barcode"] = $barcode_tmp;
                $dck_data_tmp["login_no"] = (!empty($tsg_info["loginno_accord"]) ? $dck_data_tmp["barcode"] : trim(input('login_no/a')[$key]));
                $dck_data_tmp["tsg_site_code"] = trim(input('tsg_site_code/a')[$key]);
                $dck_data_tmp["lt_type"] = trim($_POST["lt_type"][$key]);

                if ($is_save) {
                    $dck_data_tmp["dck_id"] = $dck_id_post_list[$key];
                    $save_list[] = $dck_data_tmp;
                } else {
                    $dck_data_tmp["book_id"] = $ys_info["book_id"];
                    $dck_data_tmp["batch_no"] = $ys_info["batch_no"];
                    $dck_data_tmp["tsg_code"] = $ys_info["tsg_code"];

                    if ($ys_type == self::YS_TYPE_YD) {
                        $dck_data_tmp["seller_code"] = $ys_info["seller_code"];
                        $dck_data_tmp["cost_code"] = $ys_info["cost_code"];
                    }

                    $dck_data_tmp["add_time"] = time();
                    $dck_data_tmp["add_user"] = $this->_user_info["user_name"];
                    $dck_data_tmp["edit_time"] = $dck_data_tmp["add_time"];
                    $dck_data_tmp["edit_user"] = $this->_user_info["user_name"];
                    $add_list[] = $dck_data_tmp;
                }
            }

            $add_data["ys_cnt"] = count($add_list) + count($save_list);
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
            $is_unique = $mod_dck->isUniqueBarcode($this->_user_info["tsg_code"], $barcode_list, $ys_id, "ys_id");
            if ($is_unique === false) {
                $tmp_arr1 = $mod_dck->errorData["code_list"];
                $this->error($mod_dck->errorData["msg"] . ",重复条码:" . implode("、", $tmp_arr1));
            }
            $mod_zch = d("zch");
            try {
                $mod_ys->startTrans();
                $is_success = $mod_ys->update($add_data, ['ys_id' => $ys_id])->result;
                if ($is_success === false) {
                    $mod_ys->rollback();
                    $this->error("更新验收数据失败:更新数据库失败");
                }

                foreach ($save_list as $item) {
                    $dck_add_data = $item;
                    $dck_id_tmp = (!empty($dck_add_data["dck_id"]) ? intval($dck_add_data["dck_id"]) : 0);
                    unset($dck_add_data["dck_id"]);
                    $dck_id = $mod_dck->update($dck_add_data, ['dck_id' => $dck_id_tmp])->result;
                    if ($dck_id === false) {
                        $mod_ys->rollback();
                        $this->error("更新馆藏失败:更新数据库失败");
                    }
                    $zch_id = $mod_zch->update($zch_add_data, ['dck_id' => $dck_id_tmp])->result;
                    if ($zch_id === false) {
                        $mod_ys->rollback();
                        $this->error("种次号更新失败:更新数据库失败");
                    }
                }

                foreach ($add_list as $item) {
                    $dck_add_data = $item;
                    $dck_add_data["ys_id"] = $ys_id;
                    $dck_id = $mod_dck->create($dck_add_data)->getLastInsID();
                    if (!$dck_id) {
                        $mod_ys->rollback();
                        $this->error("新增馆藏失败:更新数据库失败");
                    }

                    $zch_add_data["dck_id"] = $dck_id;
                    $zch_id = $mod_zch->create($zch_add_data)->getLastInsID();
                    if (!$zch_id) {
                        $mod_ys->rollback();
                        $this->error("种次号增加失败:更新数据库失败");
                    }
                }

                if ($ys_type == self::YS_TYPE_YD) {
                    $ys_cnt_sum = $mod_ys->where("destine_id={$ys_info["destine_id"]}")->sum("ys_cnt");
                    $is_success = $mod_destine->update(['ys_cnt' => $ys_cnt_sum], ['destine_id' => $ys_info["destine_id"]])->result;
                    if ($is_success === false) {
                        $mod_ys->rollback();
                        $this->error("更新预订信息失败:更新数据库失败");
                    }
                }
                \BookCalino::upMaxZch($this->_user_info["tsg_code"], $book_info["mt_id"], $clc_list);
                CfLog::addLog(CfLog::OP_TYPE_YS_SAVE, $this->_user_info, array("book_id" => $book_info["book_id"], "db1" => $ys_id));
                $mod_ys->commit();
                $this->success("保存验收数据成功！");
            } catch (Exception $e) {
                $mod_ys->rollback();
                $this->error("新增验收数据异常");
            }
        }
        $this->assign("dck_list", $dck_list);
        if ($ys_type == self::YS_TYPE_YD) {
            $this->assign("form_title", lang("yd_form_title_edit"));
        } else if ($ys_type == self::YS_TYPE_ZJ) {
            $this->assign("form_title", lang("zj_form_title_edit"));
        }

        $this->dck_assign_common($book_id, "add");
        $this->assign("is_ys", self::YS_TYPE_YD);
        $this->assign("batch_info", $batch_info);
        $this->assign("ys_info", $ys_info);
        $this->assign("default_info", $ys_info);
        $this->assign("book_clc", $ys_info["calino"]);
        $this->assign("ys_type", $ys_type);
        return view();
    }

    public function getYsManListAction()
    {
        $batch_no = (isset($_GET["batch_no"]) && !empty($_GET["batch_no"]) ? trim($_GET["batch_no"]) : $this->adminInfo["batch_no_curr"]);
        $ys_list = Ys::where(['batch_no' => $batch_no])->field('ys_id')->select();
        $ys_ids = [];
        foreach ($ys_list as $item) {
            $ys_ids[] = $item['ys_id'];
        }
        unset($item);
        unset($ys_list);

        $params = $this->getQueryParams();//分页,排序,查询参数
        $condition = [];
        $book_condition = [];
        if ($params->search) {
            foreach ($params->search as $search) {
                if ($search['field'] == 'destine_batch_code')
                    continue;
                $book_condition[$search['field']] = $search['value'];
            }
        }
        if ($ys_ids) {
            $condition['ys_id'] = ['in', $ys_ids];
        }
        $book_list = Book::where($book_condition)->column('book_id');
        $condition['book_id'] = ['in', $book_list];
        $ys_list = Ys::getPageList($condition, $params->limit, $params->order);
        $count = Ys::where($condition)->count();
        $book_ids = [];
        foreach ($ys_list as $item) {
            $book_ids[] = $item['book_id'];
        }
        $book_list = Book::where(['book_id' => ['in', $book_ids]])->select();
        $book_list = array_under_reset($book_list, 'book_id');

        foreach ($ys_list as &$item) {
            $book = $book_list[$item['book_id']];
            $item['clc'] = $book['clc'];
            $item['title'] = $book['title'];
            $item['ys_time'] = fmt_date_time($item['ys_time']);
            $item['discount'] = $item['discount'] . ' %';
            $item['publisher'] = $book['publisher'];
        }
        unset($item);
        return $this->echoPageData($ys_list, $count);
    }

    public function getYsListAction()
    {
        $condition = [];
        $ys_type = input('ys_type', self::YS_TYPE_ZJ);
        $book_id = input('book_id/d');
        $destine_id = input('destine_id/d');

        if (cookie('show_all_tsg') != 1) {
            $condition['tsg_code'] = $this->adminInfo['tsg_code'];
        }
        $condition['book_id'] = $book_id;
        if ($ys_type == self::YS_TYPE_YD) {
            $condition['ys_type'] = self::YS_TYPE_YD;
            $condition['destine_id'] = $destine_id;
        }
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = $search['value'];
            }
        }

        $fields = 'ys_id,batch_no,tsg_code,calino,ys_type,ys_cnt,ori_price,price,discount,seller_code,cost_code,ys_user,ys_time';
        $ys_list = Ys::getPageList($condition, $params->limit, $params->order, $fields);
        $count = Ys::where($condition)->count();
        foreach ($ys_list as $key => $item) {
            $item['ys_time'] = fmt_date_time($item['ys_time']);
            $ys_list[$key] = $item;
        }
        return $this->echoPageData($ys_list, $count);
    }

    public function destine_ysAction()
    {
        $mod_user = d("User");
        $mod_destine = d("Destine");
        $params = $this->getQueryParams();//分页,排序,查询参数
        $where = array();
        $index_map = array();

        $where_batch["tsg_code"] = $this->_user_info["tsg_code"];
        $mt_id = $mod_user->get_mt_id($this->_user_info["user_id"]);
        $mod_indexrel = d("Indexrel");
        $index_list = $mod_indexrel->field("sour_field,order_table")->where("sour_mod='Book' AND mt_id=$mt_id")->select();
        $where["lib_destine.status"] = array("eq", self::STATUS_YD);
        foreach ($index_list as $item) {
            $index_map[$item["sour_field"]] = $item["order_table"];
        }
        $table_str = "lib_destine";
        if (isset($index_map["tsg_code"])) {
            $where[$table_str . ".tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
            $table_str .= " INNER JOIN {$index_map["tsg_code"]} on book_id={$index_map["tsg_code"]}.bid";
            $mod_destine = $mod_destine->join($index_map["tsg_code"], "book_id={$index_map["tsg_code"]}.bid");
        }

        if ($params->search) {
            foreach ($params->search as $search) {
                if (in_array($search['field'], array("order_no", "jzinfo", "ori_price", "price", "book_cnt", "order_sour", "remark"))) {
                    $where[$search['field']] = array("like", $search['value'] . "%");
                } elseif (isset($index_map[$search['field']])) {
                    if ($search['field'] == "isbn") {
                        $isbn = str_replace("-", "", $search['value']);
                        if (11 <= strlen($isbn)) {
                            $isbn = substr($isbn, 0, 12);
                        } else if (10 <= strlen($isbn)) {
                            $isbn = (substr($isbn, 0, 3) != "978" ? "978" . substr($isbn, 0, 9) : substr($isbn, 0, 9));
                        } else {
                            $isbn = (substr($isbn, 0, 3) != "978" ? "978" . $isbn : $isbn);
                        }
                        $search['value'] = $isbn;
                    }
                    $where[$index_map[$search['field']] . ".val"] = array("like", $search['value'] . "%");
                    $table_str .= " INNER JOIN {$index_map[$search['field']]} on {$index_map["tsg_code"]}.bid={$index_map[$search['field']]}.bid";
                    $mod_destine = $mod_destine->join($index_map[$search['field']], "{$index_map["tsg_code"]}.bid={$index_map[$search['field']]}.bid");
                } else {
                    $where[$search['field']] = $search['value'];
                }
            }
        }

        $count = $mod_destine->where($where)->count();
        $ids_tmp = $mod_destine->field("book_id")->where($where)->limit($params->limit)->group("book_id")->select();
        $fields = "lib_destine.book_id,destine_id,destine_batch_code,lib_destine.tsg_code,order_no,jzinfo,ori_price,discount,price,book_cnt,order_sour,remark,title,isbn,clc,firstauthor,publisher,pubdate";
        $ids = array();

        foreach ($ids_tmp as $item) {
            $ids[] = $item["book_id"];
        }

        $ids = (!empty($ids) ? implode(",", $ids) : 0);
        $destine_list = Destine::join('lib_book', 'lib_destine.book_id=lib_book.book_id')->field($fields)->where("lib_destine.book_id in($ids)")->group("book_id")->select();
        return $this->echoPageData($destine_list, $count);
    }

    public function getBookDestineAction()
    {
        $condition = ['status' => DestineController::STATUS_YD];
        $book_condition = [];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                switch ($search['field']) {
                    case 'destine_batch_code':
                        $condition['destine_batch_code'] = $search['value'];
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
            if ($book_search_ids)
                $condition['book_id'] = ['in', $book_search_ids];
        }

        $list = Destine::getPageList($condition, $params->limit, $params->order, '', 'book_id');
        $count = Destine::where($condition)->group('book_id')->count();
        $book_ids = [];
        foreach ($list as $item) {
            $book_ids[] = $item['book_id'];
        }
        $book_list = Book::where(['book_id' => ['in', $book_ids]])->select();
        $book_list = array_under_reset($book_list, 'book_id');

        foreach ($list as &$item) {
            $book = $book_list[$item['book_id']];
            $item['clc'] = $book['clc'];
            $item['title'] = $book['title'];
            $item['pubdate'] = $book['pubdate'];
            $item['discount'] = $item['discount'] . ' %';
            $item['publisher'] = $book['publisher'];
        }
        unset($item);
        return $this->echoPageData($list, $count);
    }

    public function dropAction()
    {
        $ys_id = input('ys_id/d');
        $dck_info = Dck::where('ys_id', $ys_id)->find();

        if (!empty($dck_info)) {
            $this->error('本验收数据存在馆藏信息,无法删除！');
        }
        $ys_info = Ys::get($ys_id);
        if (!$ys_info) {
            $this->error(lang('not_found_data'));
        }
        if ($ys_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }

        try {
            if (!empty($ys_info)) {
                Db::startTrans();
                $is_success = Ys::where(['tsg_code' => $this->adminInfo['tsg_code'], 'ys_id' => $ys_id])->delete();

                if ($is_success === false) {
                    Db::rollback();
                    $this->error('删除验收信息失败！');
                }

                if ($ys_info["ys_type"] == self::YS_TYPE_YD) {
                    $ys_cnt_sum = Ys::where('destine_id', $ys_info['destine_id'])->sum('ys_cnt');
                    $is_success = Destine::update(['ys_cnt' => $ys_cnt_sum], ['destine_id' => $ys_info['destine_id']]);
                    if ($is_success === false) {
                        Db::rollback();
                        $this->error("更新预订信息失败:更新数据库失败");
                    }
                }
                CfLog::addLog(CfLog::OP_TYPE_YS_DROP, $this->adminInfo, array("book_id" => $ys_info["book_id"], "db1" => $ys_id));
                Db::commit();
            }

            $this->success('删除验收信息成功！');
        } catch (Exception $e) {
            Db::rollback();
            $this->error("删除验收信息异常！错误提示:" . $e->getMessage());
        }
    }

    public static function get_ys_types()
    {
        return array(self::YS_TYPE_YD => "预订验收", self::YS_TYPE_ZJ => "直接验收");
    }



}
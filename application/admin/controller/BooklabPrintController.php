<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 10:45
 */

namespace app\admin\controller;


use think\Lang;

class BooklabPrintController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/booklab_print.php']);
    }

    public function indexAction()
    {
        $mod_booklab_cnf = d("Booklab_cnf");
        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $mod_batch = d("Batch");
        $booklab_cnf_list = $mod_booklab_cnf->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();

        foreach ($booklab_cnf_list as $key => $item) {
            $booklab_cnf_list[$key]["fields_cnf"] = $mod_booklab_cnf_ext->where("booklab_cnf_id={$item["booklab_cnf_id"]}")->order("field_type_order")->select();
        }

        $batch_list = $mod_batch->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $this->assign("booklab_cnf_list", $booklab_cnf_list);

        if (input('dtype/d') == 1) {
            $_curlocal = array(
                array("text" => l("menu_qk")),
                array("text" => l("menu_qkzd")),
                array("text" => l("booklab_print"))
            );
        } else {
            $_curlocal = array(
                array("text" => l("menu_bm")),
                array("text" => l("menu_bm_child")),
                array("text" => l("booklab_print"))
            );
        }

        $date_arr = array("beg" => date("Y-m-d") . " 00:00:00", "end" => date("Y-m-d") . " 23:59:59");
        $this->assign("date_arr", $date_arr);
        $order_list = $this->_get_order_list();
        $this->assign("order_list", $order_list);
        $this->assign("batch_list", $batch_list);
        $this->assign("_curlocal", $_curlocal);
        return view();
    }

    public function index_qkAction()
    {
        return $this->redirect('index',['dtype'=>1]);
    }

    public function printsetAction()
    {
        if ($this->isPost) {
            $bm_cnf = c("catalog");
            $mod_booklab_cnf = d("Booklab_cnf");
            $printset_cnf = $bm_cnf["booklab_print_cnf"];
            $booklab_cnf_list = $mod_booklab_cnf->field("booklab_cnf_id,cnf_name")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();
            $this->assign("booklab_cnf_list", $booklab_cnf_list);
            $this->assign("printset_cnf_json", json_encode($printset_cnf));
            $search_type = $this->_post("search_type");
            $order_type = $this->_post("order_type");
            $order_seq = $this->_post("order_seq");
            $order_list = $this->_get_order_list();
            $order_str = (!empty($order_type) && isset($order_list[$order_type]) ? "$order_type $order_seq" : "barcode");
            $calino_list = array();
            $mod_dck = d("Dck");

            if ($search_type == "1") {
                $batch_no = $this->_post("batch_no");
                $where = array();
                $where["lib_dck.tsg_code"] = $this->_user_info["tsg_code"];
                if (!empty($_POST["barcode_beg"]) || !empty($_POST["barcode_end"])) {
                    $barcode_beg = (isset($_POST["barcode_beg"]) && !empty($_POST["barcode_beg"]) ? trim($_POST["barcode_beg"]) : trim($_POST["barcode_end"]));
                    $barcode_end = (isset($_POST["barcode_end"]) && !empty($_POST["barcode_end"]) ? trim($_POST["barcode_end"]) : trim($_POST["barcode_beg"]));

                    if (strlen($barcode_beg) != strlen($barcode_end)) {
                        $this->error("图书起始条码和结束条码的位数必须一致");
                        return false;
                    }

                    $where["barcode"] = array(
                        "BETWEEN",
                        array($barcode_beg, $barcode_end)
                    );
                    $where["LENGTH(barcode)"] = array("eq", strlen($barcode_beg));
                }

                !empty($batch_no) && ($where["batch_no"] = array("eq", $batch_no));

                if (!empty($_POST["dck_add_time_beg"]) || !empty($_POST["dck_add_time_end"])) {
                    $dck_add_time_beg = (isset($_POST["dck_add_time_beg"]) ? mstrtotime(trim($_POST["dck_add_time_beg"])) : 0);
                    $dck_add_time_end = (isset($_POST["dck_add_time_end"]) ? mstrtotime(trim($_POST["dck_add_time_end"])) : 0);
                    if ($dck_add_time_beg && !$dck_add_time_end) {
                        $dck_add_time_end = mstrtotime(mdate("Y-m-d", $dck_add_time_beg) . " 23:59:59");
                    } else {
                        if (!$dck_add_time_beg && $dck_add_time_end) {
                            $dck_add_time_beg = mstrtotime(mdate("Y-m-d", $dck_add_time_end) . " 00:00:00");
                        }
                    }

                    $where["add_time"] = array(
                        "between",
                        array($dck_add_time_beg, $dck_add_time_end)
                    );
                }

                if (empty($where)) {
                    $this->error("请输入检索的条件！");
                }
                $calino_list = $mod_dck->join("lib_zch", 'lib_dck.dck_id=lib_zch.dck_id')->field("barcode,clc,lib_zch.zch,fzno,calino,author_code,sj_code,calino_type")->where($where)->order($order_str)->select();
            } else if ($search_type == "2") {

                $info_tmp = array();
                if (empty($_POST["barcode"])) {
                    $this->error("请添加补缺打印图书条码！");
                }

                foreach ($_POST["barcode"] as $key => $item) {
                    $info_tmp["barcode"] = $item;
                    $info_tmp["calino"] = $_POST["calino"][$key];
                    $info_tmp["clc"] = $_POST["class_no"][$key];
                    $info_tmp["zch"] = $_POST["zch"][$key];
                    $info_tmp["fzno"] = $_POST["fzno"][$key];
                    $info_tmp["sj_code"] = $_POST["sj_code"][$key];
                    $info_tmp["author_code"] = $_POST["author_code"][$key];
                    $info_tmp["calino_type"] = $_POST["calino_type"][$key];
                    $calino_list[] = $info_tmp;
                }
            } else {
                $this->error("请选择有效的打印方式！");
            }

            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
            $maper = array(\BookCalino::CLC_TYPE_ZCH => \BookCalino::getDiffname(\BookCalino::CLC_TYPE_ZCH), \BookCalino::CLC_TYPE_AUTHORCODE => \BookCalino::getDiffname(\BookCalino::CLC_TYPE_AUTHORCODE), \BookCalino::CLC_TYPE_SJCODE => \BookCalino::getDiffname(\BookCalino::CLC_TYPE_SJCODE));

            foreach ($calino_list as $key => $item) {
                $calino_type = ($item["calino_type"] && isset($maper[$item["calino_type"]]) ? $item["calino_type"] : \BookCalino::CLC_TYPE_ZCH);
                $calino_list[$key]["zch"] = $item[$maper[$item["calino_type"]]];
            }

            $this->assign("calino_cnt", count($calino_list));
            $this->assign("calino_list", $calino_list);
            return view();
        } else {
            $this->error("请选择有效的打印方式！");
        }
    }

    public function addcnfAction()
    {
        if (!$this->isPost) {
            $this->error('新增失败！请使用POST表单提交参数!');
        }
        $mod_booklab_cnf = d("Booklab_cnf");
        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $add_data = [
            'booklab_cnf_id' => input('booklab_cnf_id/d'),
            'cnf_name' => input('cnf_name'),
            'paper_weight' => input('paper_weight'),
            'bl_width' => input('bl_width'),
            'line_num' => input('line_num'),
            'bl_font' => input('bl_font'),
            'bl_right' => input('bl_right'),
            'bl_bold' => input('bl_bold'),
            'paper_height' => input('paper_height'),
            'bl_height' => input('bl_height'),
            'col_num' => input('col_num'),
            'word_size' => input('word_size'),
            'bl_bottom' => input('bl_bottom'),
            'bl_repeat' => input('bl_repeat'),
            'bl_align' => input('bl_align'),
            'border_show' => input('border_show'),
        ];
        unset($add_data["booklab_cnf_id"]);
        $add_data["user_id"] = $this->_user_info["user_id"];
        $add_data["tsg_code"] = $this->_user_info["tsg_code"];

        try {
            $mod_booklab_cnf->startTrans();
            $booklab_cnf_id = $mod_booklab_cnf->create($add_data)->result;

            if ($booklab_cnf_id !== false) {
                $cnf_ext_data = array();
                $field_type_list = input('field_type/a');
                foreach ($field_type_list as $key => $item) {
                    $cnf_ext_data[] = array("booklab_cnf_id" => trim($booklab_cnf_id), "field_type" => trim($item), "field_type_order" => isset($_POST["field_type_order"][$key]) ? intval($_POST["field_type_order"][$key]) : "0", "field_order" => isset($_POST["field_order"][$key]) ? intval($_POST["field_order"][$key]) : "0", "pos_sp" => isset($_POST["pos_sp"][$key]) ? intval($_POST["pos_sp"][$key]) : "0", "pos_cz" => isset($_POST["pos_cz"][$key]) ? intval($_POST["pos_cz"][$key]) : "0", "word_qz" => isset($_POST["word_qz"][$key]) ? trim($_POST["word_qz"][$key]) : "", "word_hz" => isset($_POST["word_hz"][$key]) ? trim($_POST["word_hz"][$key]) : "", "font_size" => isset($_POST["font_size"][$key]) ? trim($_POST["font_size"][$key]) : "", "is_show" => isset($_POST["is_show"][$key]) ? trim($_POST["is_show"][$key]) : "0", "is_br" => isset($_POST["is_br"][$key]) ? trim($_POST["is_br"][$key]) : "0");
                }

                $is_success = $mod_booklab_cnf_ext->insertAll($cnf_ext_data);

                if ($is_success === false) {
                    $mod_booklab_cnf->rollback();
                    $this->error('新增失败！错误提示:保存字段配置失败!');
                }

                $mod_booklab_cnf->commit();
                $this->result($booklab_cnf_id, 1);
            } else {
                $this->error('新增失败:插入数据库失败');
            }
        } catch (Exception $e) {
            $mod_booklab_cnf->rollback();
            $this->error('新增失败！错误提示:' . $e->getMessage());
        }
    }

    public function editcnfAction()
    {
        $booklab_cnf_id = input('booklab_cnf_id/d');
        if (!$this->isPost) {
            $this->error('保存失败！请使用POST表单提交参数!');
        }
        $mod_booklab_cnf = d("Booklab_cnf");
        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $add_data = [
            'booklab_cnf_id' => input('booklab_cnf_id/d'),
            'cnf_name' => input('cnf_name'),
            'paper_weight' => input('paper_weight'),
            'bl_width' => input('bl_width'),
            'line_num' => input('line_num'),
            'bl_font' => input('bl_font'),
            'bl_right' => input('bl_right'),
            'bl_bold' => input('bl_bold'),
            'paper_height' => input('paper_height'),
            'bl_height' => input('bl_height'),
            'col_num' => input('col_num'),
            'word_size' => input('word_size'),
            'bl_bottom' => input('bl_bottom'),
            'bl_repeat' => input('bl_repeat'),
            'bl_align' => input('bl_align'),
            'border_show' => input('border_show'),
        ];
        unset($add_data["booklab_cnf_id"]);
        unset($add_data["cnf_name"]);

        $booklab_cnf_info = $mod_booklab_cnf->where("booklab_cnf_id=$booklab_cnf_id")->find();
        if ($booklab_cnf_info["tsg_code"] != $this->_user_info["tsg_code"]) {
            $this->error("保存失败！错误提示:无权限更新此模板!");
        }
        try {
            $mod_booklab_cnf->startTrans();
            $is_success = $mod_booklab_cnf->update($add_data, ['booklab_cnf_id' => $booklab_cnf_id])->result;

            if ($is_success !== false) {
                $cnf_ext_data = array();
                $field_type_list = input('field_type/a');
                foreach ($field_type_list as $key => $item) {
                    $cnf_ext_data = array("field_type_order" => isset($_POST["field_type_order"][$key]) ? intval($_POST["field_type_order"][$key]) : "0", "field_order" => isset($_POST["field_order"][$key]) ? intval($_POST["field_order"][$key]) : "0", "pos_sp" => isset($_POST["pos_sp"][$key]) ? intval($_POST["pos_sp"][$key]) : "0", "pos_cz" => isset($_POST["pos_cz"][$key]) ? intval($_POST["pos_cz"][$key]) : "0", "word_qz" => isset($_POST["word_qz"][$key]) ? trim($_POST["word_qz"][$key]) : "", "word_hz" => isset($_POST["word_hz"][$key]) ? trim($_POST["word_hz"][$key]) : "", "font_size" => isset($_POST["font_size"][$key]) ? trim($_POST["font_size"][$key]) : "", "is_show" => isset($_POST["is_show"][$key]) ? trim($_POST["is_show"][$key]) : "0", "is_br" => isset($_POST["is_br"][$key]) ? trim($_POST["is_br"][$key]) : "0");
                    $is_success = $mod_booklab_cnf_ext->update($cnf_ext_data, ['booklab_cnf_id' => $booklab_cnf_id, 'field_type' => $item])->result;
                    if ($is_success === false) {
                        $mod_booklab_cnf->rollback();
                        $this->error('保存模板失败！错误提示:保存模板字段配置失败!');
                    }
                }

                $mod_booklab_cnf->commit();
                $this->success('保存模板成功！');
            } else {
                $this->error('保存模板失败！');
            }
        } catch (Exception $e) {
            $mod_booklab_cnf->rollback();
            $this->error('保存失败!');
        }
    }

    public function get_cnfAction()
    {
        $booklab_cnf_id = input('booklab_cnf_id/d');
        $mod_booklab_cnf = d("Booklab_cnf");
        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $booklab_cnf = $mod_booklab_cnf->where("booklab_cnf_id=$booklab_cnf_id")->find();
        unset($booklab_cnf["cnf_name"]);
        $booklab_cnf["fields_cnf"] = $mod_booklab_cnf_ext->where("booklab_cnf_id=$booklab_cnf_id")->order("field_type_order")->select();
        $this->result($booklab_cnf, 1);
    }

    public function dropcnfAction()
    {
        $booklab_cnf_id = input('booklab_cnf_id/d');
        $mod_booklab_cnf = d("Booklab_cnf");
        $mod_booklab_cnf_ext = d("booklab_cnf_ext");
        $booklab_cnf_info = $mod_booklab_cnf->where("booklab_cnf_id=$booklab_cnf_id")->find();

        if ($booklab_cnf_info["tsg_code"] != $this->_user_info["tsg_code"]) {
            $this->error("删除失败！错误提示:无权限删除此模板!");
        }

        $is_success = $mod_booklab_cnf->where("booklab_cnf_id=$booklab_cnf_id")->delete();

        if ($is_success) {
            $is_success = $mod_booklab_cnf_ext->where("booklab_cnf_id=$booklab_cnf_id")->delete();
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_booklab_cnf->getError());
        }
    }

    public function _get_order_list()
    {
        return array("calino" => l("calino"), "barcode" => l("barcode"), "add_time" => l("dck_add_time"));
    }

    public function get_calinoAction()
    {
        $barcode = $this->_post("barcode");
        $class_range = $this->_post("class_range");
        $range_arr = explode(",", $class_range);

        if (empty($barcode)) {
            $this->error('图书条码不能为空');
        }

        $mod_dck = d("Dck");
        $mod_zch = d("Zch");
        $dck_info = $mod_dck->field("dck_id,barcode,calino")->where("tsg_code ='{$this->_user_info["tsg_code"]}' AND barcode='$barcode'")->find();

        if (empty($dck_info)) {
            $this->error('未找到该条码信息!');
        }

        $zch_info = $mod_zch->where(" dck_id={$dck_info["dck_id"]}")->find();
        if (empty($zch_info)) {
            $this->error('未找到该条码信息!');
        }

        unset($dck_info["dck_id"]);
        $dck_info["clc"] = $zch_info["clc"];
        $dck_info["zch"] = $zch_info["zch"];
        $dck_info["fzno"] = $zch_info["fzno"];
        $dck_info["author_code"] = $zch_info["author_code"];
        $dck_info["sj_code"] = $zch_info["sj_code"];
        $dck_info["calino_type"] = $zch_info["calino_type"];

        if (!in_array(ucfirst(substr($dck_info["clc"], 0, 1)), $range_arr)) {
            $this->error('该条码信息不在指定分类段!');
        }
        $this->result($dck_info, 1);
    }

    public function export_barcodeAction()
    {
        $barcode_list = array();
        $barcode = input('barcode/a');
        if (empty($barcode)) {
            $this->alertMsg('条码列表为空无法导出!');
        }

        foreach ($barcode as $key => $item) {
            $barcode_list[] = $item;
        }
        $file_buff = implode("\r\n", $barcode_list);
        $savePath = ROOT_PATH . 'public/' . config('upload_path') . 'tempfiles/' . $this->_user_info["user_id"] . '/booklab_' . date("YmdHis") . ".txt";
        $dir = @dirname($savePath);

        if (!file_exists($dir)) {
            @mkdir($dir, 504);
        }

        file_put_contents($savePath, $file_buff);
        import("ORG\Net\Http", EXTEND_PATH, '.class.php');
        $fname = basename($savePath);
        \Http::download($savePath, $fname, $file_buff, 1800);
        @unlink($savePath);
    }

    public function import_barcodeAction()
    {
        if (!$this->isPost) {
            $this->assign("bcode_list", "[]");
            return view();
        } else {
            $file = request()->file('marc_file');
            if (!$file) {
                $this->error('请上传文件!');
            }
            $dir = config('upload_path') . 'tempfiles/' . $this->_user_info["user_id"] . '/';
            $info = $file->validate(['size' => 2147483648, 'ext' => 'txt'])->move(ROOT_PATH . 'public/' . $dir);
            if (!$info) {
                $this->error($file->getError());
            }
            $file_path = ROOT_PATH . 'public/' . $dir . $info->getSaveName();
            $file_buff = file($file_path);
            @unlink($file_path);

            $barcode_list = array();
            $barcode_list_order = array();

            foreach ($file_buff as $key => $item) {
                $barcode_list_order[$key] = trim($item);
                $barcode_list[$key] = "'" . trim($item) . "'";
            }

            $barcode_list_order = array_unique($barcode_list_order);
            $barcode_list_order = implode(",", $barcode_list_order);
            $barcode_list = array_unique($barcode_list);
            $barcode_list = (!empty($barcode_list) ? implode(",", $barcode_list) : "'0'");
            $mod_dck = d("Dck");
            $re_list = $mod_dck->join("lib_zch", 'lib_dck.dck_id=lib_zch.dck_id')
                ->field("lib_zch.clc,lib_zch.zch,lib_zch.fzno,lib_dck.calino,barcode,author_code,sj_code,calino_type")
                ->where("lib_dck.tsg_code='{$this->_user_info["tsg_code"]}' AND barcode in($barcode_list)")->orderRaw("FIND_IN_SET(barcode,'$barcode_list_order')")->select();
            $this->assign("bcode_list", json_encode($re_list));
            $this->assign("import_beg", "1");
            return view();
        }

    }

}
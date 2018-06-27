<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/12
 * Time: 16:04
 */

namespace app\admin\controller;

use app\admin\model\Dck;
use app\admin\model\Email;
use app\admin\model\Finance;
use app\admin\model\Lend;
use app\admin\model\LendReser;
use app\admin\model\Ltrule;
use app\admin\model\Reser;
use app\admin\model\Sms;
use app\admin\model\SysLog;
use app\admin\model\Tsg;
use think\Db;
use think\Exception;
use think\Lang;

/**
 * 系统参数 -> 分馆管理
 * Class TsgController
 * @package app\admin\controller
 */
class TsgController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/tsg.php']);
    }

    /**
     * 分馆管理
     */
    public function indexAction()
    {
        return view();
    }

    /**
     * 异步获取列表
     */
    public function getJsonListAction()
    {
        $params = $this->getQueryParams();//分页,排序,查询参数
        if (!$this->_user_info["is_main_tsg"]) {
            $condition["tsg_code"] = $this->_user_info["tsg_code"];
        }
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }
        $list = Tsg::getPageList($condition, $params->limit, $params->order);
        $count = Tsg::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        if ($this->isPost) {
            $mod_tsg = d("Tsg");
            $add_data = array(
                'tsg_code' => input('tsg_code'),
                'tsg_name' => input('tsg_name'),
                'tsg_close' => input('tsg_close'),
                'tsg_addr' => input('tsg_addr'),
                'tsg_phone' => input('tsg_phone'),
                'tsg_telno' => input('tsg_telno'),
                'tsg_post' => input('tsg_post'),
                'tsg_email' => input('tsg_email'),
                'tsg_type' => input('tsg_type'),
            );

            if (!$add_data["tsg_code"]) {
                $this->error(l("tsg_code_require"));
            }
            if (preg_match("/[^0-9a-zA-Z]/", $add_data["tsg_code"])) {
                $this->error(l("tsg_code_limit"));
            }
            if (!$mod_tsg->unique($add_data["tsg_code"])) {
                $this->error(l("tsg_code_exist"));
            }

            try {
                $mod_tsg->startTrans();
                $is_success = $mod_tsg->create($add_data)->result;

                if ($is_success === false) {
                    $mod_tsg->rollback();
                    $this->error('新增失败:数据库新增失败');
                }
                $is_success = $mod_tsg->addDefaultData($add_data["tsg_code"]);
                if ($is_success === false) {
                    $mod_tsg->rollback();
                    $this->error("新增失败:" . $mod_tsg->getError());
                }

                $mod_tsg->commit();
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_TSG_ADD, $this->_user_info, array("db1" => $add_data["tsg_code"], "op_desc" => "[#],分馆名称:{$add_data["tsg_name"]}"));
                $this->success('新增成功');
            } catch (Exception $e) {
                $mod_tsg->rollback();
                $this->error('新增失败：程序出现异常');
            }
        }
        return view('edit');
    }

    public function editAction()
    {
        $tsg_code = input('tsg_code');
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->where("tsg_code='$tsg_code'")->find();

        if (!$this->isPost) {
            if (!$tsg_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if (($tsg_info["tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
            if (!$tsg_info) {
                $this->alertMsg('未找到此图书馆数据');
            }

            $this->assign('tsg_info', $tsg_info);
            return view();
        } else {
            if (!$tsg_info) {
                $this->error(l("not_found_data"));
            }
            if (($tsg_info["tsg_code"] != $this->_user_info["tsg_code"]) && !$this->_user_info["is_main_tsg"]) {
                $this->error(l("not_access_edit_data"));
            }
            $save_data = array(
                'tsg_name' => input('tsg_name'),
                'tsg_close' => input('tsg_close'),
                'tsg_addr' => input('tsg_addr'),
                'tsg_phone' => input('tsg_phone'),
                'tsg_telno' => input('tsg_telno'),
                'tsg_post' => input('tsg_post'),
                'tsg_email' => input('tsg_email'),
                'tsg_type' => input('tsg_type'),
            );
            if (($tsg_code == "999") && ($save_data["tsg_close"] == 1)) {
                $this->error(l("canot_close_maintsg"));
            }

            unset($save_data["tsg_code"]);
            $is_success = $mod_tsg->update($save_data, ['tsg_code' => $tsg_code])->result;

            if ($is_success !== false) {
                $mod_sys_log = d("Sys_log");
                $mod_sys_log->addlog(SysLog::OP_TYPE_TSG_SAVE, $this->_user_info, array("db1" => $tsg_info["tsg_code"], "op_desc" => "[#],分馆名称:{$save_data["tsg_name"]}"));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！错误提示:' . $mod_tsg->getError());
            }

        }

    }


    public function dropAction()
    {
        $tsg_code = input('tsg_code');
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->where("tsg_code='$tsg_code'")->find();

        if (!$this->isPost) {
            if (!$tsg_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if ($tsg_code == "999") {
                $this->alertMsg('中心馆不容许删除');
            }
            if ($this->_user_info["user_name"] != "admin") {
                $this->alertMsg('仅admin用户可以删除分馆');
            }

            $op_code = mt_rand();
            $this->assign("op_code", $op_code);
            return view();
        } else {
            if (!$tsg_info) {
                $this->error(l("not_found_data"));
            }
            if ($tsg_code == "999") {
                $this->error('中心馆不容许删除');
            }
            if ($this->_user_info["user_name"] != "admin") {
                $this->error('仅admin用户可以删除分馆');
            }

            $op_code = input('op_code');
            $check_code = input('check_code');

            if (!$op_code) {
                $this->error('操作码不能为空');
            }
            if (!$check_code) {
                $this->error('验证码不能为空');
            }

            $check_code = pack("H*", $check_code);
            $key_str = "b4e3063f5739c9B802bcd21Df31a9J82";
            $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB), MCRYPT_RAND);
            $check_code = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key_str, $check_code, MCRYPT_MODE_ECB, $iv);
            $check_code = unserialize($check_code);
            $check_time = $check_code["time"];
            $check_code = $check_code["code"];
            $check_code -= 2111888;
            $check_code = $check_code * 2;

            if ($check_code != $op_code) {
                $this->error('验证码错误');
            }
            if (($check_time + 300) <= time()) {
                $this->error('该验证码已超时(五分钟有效期)');
            }

            $task_cnt = 0;
            $drop_table_list = array(
                array("sql" => "DELETE from lib_annou WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_batch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_bm_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_booklab_cnf_ext WHERE booklab_cnf_id in(select booklab_cnf_id from lib_booklab_cnf WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_bookseller WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_cf_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_check_batch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_cost WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_dc_dispatch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_dc_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_dck WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_destine WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_destine_batch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_opac_log WHERE dz_id in(select dz_id from lib_dzgl WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_dzgl WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_dz_type WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_dz_unit WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_email WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_email_tpl WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_finance WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_holiday WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_index_add_time_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_clc_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_clc_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_firstauthor_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_firstauthor_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_isbn_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_isbn_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_op_user_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_pubdate_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_publisher_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_publisher_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_subject_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_subject_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_title_order_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_title_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_tsg_code_s WHERE bid in(select book_share_id from lib_book_share WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_book_share WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_index_cataloger WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_catatime WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_clc WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_clc_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_firstauthor WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_firstauthor_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_isbn WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_isbn_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_pubdate WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_publisher WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_publisher_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_subject WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_subject_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_title WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_title_order WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_index_tsg_code WHERE bid in(select book_id from lib_book WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_jdbm_cnf WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_lend WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_lend_reser WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_lt_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_ltrule WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_ltype WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_mt_script WHERE user_id in(select user_id from lib_user WHERE belong_tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_report_gs WHERE user_id in(select user_id from lib_user WHERE belong_tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_upload WHERE user_id in(select user_id from lib_user WHERE belong_tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_user_role WHERE user_id in(select user_id from lib_user WHERE belong_tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_user_z3950 WHERE user_id in(select user_id from lib_user WHERE belong_tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_user WHERE belong_tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_qk_rel WHERE qk_id in(select qk_id from lib_qk WHERE tsg_code='$tsg_code')"),
                array("sql" => "DELETE from lib_qk WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_qk_batch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_qk_cycle WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_qk_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_reser WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_role WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_role_acl WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_sms WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_sms_tpl WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_sys_log WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_tsg WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_tsg_site WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_ys WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_z3950 WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_zch WHERE tsg_code='$tsg_code'"),
                array("sql" => "DELETE from lib_zch_max WHERE tsg_code='$tsg_code'")
            );
            $mod_tsg = d("Tsg");

            try {
                $mod_tsg->startTrans();
                $task_pre = 100 / count($drop_table_list);

                foreach ($drop_table_list as $item) {
                    $is_success = Db::query($item["sql"]);
                    if ($is_success === false) {
                        $mod_tsg->rollback();
                        $arr_tmp = explode(" ", $item["sql"]);
                        $this->error("删除失败:删除数据表$arr_tmp[2]失败");
                    }
                    $task_cnt += $task_pre;
                }
                $mod_tsg->commit();
                $this->success('删除图书馆成功');
            } catch (Exception $e) {
                $mod_tsg->rollback();
                $this->error('删除失败:程序处理出现异常');
            }
        }
    }

    public function configAction()
    {
        $mod_dck = d("Dck");
        $mod_tsg = d("Tsg");
        $dck_info = $mod_dck->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        $tsg_info = $mod_tsg->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        $has_dck = !empty($dck_info);
        $this->assign('_user_info', $this->_user_info);
        if (!$this->isPost) {
            $class_types = $this->get_classtype();
            $this->assign("class_types", $class_types);
            $this->assign("has_dck", $has_dck);
            $this->assign("tsg_info", $tsg_info);
            $syscnf = c("syscnf");
            $this->assign("syscnf", $syscnf);
            return view();

        } else {
            if ($this->_user_info["is_main_tsg"]) {
                $syscnf_data = array("ebook_addr" => input('ebook_addr', ''));
                loadDataSave("syscnf", $syscnf_data);
            }

            $tsg_save_data = [
                "calino_type" => input('calino_type/d'),
                "calino_has_sep2" => input('calino_has_sep2/d'),
                "is_calino_cf" => input('is_calino_cf/d'),
                "loginno_accord" => input('loginno_accord/d'),
                "lend_is_owe" => input('lend_is_owe/d'),
                "lend_is_sound" => input('lend_is_sound/d'),
                "lend_is_inter_re" => input('lend_is_inter_re/d'),
                "room_mode" => input('room_mode/d', 1),
                "lend_mode" => input('lend_mode/d', 1),
                "barcode_len" => input('barcode_len', null)
            ];
            $tsg_save_data['barcode_len'] = $tsg_save_data['barcode_len'] ?: null;
            $is_success = $mod_tsg->update($tsg_save_data, ['tsg_code' => $this->_user_info["tsg_code"]])->result;
            if ($is_success !== false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！错误提示:" . $mod_tsg->getError());
            }
        }
    }

    public function get_classtype()
    {
        import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
        return array(\BookCalino::CLC_TYPE_ZCH => l("CLC_TYPE_ZCH"), \BookCalino::CLC_TYPE_AUTHORCODE => l("CLC_TYPE_AUTHORCODE"), \BookCalino::CLC_TYPE_SJCODE => l("CLC_TYPE_SJCODE"));
    }

    public function infoAction()
    {

        if ($this->_user_info["is_main_tsg"]) {
            $tsg_code = input('tsg_code', $this->_user_info["tsg_code"]);
            $_GET["tsg_code"] = $tsg_code;
            if ($tsg_code == "all_tsg") {
                $tsg_code = "";
            }
        } else {
            $tsg_code = $this->_user_info["tsg_code"];
        }

        $this->assign("tsg_code", $tsg_code);
        $mod_tsg = d("Tsg");
        import('DataTool\DataTool', EXTEND_PATH, '.class.php');

        $date_arr = \DataTool::getDateBetween($_GET, "time_beg", "time_end");

        $data = $this->getTjData(array("base"), $tsg_code, $date_arr["beg"], $date_arr["end"]);
        $this->assign("data", $data);
        $tsg_list = $mod_tsg->getMap();
        $this->assign("tsg_list", $tsg_list);
        return view();
    }

    private function getTjData($type, $tsg_code, $beg_time, $end_time)
    {
        if (!is_array($type)) {
            $type = array($type);
        }

        $data = array();
        $mod_dck = d("Dck");
        $mod_book = d("Book");
        $mod_lend = d("Lend");
        $mod_tsg = d("Tsg");
        $mod_tsg_site = d("Tsg_site");
        $mod_doctype = d("Doctype");
        $mod_dz = d("dzgl");
        $mod_lend = d("Lend");
        $mod_finance = d("Finance");
        $mod_lend_reser = d("Lend_reser");
        $mod_reser = d("Reser");
        $mod_dz_unit = d("Dz_unit");
        $mod_dz_type = d("Dz_type");
        $mod_email = d("Email");
        $mod_sms = d("Sms");
        $mod_user = d("User");
        $mod_role = d("Role");
        $mod_ltrule = d("Ltrule");
        $where_dck = array();
        $where_tsg_site = array();
        $where_dz = array();
        $where_lend = array();
        $where_finance = array();
        $where_lend_reser = array();
        $where_reser = array();
        $where_dz_unit = array();
        $where_dz_type = array();
        $where_email = array();
        $where_sms = array();
        $where_user = array();
        $where_role = array();
        $where_ltrule = array();

        if ($tsg_code) {
            $where_dck["tsg_code"] = $tsg_code;
            $where_dz["tsg_code"] = $tsg_code;
            $where_tsg_site["tsg_code"] = $tsg_code;
            $where_lend["tsg_code"] = $tsg_code;
            $where_finance["tsg_code"] = $tsg_code;
            $where_lend_reser["tsg_code"] = $tsg_code;
            $where_reser["tsg_code"] = $tsg_code;
            $where_dz_unit["tsg_code"] = $tsg_code;
            $where_dz_type["tsg_code"] = $tsg_code;
            $where_email["tsg_code"] = $tsg_code;
            $where_sms["tsg_code"] = $tsg_code;
            $where_user["belong_tsg_code"] = $tsg_code;
            $where_role["tsg_code"] = $tsg_code;
            $where_ltrule["tsg_code"] = $tsg_code;
        }

        if ($beg_time) {
            $where_dck["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_dz["dz_add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_lend["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_finance["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_lend_reser["lend_reser_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_reser["reser_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_email["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_sms["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
            $where_user["add_time"] = array(
                "between",
                array($beg_time, $end_time)
            );
        }

        if (in_array("base", $type)) {
            $data["tsg_site_cnt"] = $mod_tsg_site->where($where_tsg_site)->count("0");
            $data["doctype_cnt"] = $mod_doctype->count("0");
            $data["dz_unit_cnt"] = $mod_dz_unit->where($where_dz_unit)->count("0");
            $data["dz_type_cnt"] = $mod_dz_type->where($where_dz_type)->count("0");
            $data["dz_all_cnt"] = $mod_dz->where($where_dz)->count("0");
            $where_dz1 = $where_dz;
            $where_dz1["dz_status"] = "退证";
            $data["dz_cnt_tz"] = $mod_dz->where($where_dz1)->count("0");
            $where_dz1["dz_status"] = "暂停";
            $data["dz_cnt_zt"] = $mod_dz->where($where_dz1)->count("0");
            $where_dz1["dz_status"] = "挂失";
            $data["dz_cnt_gs"] = $mod_dz->where($where_dz1)->count("0");
            $where_dz1["dz_status"] = "注销";
            $data["dz_cnt_zx"] = $mod_dz->where($where_dz1)->count("0");
            $where_email["send_status"] = Email::EMAIL_STATUS_OK;
            $data["email_cnt"] = $mod_email->where($where_email)->count("0");
            $where_sms["send_status"] = Sms::SMS_STATUS_OK;
            $data["sms_cnt"] = $mod_sms->where($where_sms)->count("0");
            $where_finance1 = $where_finance;
            $where_finance1["fee_mode"] = Finance::FEE_MODE_LEND_LOSE;
            $data["dz_cnt_diushi"] = $mod_finance->where($where_finance1)->count("DISTINCT dz_id");
            $where_finance1["fee_mode"] = Finance::FEE_MODE_LEND_DIRTY;
            $data["dz_cnt_wushun"] = $mod_finance->where($where_finance1)->count("DISTINCT dz_id");
            $where_finance2 = $where_finance;
            $where_finance2["fin_type"] = Finance::FIN_TYPE_IN;
            $data["finance_all_in"] = $mod_finance->where($where_finance2)->sum("fee_money");
            $where_finance2["fin_type"] = Finance::FIN_TYPE_OUT;
            $data["finance_all_out"] = $mod_finance->where($where_finance2)->sum("fee_money");
            $where_finance2["fin_type"] = Finance::FIN_TYPE_IN;
            $where_finance2["fee_type"] = Finance::FEE_TYPE_NOPAY;
            $data["finance_not_pay"] = $mod_finance->where($where_finance2)->sum("fee_money");
            $data["user_cnt"] = $mod_user->where($where_user)->count("0");
            $data["role_cnt"] = $mod_role->where($where_role)->count("0");


            import('Task\TaskBase', EXTEND_PATH, '.class.php');
            $webback_obj = \TaskBase::getTask("WebBackupTask");
            $file_list_raw = $webback_obj->getFileList();
            $data["databack_cnt"] = count($file_list_raw);

            $where_lend1 = $where_lend;
            $where_lend1["lend_status"] = Lend::LEND_STATUS_ON;
            $data["lend_out_cnt"] = $mod_lend->where($where_lend1)->count("DISTINCT dck_id");
            $where_lend1["re_cnt"] = array("gt", 0);
            $data["lend_out_re_cnt"] = $mod_lend->where("re_cnt>0 AND lend_status=" . Lend::LEND_STATUS_ON)->count("DISTINCT dck_id");
            $where_lend3 = $where_lend;
            $time_now = time();
            $where_lend3["lend_status"] = Lend::LEND_STATUS_ON;
            $where_lend3["must_time"] = array("lt", $time_now);
            $data["dz_cnt_chaoqi"] = $mod_lend->where($where_lend3)->count("DISTINCT dz_id");
            $data["book_cnt_chaoqi"] = $mod_lend->where($where_lend3)->count("DISTINCT dck_id");
            $where_lend2 = $where_lend;
            $where_lend2["lend_status"] = Lend::LEND_STATUS_OFF;
            $data["lend_re_cnt"] = $mod_lend->where($where_lend2)->count("0");
            $where_lend_reser["lend_reser_status"] = LendReser::LEND_RESER_STATUS_ADD;
            $data["lend_reser_cnt"] = $mod_lend_reser->where($where_lend_reser)->count("0");
            $where_reser["reser_status"] = array(
                "in",
                array(Reser::RESER_STATUS_ADD, Reser::RESER_STATUS_BOOK, Reser::RESER_STATUS_NOITE)
            );
            $data["reser_cnt"] = $mod_reser->where($where_reser)->count("0");
            $where_ltrule["rule_type"] = Ltrule::LT_IS_RULE_TYPE_EXT;
            $data["ltrule_cnt"] = $mod_ltrule->where($where_ltrule)->count("0");
        }
        if (in_array("dck", $type)) {
            $dck_info = $mod_dck->field("count(DISTINCT book_id) as book_cnt,count(0) as dck_all_cnt,sum(price) as dck_all_money")->where($where_dck)->find();
            $data["book_cnt"] = ($dck_info["book_cnt"] ? $dck_info["book_cnt"] : 0);
            $data["dck_all_cnt"] = ($dck_info["dck_all_cnt"] ? $dck_info["dck_all_cnt"] : 0);
            $data["dck_all_money"] = ($dck_info["dck_all_money"] ? $dck_info["dck_all_money"] : 0);
            $where_dck1 = $where_dck;
            $where_dck1["dt"] = Dck::DT_TYPE_BOOK;
            $dck_info1 = $mod_dck->field("count(0) as dck_cnt,sum(price) as dck_money")->where($where_dck1)->find();
            $data["dck_cnt"] = ($dck_info1["dck_cnt"] ? $dck_info1["dck_cnt"] : 0);
            $data["dck_money"] = ($dck_info1["dck_money"] ? $dck_info1["dck_money"] : 0);
            $data["book_ratio"] = ($data["dck_cnt"] && $data["book_cnt"]) ? round($data["dck_cnt"] / $data["book_cnt"], 2) : 0;
            $where_dck5 = $where_dck;
            $where_dck5["status"] = array(
                "in",
                array("分编", "在架", "借出", "续借", "租出", "续租", "预借", "阅读", "装订")
            );
            $dck_valid_cnt = $mod_dck->where($where_dck5)->count("0");
            $where_dz2 = $where_dz;
            $where_dz2["dz_status"] = "有效";
            $dz_valid_cnt = $mod_dz->where($where_dz2)->count("0");
            $data["dck_per_cnt"] = ($dck_valid_cnt && $dz_valid_cnt) ? round($dck_valid_cnt / $dz_valid_cnt, 2) : 0;
            $where_dck2 = $where_dck;
            $where_dck2["status"] = "停用";
            $data["dck_ty_cnt"] = $mod_dck->where($where_dck2)->count("0");
            $where_dck2["status"] = "遗失";
            $data["dck_ys_cnt"] = $mod_dck->where($where_dck2)->count("0");
            $where_dck2["status"] = "损坏";
            $data["dck_sh_cnt"] = $mod_dck->where($where_dck2)->count("0");
            $where_dck2["status"] = "剔除";
            $data["dck_tc_cnt"] = $mod_dck->where($where_dck2)->count("0");
            $where_dck3 = $where_dck;
            $where_dck3["dt"] = Dck::DT_TYPE_QK;
            $data["qk_cnt"] = $mod_dck->where($where_dck3)->count("0");
            $data["qk_money"] = $mod_dck->where($where_dck3)->sum("price");
            $data["qk_money"] = ($data["qk_money"] ? $data["qk_money"] : 0);
            $lend_info = $mod_lend->field("count(DISTINCT book_id) as book_cnt,count(DISTINCT dck_id) as dck_cnt")->where($where_lend)->find();
            $data["book_ly_ratio"] = ($lend_info["book_cnt"] && $data["book_cnt"]) ? round(($lend_info["book_cnt"] / $data["book_cnt"]) * 100, 2) : 0;
            $data["book_lt_ratio"] = ($lend_info["dck_cnt"] && $data["dck_all_cnt"]) ? round(($lend_info["dck_cnt"] / $data["dck_all_cnt"]) * 100, 2) : 0;
        }


        if (in_array("dck1", $type)) {
            $where_dck4 = $where_dck;

            if (!isset($where_dck["tsg_code"])) {
                $data["dck_cnt_tg"] = 0;
                $data["dck_cnt_bg"] = 0;
            } else {
                $where_dck4["tsg_code_has"] = array("eq", $where_dck["tsg_code"]);
                $where_dck4["tsg_code"] = array("neq", $where_dck["tsg_code"]);
                $data["dck_cnt_tg"] = $mod_dck->where($where_dck4)->count("0");
                $where_dck4["tsg_code_has"] = array("neq", $where_dck["tsg_code"]);
                $where_dck4["tsg_code"] = array("eq", $where_dck["tsg_code"]);
                $data["dck_cnt_bg"] = $mod_dck->where($where_dck4)->count("0");
            }

            $where_dz3 = $where_dz;
            $where_dz3["dz_status"] = "有效";
            $data["dz_valid_cnt"] = $mod_dz->where($where_dz3)->count("0");
            $data["lend_cnt"] = $mod_lend->where($where_lend)->count("0");
            $data["lend_per_cnt"] = ($data["lend_cnt"] && $data["dz_valid_cnt"]) ? round($data["lend_cnt"] / $data["dz_valid_cnt"], 2) : 0;
            $data["dz_cnt_daoguan"] = $mod_lend->where($where_lend)->count("DISTINCT dz_id");
            $data["dz_ratio_daoguan"] = ($data["dz_cnt_daoguan"] && $data["dz_valid_cnt"]) ? round(($data["dz_cnt_daoguan"] / $data["dz_valid_cnt"]) * 100, 2) : 0;
        }

        foreach ($data as $key => $item) {
            if (empty($item)) {
                $data[$key] = 0;
            }
        }

        return $data;
    }

    public function get_tj_jsonAction()
    {
        $type = (isset($_POST["type"]) ? explode(",", trim($_POST["type"])) : "");

        if ($this->_user_info["is_main_tsg"]) {
            $tsg_code = (isset($_POST["tsg_code"]) ? trim($_POST["tsg_code"]) : $this->_user_info["tsg_code"]);
            $_GET["tsg_code"] = $tsg_code;

            if ($tsg_code == "all_tsg") {
                $tsg_code = "";
            }
        } else {
            $tsg_code = $this->_user_info["tsg_code"];
        }

        import('DataTool\DataTool', EXTEND_PATH, '.class.php');
        $date_arr = \DataTool::getDateBetween($_POST, "time_beg", "time_end");
        $data = $this->getTjData($type, $tsg_code, $date_arr["beg"], $date_arr["end"]);
        $this->result($data, 1);
    }

    public function tj_excelAction()
    {
        $data = array();
        $zd_list = input('zd/a');
        foreach ($zd_list as $key => $item) {
            $data[$key] = array();

            foreach ($item as $key1 => $item1) {
                $data[$key][] = $item1;
            }
        }

        import('PHPExcel\PHPExcel', EXTEND_PATH, '.php');
        $objPHPExcel = new \PHPExcel();
        $color_font = new \PHPExcel_Style_Color("FF256799");
        $objPHPExcel->getProperties()->setCreator("weblib")->setLastModifiedBy("weblib")->setTitle("weblib")->setSubject("weblib")->setDescription("weblib")->setKeywords("weblib")->setCategory("weblib");
        $worksheet_obj = $objPHPExcel->setActiveSheetIndex(0);
        $worksheet_obj->getDefaultStyle()->getFont()->setSize(12);
        $worksheet_obj->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $worksheet_obj->getDefaultStyle()->getAlignment()->setWrapText(true);
        $row_i = 1;

        foreach ($data as $row) {
            $col_i = 0;

            foreach ($row as $cell) {
                $worksheet_obj->setCellValueByColumnAndRow($col_i, $row_i, $cell["val"]);
                $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getBorders()->getLeft()->setColor(new \PHPExcel_Style_Color("FFFF0000"));
                $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getBorders()->getRight()->setColor(new \PHPExcel_Style_Color("FFFF0000"));
                $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getBorders()->getTop()->setColor(new \PHPExcel_Style_Color("FFFF0000"));
                $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getBorders()->getBottom()->setColor(new \PHPExcel_Style_Color("FFD8D8DA"));

                if (1 < $cell["colspan"]) {
                    $worksheet_obj->mergeCellsByColumnAndRow(0, $row_i, intval($cell["colspan"]) - 1, $row_i);
                    $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                    $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getFont()->setBold(true);
                    $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getFont()->setColor($color_font);
                } else if ($cell["type"] == "th") {
                    $worksheet_obj->getStyleByColumnAndRow($col_i, $row_i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB("E6F1F4");
                }
                $col_i++;
            }

            $worksheet_obj->getRowDimension($row_i)->setRowHeight(26);
            $row_i++;
        }

        $col_width = array(20, 15, 20, 15, 20, 15, 20, 15);

        foreach ($col_width as $key => $item) {
            $worksheet_obj->getColumnDimensionByColumn($key)->setWidth($item);
        }

        $worksheet_obj->getStyleByColumnAndRow(0, 1)->getFont()->setSize(18);
        $worksheet_obj->getStyleByColumnAndRow(0, 1)->getFont()->setBold(true);
        $worksheet_obj->getStyleByColumnAndRow(0, 1)->getFont()->setColor(new \PHPExcel_Style_Color("FFFFFFFF"));
        $worksheet_obj->getStyleByColumnAndRow(0, 1)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB("2F83A7");
        $worksheet_obj->getStyleByColumnAndRow(0, 1)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;filename=\"gqgk_excel.xls\"");
        header("Cache-Control: max-age=0");
        header("Cache-Control: max-age=1");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: cache, must-revalidate");
        header("Pragma: public");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, "Excel5");
        $objWriter->save("php://output");
    }
}
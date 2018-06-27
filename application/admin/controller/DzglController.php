<?php

namespace app\admin\controller;

use app\admin\model\Common;
use app\admin\model\Cost;
use app\admin\model\Dzgl;
use app\admin\model\DzLog;
use app\admin\model\ExpectLog;
use app\admin\model\Finance;
use app\admin\model\Tsg;
use app\admin\model\Upload;
use app\admin\model\User;
use think\Exception;
use think\Image;
use think\Lang;

/**
 * Class DzglController
 * @package app\admin\controller
 * 读者管理
 */
class DzglController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/dzgl.php']);
    }

    /**
     * 读者资料管理
     */
    public function indexAction()
    {
        $mod_dz = d("Dzgl");
        $mod_tsg = d("Tsg");
        $mod_dz_unit = d("Dz_unit");
        $mod_dz_type = d("Dz_type");

        $unit_name_list = $mod_dz_unit->get_list($this->_user_info["tsg_code"]);
        $dz_status_list = $mod_dz->get_dz_status_list();
        $dz_type_list = $mod_dz_type->get_list($this->_user_info["tsg_code"], array("field" => "dz_type_code,dz_type_name"));
        $tsg_info = $mod_tsg->field("lend_is_owe,lend_is_sound")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();

        $this->assign("tsg_info", $tsg_info);
        $this->assign("dz_type_list", $dz_type_list);
        $this->assign("unit_name_list", $unit_name_list);
        $this->assign("dz_status_list", $dz_status_list);
        return view();
    }

    /**
     * @return string
     * @throws \think\Exception
     * 图书借还跳转页面
     */
    public function operpageAction()
    {
        return $this->view->fetch('lend/operpage');
    }

    /**
     * 异步获取列表
     */
    public function getJsonListAction()
    {
        $condition = ['dz.tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition['dz.' . $search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }
        if ($params->order) {
            $params->order = 'dz.' . $params->order;
        }

        $list = Dzgl::alias('dz')->join('lib_dz_type dz_type', 'dz_type.dz_type_code=dz.dz_type_code and dz.tsg_code=dz_type.tsg_code', 'left')
            ->where($condition)->order($params->order)->limit($params->limit)->fieldRaw('dz.*,dz_type.dz_type_name')->fetchSql(false)->select();
        $count = Dzgl::alias('dz')->join('lib_dz_type dz_type', 'dz_type.dz_type_code=dz.dz_type_code and dz.tsg_code=dz_type.tsg_code')->fetchSql(false)->where($condition)->count('dz_id');

        foreach ($list as $key => $item) {
            $list[$key]['portrait'] = get_img_full_path($item['portrait']);
        }
        return $this->echoPageData($list, $count);
    }

    /**
     * @return \think\response\View
     * 读者新增
     */
    public function addAction()
    {
        if ($this->isPost) {
            $add_data = $this->_getDzData();
            $add_data['dz_code'] = input('dz_code');
            $mod_dz = d("Dzgl");
            $mod_dz->startTrans();
            $dz_id = $mod_dz->add_dz($this->_user_info, $add_data, Finance::FEE_TYPE_PAY);

            if ($dz_id !== false) {
                $mod_dz->commit();

                $mod_lt_log = d("Dz_log");
                $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_ADD, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => $add_data["dz_code"], "op_desc" => "操作:读者办证,读者证号【{$add_data["dz_code"]}】,读者姓名【{$add_data["real_name"]}】"));
                $this->success('新增读者成功');
            } else {
                $mod_dz->rollback();
                $this->error('新增失败: ' . $mod_dz->getError());
            }
        }
        return $this->editAction();
    }

    private function _getDzData()
    {
        $data = array(
            'cred_type' => input('cred_type'),
            'dz_type_code' => input('dz_type_code'),
            'cred_num' => input('cred_num'),
            'dz_status' => input('dz_status'),
            'address' => input('address'),
            'real_name' => input('real_name'),
            'birthday' => input('birthday'),
            'beg_time' => input('beg_time'),
            'email' => input('email'),
            'end_time' => input('end_time'),
            'gender' => input('gender'),
            'zip_code' => input('zip_code'),
            'phone_mob' => input('phone_mob'),
            'unit_name' => input('unit_name'),
            'phone_tel' => input('phone_tel'),
            'remark' => input('remark'),
            'portrait' => input('portrait'),
        );
        $pwd = input('pwd');
        if ($pwd) {
            $data['pwd'] = md5($pwd);
        }
        return $data;
    }

    public function editAction()
    {
        $dz_id = input('dz_id/d');
        $dz_id && ($dz_info = Dzgl::get(['dz_id' => $dz_id, 'tsg_code' => $this->adminInfo['tsg_code']]));

        if ($this->isPost) {
            $mod_dz = d("Dzgl");
            $save_data = $this->_getDzData();
            $save_data["beg_time"] = mstrtotime($save_data["beg_time"]);
            $save_data["end_time"] = mstrtotime($save_data["end_time"]);
            unset($save_data["dz_id"]);
            unset($save_data["tsg_code"]);
            unset($save_data["integral"]);
            unset($save_data["lend_num"]);
            unset($save_data["curr_lend_num"]);
            unset($save_data["reser_num"]);
            unset($save_data["curr_reser_num"]);
            unset($save_data["lend_reser_num"]);
            unset($save_data["curr_lend_reser_num"]);
            unset($save_data["renew_num"]);
            unset($save_data["violate_cnt"]);
            unset($save_data["owe_money"]);
            unset($save_data["ple_money"]);
            $mod_finance = d("Finance");
            $finan_data = array();

            if ($dz_info["dz_type_code"] != $save_data["dz_type_code"]) {
                $mod_dz_type = d("Dz_type");
                $dz_type_info = $mod_dz_type->field("dz_ple_money")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='{$save_data["dz_type_code"]}'")->find();
                $dz_type_info_old = $mod_dz_type->field("dz_ple_money")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='{$dz_info["dz_type_code"]}'")->find();

                if ($dz_type_info["dz_ple_money"] != $dz_type_info_old["dz_ple_money"]) {
                    $finan_data["tsg_code"] = $this->_user_info["tsg_code"];
                    $finan_data["add_time"] = time();
                    $finan_data["op_user"] = $this->_user_info["user_name"];
                    $finan_data["unit_name"] = $save_data["unit_name"];
                    $finan_data["real_name"] = $save_data["real_name"];
                    $finan_data["dz_code"] = $dz_info["dz_code"];
                    $finan_data["dz_id"] = $dz_id;
                    $finan_data["fee_type"] = Finance::FEE_TYPE_PAY;
                    $finan_data["fin_mode"] = Finance::FIN_MODE_MONEY;

                    if ($dz_type_info_old["dz_ple_money"] < $dz_type_info["dz_ple_money"]) {
                        $finan_data["fin_type"] = Finance::FIN_TYPE_IN;
                        $finan_data["fee_mode"] = Finance::FEE_MODE_DZCARD_ADD;
                        $diff_money = $dz_type_info["dz_ple_money"] - $dz_type_info_old["dz_ple_money"];
                        $finan_data["fee_money"] = $diff_money;
                        $save_data["ple_money"] = $dz_info["ple_money"] + $diff_money;
                    } else {
                        $finan_data["fin_type"] = Finance::FIN_TYPE_OUT;
                        $finan_data["fee_mode"] = Finance::FEE_MODE_DZCARD_SUB;
                        $diff_money = $dz_type_info_old["dz_ple_money"] - $dz_type_info["dz_ple_money"];

                        if ($dz_info["ple_money"] < $diff_money) {
                            $diff_money = $dz_info["ple_money"];
                        }

                        $finan_data["fee_money"] = "-" . $diff_money;
                        $save_data["ple_money"] = $dz_info["ple_money"] - $diff_money;

                        if ($dz_info["ple_money"] < $diff_money) {
                            $this->error("保存失败！错误提示:读者当前押金为【{$dz_info["ple_money"]}】,无法减少押金【{$diff_money}】");
                        }
                    }
                }
            }

            if (isset($save_data["dz_type_code"]) && ($save_data["dz_type_code"] != $dz_info["dz_type_code"])) {
                $need_update_ext["dz_type_code"] = $save_data["dz_type_code"];
                $dz_type_info = $mod_dz_type->field("dz_type_name")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='{$save_data["dz_type_code"]}'")->find();
                $need_update_ext["dz_type_name"] = ($dz_type_info["dz_type_name"] ? $dz_type_info["dz_type_name"] : "");
            }

            if (isset($save_data["real_name"]) && ($save_data["real_name"] != $dz_info["real_name"])) {
                $need_update_ext["real_name"] = $save_data["real_name"];
            }

            if (isset($save_data["unit_name"]) && ($save_data["unit_name"] != $dz_info["unit_name"])) {
                $need_update_ext["unit_name"] = $save_data["unit_name"];
            }

            if (isset($save_data["email"]) && ($save_data["email"] != $dz_info["email"])) {
                $need_update_ext["email"] = $save_data["email"];
            }
            if (isset($save_data["phone_mob"]) && ($save_data["phone_mob"] != $dz_info["phone_mob"])) {
                $need_update_ext["phone_mob"] = $save_data["phone_mob"];
            }

            try {
                $mod_dz->startTrans();

                if (!empty($need_update_ext)) {
                    $is_success = $mod_dz->update_ext($this->_user_info["tsg_code"], array("dz_id" => $dz_id), $need_update_ext);

                    if ($is_success === false) {
                        $mod_dz->rollback();
                        $this->error('保存失败！错误提示:更新读者关联数据失败,' . $mod_dz->getError());
                    }
                }

                if (!empty($finan_data)) {
                    $finance_id = $mod_finance->add($finan_data);
                    if ($finance_id === false) {
                        $mod_dz->rollback();
                        $this->error('保存失败！错误提示:更新读者财务数据失败');
                    }
                }

                $is_success = $mod_dz->update($save_data, ['dz_id' => $dz_id])->result;

                if ($is_success === false) {
                    $mod_dz->rollback();
                    $this->error('保存失败！错误提示:更新读者数据失败');
                }

                $mod_dz->commit();
                $mod_lt_log = d("Dz_log");
                $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_EDIT, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => $dz_info["dz_code"], "op_desc" => "操作:读者办证,读者证号【{$dz_info["dz_code"]}】,读者姓名【{$save_data["real_name"]}】"));
                $this->success('保存成功！');
            } catch (Exception $e) {
                $mod_dz->rollback();
                $this->error('保存失败:程序出现异常');
            }

        }

        $dz_model = new Dzgl();
        $time_id = date("YmdHis");
        $this->assign("time_id", $time_id);
        $flash_html = $dz_model->get_avatar($this->_user_info["user_id"], $time_id);
        $this->assign("flash_html", $flash_html);
        $this->_assign_common();
        $this->assign('dz_info', $dz_info);
        return view('edit');
    }

    public function get_dz_portAction()
    {
        $time_id = input('time_id', '');
        $site_dir = ROOT_PATH;
        $file_name = "DzPhoto_thumb_{$time_id}_{$this->_user_info["user_id"]}.jpg";
        $file_patch = $site_dir . "public/uploads/dzgl/" . $file_name;
        $re_url = "/uploads/dzgl/" . $file_name;
        $data = ['file_name'=>'dzgl/'.$file_name];
        if (file_exists($file_patch)) {
//            $image = Image::open($file_patch);
            $data['re_url'] = request()->domain() . $re_url;
            $this->success('ok','',$data);
        }
        else {
            $this->error('');
        }
    }

    /**
     * 批量修改读者单位
     */
    public function batch_unitAction()
    {
        $dz_ids = input('dz_ids');
        $unit_name = input('unit_name');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }

        if (empty($dz_ids_arr)) {
            $this->error('修改单位失败:无效的读者ID');
        }

        $mod_dz = d("Dzgl");
        $save_data = array("unit_name" => $unit_name);
        $where = array();
        $where["dz_id"] = array("in", $dz_ids_arr);
        $where["tsg_code"] = $this->_user_info["tsg_code"];

        try {
            $mod_dz->startTrans();
            $is_success = $mod_dz->update($save_data, $where)->result;
            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('修改单位失败:更新数据库失败');
            }
            $is_success = $mod_dz->update_ext($this->_user_info["tsg_code"], $where, $save_data);
            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('修改单位失败:更新读者关联数据库失败');
            }

            $mod_dz->commit();
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_CUNIT, $this->_user_info,
                array("book_id" => 0, "dck_id" => 0, "db1" => $dz_ids, "op_desc" => "操作:读者修改单位,批量修改读者数量【" . count($dz_ids_arr) . "】,修改后单位名称:【" . $unit_name . "】"));
            $this->success('修改单位成功！');
        } catch (Exception $e) {
            $mod_dz->rollback();
            $this->error('修改单位失败:程序出现异常');
        }
    }

    /**
     * 批量修改状态
     */
    public function batch_statusAction()
    {
        $dz_ids = input('dz_ids');
        $dz_status = input('dz_status');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }

        if (empty($dz_ids_arr)) {
            $this->error('证件处理失败:无效的读者ID');
        }

        $mod_dz = d("Dzgl");
        $save_data = array("dz_status" => $dz_status);
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = array("in", $dz_ids_arr);
        $is_success = $mod_dz->update($save_data, $where)->result;

        if ($is_success !== false) {
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_CSTATUS, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者证件处理,批量修改读者数量【" . count($dz_ids_arr) . "】,修改后单位状态:【" . $dz_status . "】"));
            $this->success('证件处理成功！');
        } else {
            $this->error('证件处理失败:更新数据库失败');
        }
    }

    public function batch_pwdAction()
    {
        $pwd = input('pwd');
        $dz_ids = input('dz_ids');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }

        if (empty($dz_ids_arr)) {
            $this->error('修改密码失败:无效的读者ID');
        }

        $mod_dz = d("Dzgl");
        $save_data = array("pwd" => md5($pwd));
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = array("in", $dz_ids_arr);
        $is_success = $mod_dz->update($save_data, $where)->result;

        if ($is_success !== false) {
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_CPWD, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者修改密码,批量修改读者数量【" . count($dz_ids_arr) . "】"));
            $this->success('修改密码成功！');
        } else {
            $this->error('修改密码失败:更新数据库失败');
        }
    }

    public function batch_endtimeAction()
    {
        $endtime = input('endtime');
        $endtime = $endtime ? strtotime($endtime) : 0;
        $dz_ids = input('dz_ids');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }

        if (empty($dz_ids_arr)) {
            $this->error('修改有效期失败:无效的读者ID');
        }

        if (empty($endtime)) {
            $this->error('修改有效期失败:有效期不能为空');
        }

        $mod_dz = d("Dzgl");
        $save_data = array("end_time" => $endtime);
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = array("in", $dz_ids_arr);
        $is_success = $mod_dz->update($save_data, $where)->result;

        if ($is_success !== false) {
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_CENDDATE, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者修改有效期,批量修改读者数量【" . count($dz_ids_arr) . "】,修改后有效期至:【" . mdate("Y-m-d", $endtime) . "】"));
            $this->success('修改有效期成功！');
        } else {
            $this->error('修改有效期失败:更新数据库失败');
        }
    }

    public function batch_dztypeAction()
    {
        $dztype = input('dztype');
        $dz_ids = input('dz_ids');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }
        if (empty($dz_ids_arr)) {
            $this->error('修改读者类型失败:无效的读者ID');
        }
        if (empty($dztype)) {
            $this->error('修改读者类型失败:读者类型不能为空');
        }

        $mod_dz = d("Dzgl");
        $mod_dz_type = d("Dz_type");
        $save_data = array("dz_type_code" => $dztype);
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = array("in", $dz_ids_arr);
        $dz_type_info = $mod_dz_type->field("dz_type_name")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='$dztype'")->find();
        $need_update_ext = array("dz_type_code" => $dztype, "dz_type_name" => $dz_type_info["dz_type_name"] ? $dz_type_info["dz_type_name"] : "");

        try {
            $mod_dz->startTrans();
            $is_success = $mod_dz->update($save_data, $where)->result;

            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('修改读者类型失败:更新数据库失败');
            }

            $is_success = $mod_dz->update_ext($this->_user_info["tsg_code"], $where, $need_update_ext);
            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('修改读者类型失败:更新读者关联数据库失败');
            }

            $mod_dz->commit();
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_CDZTYPE, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:修改读者类型,批量修改读者数量【" . count($dz_ids_arr) . "】,修改后读者类型:【" . $dztype . "】"));
            $this->success('修改读者类型成功！');
        } catch (Exception $e) {
            $mod_dz->rollback();
            $this->error('修改读者类型失败:程序出现异常');
        }
    }

    /**
     * 新的读者证号
     */
    public function swap_cardAction()
    {
        $dz_code = input('dz_code');
        $dz_id = input('dz_ids');
        $mod_dz = d("Dzgl");

        if (empty($dz_id)) {
            $this->error('读者换证失败:无效的读者ID');
        }
        if (empty($dz_code)) {
            $this->error('读者换证失败:新的读者证号不能为空');
        }

        $dz_info = $mod_dz->field("dz_code")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_id=$dz_id")->find();

        if (empty($dz_info)) {
            $this->error('读者换证失败:未找到此读者信息');
        }

        if (!$mod_dz->unique($this->_user_info["tsg_code"], $dz_code, $dz_id)) {
            $this->error("读者换证失败:" . $mod_dz->getError());
        }

        $save_data = array("dz_code" => $dz_code);
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = $dz_id;

        try {
            $mod_dz->startTrans();
            $is_success = $mod_dz->update($save_data, $where)->result;

            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('读者换证失败:更新读者数据库失败');
            }

            $is_success = $mod_dz->update_ext($this->_user_info["tsg_code"], $where, $save_data);

            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('读者换证失败:更新读者关联数据库失败');
            }

            $mod_dz->commit();
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_SWAP, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者换证,旧读者证号:【{$dz_info["dz_code"]}】,新读者证号:【{$dz_code}】"));
            $this->success('读者换证成功！');
        } catch (Exception $e) {
            $mod_dz->rollback();
            $this->error('读者换证失败:程序出现异常');
        }
    }

    public function get_dz_ple_moneyAction()
    {
        $dz_id = input('dz_id/d');

        if (empty($dz_id)) {
            $this->error('无效的读者ID');
        }

        $mod_dz = d("Dzgl");
        $dz_info = $mod_dz->field("ple_money")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_id='$dz_id'")->find();

        if (empty($dz_info)) {
            $this->error('不存在此读者信息');
        }
        $this->result($dz_info["ple_money"], 1, '读取读者信息成功!');
    }

    /**
     * 退证
     */
    public function return_cardAction()
    {
        $dz_id = input('dz_id/d');
        $mod_dz = d("Dzgl");

        if (empty($dz_id)) {
            $this->error('退证失败:无效的读者ID');
        }

        $dz_info = $mod_dz->field("dz_code,ple_money,unit_name,real_name")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_id=$dz_id")->find();
        if (empty($dz_info)) {
            $this->error('退证失败:未找到此读者信息');
        }

        $save_data = array("dz_status" => "退证", "ple_money" => 0);
        $where = array();
        $where["tsg_code"] = $this->_user_info["tsg_code"];
        $where["dz_id"] = $dz_id;
        $finan_data = array();
        $finan_data["tsg_code"] = $this->_user_info["tsg_code"];
        $finan_data["add_time"] = time();
        $finan_data["op_user"] = $this->_user_info["user_name"];
        $finan_data["unit_name"] = $dz_info["unit_name"];
        $finan_data["real_name"] = $dz_info["real_name"];
        $finan_data["dz_code"] = $dz_info["dz_code"];
        $finan_data["dz_id"] = $dz_id;
        $finan_data["fee_type"] = Finance::FEE_TYPE_PAY;
        $finan_data["fin_type"] = Finance::FIN_TYPE_OUT;
        $finan_data["fin_mode"] = Finance::FIN_MODE_MONEY;
        $finan_data["fee_mode"] = Finance::FEE_MODE_DZCARD_RE;
        $finan_data["fee_money"] = $dz_info["ple_money"];
        $mod_finance = d("Finance");

        try {
            $mod_dz->startTrans();
            $is_success = $mod_dz->update($save_data, $where)->result;

            if ($is_success === false) {
                $mod_dz->rollback();
                $this->error('退证失败:更新读者数据库失败');
            }

            if (0 < $dz_info["ple_money"]) {
                $is_success = $mod_finance->create($finan_data)->result;
                if ($is_success === false) {
                    $mod_dz->rollback();
                    $this->error('退证失败:插入财务信息数据失败');
                }
            }

            $mod_dz->commit();
            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_RECARD, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => $dz_info["dz_code"], "op_desc" => "操作:读者退证,读者证号【{$dz_info["dz_code"]}】,读者姓名【{$dz_info["real_name"]}】"));
            $this->success('退证成功！');
        } catch (Exception $e) {
            $mod_dz->rollback();
            $this->error('退证失败:程序出现异常');
        }
    }

    /**
     * 批量删除
     */
    public function batch_dropAction()
    {
        return $this->dropAction();
    }

    /**
     * 删除
     */
    public function dropAction()
    {
        $dz_ids = input('dz_id');
        $dz_ids_arr = explode(",", $dz_ids);

        foreach ($dz_ids_arr as $key => $item) {
            $dz_ids_arr[$key] = intval($item);
        }

        if (empty($dz_ids_arr)) {
            $this->error('删除读者失败:无效的读者ID');
        }

        $mod_dz = d("Dzgl");
        $mod_lt_log = d("Dz_log");

        try {
            $mod_dz->startTrans();
            foreach ($dz_ids_arr as $dz_id) {
                $dz_info = $mod_dz->where("dz_id=$dz_id")->find();
                if ($dz_info && ($dz_info["tsg_code"] == $this->_user_info["tsg_code"])) {
                    $is_success = $mod_dz->drop_dz($dz_id);

                    if ($is_success !== false) {
                        $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_BATCH_DROP, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => $dz_info["dz_code"], "op_desc" => "操作:批量删除读者,读者证号【{$dz_info["dz_code"]}】,读者姓名【{$dz_info["real_name"]}】"));
                    }
                }
            }

            $mod_dz->commit();
            $this->success("删除读者成功！");
        } catch (Exception $e) {
            $mod_dz->rollback();
            $this->error('删除读者失败！错误提示:程序出现异常');
        }
    }

    public function _assign_common()
    {
        $mod_dz_type = d("Dz_type");
        $mod_dz = d("Dzgl");
        $dz_type_list = $mod_dz_type->get_list($this->_user_info["tsg_code"], array("field" => "dz_type_code,dz_type_name,valid_days,dz_ple_money,gongben_fee,serv_fee,ver_fee"));
        $dz_type_map = Common::array_keySwap($dz_type_list, "dz_type_code", "dz_type_name");
        $this->assign("dz_type_list", $dz_type_list);
        $this->assign("dz_type_map", $dz_type_map);
        $cred_type_list = $mod_dz->get_cred_type_list();
        $this->assign("cred_type_list", $cred_type_list);
        $dz_status_list = $mod_dz->get_dz_status_list();
        $this->assign("dz_status_list", $dz_status_list);
        $mod_dz_unit = d("Dz_unit");
        $unit_name_list = $mod_dz_unit->get_list($this->_user_info["tsg_code"]);
        $this->assign("unit_name_list", $unit_name_list);
    }

    public function get_dz_type_aboutAction()
    {
        $dz_type_code = input('dz_type_code');
        $mod_dz_type = d("Dz_type");
        $dz_type_info = $mod_dz_type->field("dz_type_name,dz_ple_money,gongben_fee,serv_fee,ver_fee")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='$dz_type_code'")->find();
        $msg = "当前读者类型【{$dz_type_info["dz_type_name"]}】的读者证押金:【{$dz_type_info["dz_ple_money"]}】,工本费:【{$dz_type_info["gongben_fee"]}】,服务费:【{$dz_type_info["serv_fee"]}】,验证费:【{$dz_type_info["ver_fee"]}】不为0时会自动增加财务信息";
        $this->success($msg);
    }

    public function uploadAction()
    {
        $mod_upload = d("Upload");
        if (!$this->isPost) {
            return view();
        } else {
            $mod_upload->clear_disuse_file();

            $file = request()->file('marc_file');
            $error = $_FILES['marc_file']['error'];
            if ($error) {
                $this->error('上传失败，' . $error);
            }
            $file_path = 'dzimport/' . $this->_user_info["user_id"] . '/';
            $dir = ROOT_PATH . 'public' . config('upload_path') . $file_path;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $info = $file->validate(['size' => 2147483648, 'ext' => 'xls,xlsx'])->move($dir);
            if (!$info) {
                $this->error('上传文件失败！错误信息:' . $info->getError());
            }

            $saveName = $info->getSaveName();
            $saveName = str_replace('\\', '/', $saveName);

            $add_data = array(
                "user_id" => $this->_user_info["user_id"],
                "file_encode" => "utf-8",
                "file_name" => $saveName,
                "file_path" => $file_path . $saveName,
                "marc_cnt" => 0,
                "up_type" => Upload::UP_TYPE_DZ_IMPORT,
                "add_time" => time());
            $upload_id = $mod_upload->create($add_data)->getLastInsId();
            if (empty($upload_id)) {
                @unlink($add_data["file_path"]);
                $this->error("上传文件失败！错误信息:" . $mod_upload->getError());
            }

            $this->redirect('Dzgl/import', ['upload_id' => $upload_id]);
        }
    }

    public function importAction()
    {
        $upload_id = input('upload_id/d');
        $sheet_name = input('sheet_name');
        $mod_upload = d("Upload");
        $file_info = $mod_upload->where("upload_id=$upload_id AND user_id={$this->_user_info["user_id"]} AND is_add=0 AND up_type=" . Upload::UP_TYPE_DZ_IMPORT)->find();

        if (empty($file_info)) {
            $this->error("在数据库未找到该文件信息！");
        }

        $file_info["file_path"] = ROOT_PATH . 'public' . config('upload_path') . $file_info["file_path"];
        if (!file_exists($file_info["file_path"])) {
            $mod_upload->where("upload_id=$upload_id")->delete();
            $this->error("在服务器上未找到该文件！");
        }

        $mod_finance = d("Finance");
        $fee_type_list = $mod_finance->get_fee_type_list_form();
        import('PHPExcel\IOFactory', EXTEND_PATH, '.class.php');
        $file_path = $file_info["file_path"];
        $objPHPExcel = \PHPExcel_IOFactory::load($file_path);
        $sheet_list = $objPHPExcel->getSheetNames();
        $data_list = array();

        if (!empty($sheet_name)) {
            if (!in_array($sheet_name, $sheet_list)) {
                $data_list = $objPHPExcel->getSheet(0)->toArray(NULL, true, true, true);
            } else {
                $data_list = $objPHPExcel->getSheetByName($sheet_name)->toArray(NULL, true, true, true);
            }
        } else {
            $data_list = $objPHPExcel->getSheet(0)->toArray(NULL, true, true, true);
        }

        $data_head = array_shift($data_list);
        if (!$this->isPost) {
            $this->_assign_common();
            $this->assign("sheet_list", $sheet_list);
            $this->assign("data_head", $data_head);
            $this->assign("data_sum", count($data_list));
            $data_list = array_slice($data_list, 0, 50);
            $this->assign("data_list", $data_list);
            $this->assign("fee_type_list", $fee_type_list);
            $_curlocal = array(
                array("text" => l("menu_dz")),
                array("text" => l("menu_lt_dzman")),
                array("text" => l("lt_dz_import"))
            );
            $this->assign("_curlocal", $_curlocal);
            return view('import_handle');
        } else {
            $fee_type = input('fee_type');
            if (!isset($fee_type_list[$fee_type])) {
                $this->error('请选择有效的财务信息收费状态!');
            }

            $mod_dz = d("Dzgl");
            $zd_map = array();
            foreach ($_POST["zd"] as $key => $item) {
                $tmp_arr = array();
                $tmp_arr["zd"] = $item;
                $tmp_arr["is_zd"] = $_POST["is_zd"][$key];
                $tmp_arr["zd_val"] = urldecode($_POST["zd_val"][$key]);
                $tmp_arr["zd_map"] = urldecode($_POST["zd_map"][$key]);
                $zd_map[] = $tmp_arr;
            }

            if (empty($zd_map)) {
                $this->error('请先选择Excel表字段与数据字段的对应关系!');
            }
            $data_ok_cnt = 0;
            $data_err_cnt = 0;
            $err_data_list = array();
            $data_msg = array();

            foreach ($data_list as $key => $data_item) {
                $add_data = array();
                foreach ($zd_map as $key1 => $map) {
                    $val_tmp = ($map["is_zd"] == 1 ? $data_item[$map["zd_map"]] : $map["zd_val"]);
                    $val_tmp = ($val_tmp ? $val_tmp : "");
                    $add_data[$map["zd"]] = $val_tmp;
                }
                try {
                    $mod_dz->startTrans();
                    $is_success = $mod_dz->add_dz($this->_user_info, $add_data, $fee_type);

                    if ($is_success !== false) {
                        $mod_dz->commit();
                        $data_ok_cnt++;
                        $data_msg[] = array("dz_code" => $add_data["dz_code"], "real_name" => $add_data["real_name"], "status" => "成功", "msg" => "读者导入成功");
                    } else {
                        $mod_dz->rollback();
                        $data_err_cnt++;
                        $err_data_list[] = $data_item;
                        $data_msg[] = array("dz_code" => $add_data["dz_code"], "real_name" => $add_data["real_name"], "status" => "失败", "msg" => $mod_dz->getError());
                    }
                } catch (Exception $e) {
                    $mod_dz->rollback();
                }
            }

            $re_arr = array("data_ok_cnt" => $data_ok_cnt, "data_err_cnt" => $data_err_cnt, "data_errmsg" => $data_msg);

            if (0 < $data_err_cnt) {
                $objPHPExcel->getProperties()->setCreator("weblib")->setLastModifiedBy("weblib")->setTitle("weblib")->setSubject("weblib")->setDescription("weblib")->setKeywords("weblib")->setCategory("weblib");
                $worksheet_obj = $objPHPExcel->setActiveSheetIndex(0);
                $data_write_list = array_merge($data_head, $err_data_list);
                $r = 4;

                foreach ($data_write_list as $row_item) {
                    $i = 0;
                    if($row_item && is_array($row_item)) {
                        foreach ($row_item as $key => $item) {
                            $worksheet_obj->setCellValueExplicitByColumnAndRow($key, $i, $item, \PHPExcel_Cell_DataType::TYPE_STRING);
                        }
                    }
                    $r++;
                }

                $objPHPExcel->getActiveSheet()->setTitle("读者导入错误数据");
                $objPHPExcel->setActiveSheetIndex(0);
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, "Excel5");
                $file_name = preg_replace("/.xls.$/", ".xls", basename($file_path));
                $err_file_path = dirname($file_path) . "/err_" . $file_name;
                $objWriter->save($err_file_path);
                $upload_data = array();
                $upload_data["err_file"] = $err_file_path;
                $upload_data["is_add"] = 1;
                $upload_data["disuse_time"] = time();
                $mod_upload->update($upload_data, ['upload_id' => $upload_id]);
            }

            $mod_lt_log = d("Dz_log");
            $mod_lt_log->addlog(DzLog::OP_TYPE_DZ_IMPORT, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => "", "op_desc" => "操作:读者导入,导入成功数:【{$data_ok_cnt}】,导入失败数:【{$data_err_cnt}】"));
            $this->result($re_arr, 1, '读者导入完成');
        }
    }

    public function downerrfileAction()
    {
        $upload_id = input('upload_id/d');
        $mod_upload = d("Upload");
        $file_info = $mod_upload->where("upload_id=$upload_id AND user_id={$this->_user_info["user_id"]} AND up_type=" . Upload::UP_TYPE_DZ_IMPORT)->find();
        if (empty($file_info) || !file_exists($file_info["err_file"])) {
            $this->error("下载失败！服务器不存在此文件 ");
            return false;
        }

        import("ORG\Net\Http", EXTEND_PATH, '.class.php');
        $err_file_name = basename($file_info["err_file"]);
        \Http::download($file_info["err_file"], $err_file_name, "", 1800);
    }

    public function downtplAction()
    {
        import("ORG\Net\Http", EXTEND_PATH, '.class.php');
        $file_name = "dz_example.xls";
        $file_path = ROOT_PATH . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . "file" . DIRECTORY_SEPARATOR . $file_name;
        \Http::download($file_path, urlencode("读者导入数据示例.xls"), "", 1800);
    }


    /**
     * @return \think\response\View|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 读者荐购审核列表
     */
    public function expect_verifyAction()
    {
        $status_list = ExpectLog::get_status_list();
        $verify_status = ExpectLog::get_status_list('verify');
        $op_user = User::field("user_name")->where(["belong_tsg_code"=>$this->adminInfo["tsg_code"]])->select();;

        if ($this->isAjax){
            $condition = ['tsg_code'=>$this->adminInfo['tsg_code']];
            $param = $this->getQueryParams();
            if ($param->search){
                foreach ($param->search as $search){
                    switch ($search['field']){
                        case 'status':
                            $condition['status'] = $search['value'];
                            break;
                        case 'op_user':
                            $condition['op_user'] = $search['value'];
                            break;
                        default:
                            $condition[$search['field']] = ['like',['%'.$search['value'].'%']];
                    }
                }
            }
            $list = ExpectLog::getPageList($condition, $param->limlt, $param->order);
            $count = ExpectLog::getCount($condition);

            if ($list){
                foreach ($list as &$item){
                    $item['ori_status'] = $item->status;
                    $item['status'] = $item->statusOp;
                }
                unset($item);
            }
            return $this->echoPageData($list, $count);
        }

        $this->assign('status', $status_list);
        $this->assign('verify_status', $verify_status);
        $this->assign('op_user', $op_user);
        return view();
    }

    /**
     * 荐购审核
     */
    public function setstatusAction()
    {
        try{
            $expect_log_id = input('expect_log_id/d',0);
            $state = input('state/d', 0);
            $state_list = ExpectLog::get_status_list('verify');
            if (!isset($state_list[$state])) {
                $this->error('审核状态异常');
            }
            $save_data  = [
                'status'=>$state,
                'op_user' => $this->adminInfo['user_name'],
                'verify_time' => time()
            ];
            $is_success = ExpectLog::update($save_data,['expect_log_id'=>$expect_log_id])->result;
            if ($is_success === false){
                $this->error('审核失败，数据异常');
            }
            $this->success('审核成功');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

}
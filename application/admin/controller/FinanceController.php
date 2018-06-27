<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Bookseller;
use app\admin\model\CfLog;
use app\admin\model\Cost;
use app\admin\model\Dzgl;
use app\admin\model\DzLog;
use app\admin\model\Finance;
use app\admin\model\LtLog;
use app\admin\model\Tsg;
use think\Exception;

/**
 * Class FinanceController
 * @package app\admin\controller
 * 财务管理
 */
class FinanceController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    /**
     * 收费退费
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
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Finance::getPageList($condition, $params->limit, $params->order);
        $count = Finance::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        $fin_type = input('fin_type/d');
        $dz_id = input('dz_id/d');
        $mod_finance = d("Finance");
        $fin_type_list = $mod_finance->get_fin_type_list();

        if (!$this->isPost) {
            if (!isset($fin_type_list[$fin_type])) {
                $this->alertMsg('请选择有效的财务类型');
            }

            $finance_info = array("fin_type" => $fin_type);
            if (!empty($dz_id)) {
                $mod_dz = d("dzgl");
                $dz_info = $mod_dz->field("dz_code")->where("dz_id=$dz_id")->find();
                $finance_info["dz_code"] = $dz_info["dz_code"];
            }

            $fee_mode_list = ($fin_type == Finance::FIN_TYPE_IN ? $mod_finance->get_fee_mode_list_in() : $mod_finance->get_fee_mode_list_out());
            $this->assign("fee_mode_list", $fee_mode_list);
            $this->assign("finance_info", $finance_info);
            $this->assign("fin_type_list", $fin_type_list);
            $this->_assign_fin();
            return view('edit');
        } else {
            if (!isset($fin_type_list[$fin_type])) {
                $this->error('请选择有效的财务类型');
            }
            $add_data = array(
                'dz_code' => input('dz_code'),
                'fee_money' => input('fee_money'),
                'fee_mode' => input('fee_mode'),
                'fin_mode' => input('fin_mode'),
                'fee_type' => input('fee_type'),
                'barcode' => input('barcode'),
                'book_id' => input('book_id'),
                'remark' => input('remark'),
            );
            $add_data["fin_type"] = $fin_type;
            try {
                $mod_finance->startTrans();
                $finance_id = $mod_finance->addFinance($this->_user_info, $add_data);
                if ($finance_id == false) {
                    $mod_finance->rollback();
                    $this->error('新增失败: ' . $mod_finance->getError());
                }

                $this->add_log($add_data);
                $mod_finance->commit();
                $this->success('新增财务信息成功！');
            } catch (Exception $e) {
                $mod_finance->rollback();
                $this->error('新增失败:程序出现异常');
            }
        }
    }

    /**
     * 读者财务清单
     */
    public function select_listAction()
    {
        $dz_id = input('dz_id/d');
        $mod_finance = d("Finance");

        if (!$dz_id) {
            $this->alertMsg('无效的读者ID');
        }

        $mod_dz = d("dzgl");
        $dz_info = $mod_dz->field("dz_code,real_name")->where("dz_id=$dz_id")->find();
        $this->assign("dz_info", $dz_info);

        if (!$dz_info) {
            $this->alertMsg('不存在该读者信息');
        }

        $where = array("tsg_code" => $this->_user_info["tsg_code"], "dz_id" => $dz_id, "fin_type" => Finance::FIN_TYPE_IN, "fee_type" => Finance::FEE_TYPE_PAY);
        $finance_list = $mod_finance->where($where)->select();
        $this->assign("finance_list", $finance_list);
        $fee_mode_list = $mod_finance->get_fee_mode_list_in();
        $this->assign("fee_mode_list", $fee_mode_list);
        $this->_assign_fin();
        return view();
    }

    public function print_listAction()
    {
        $print_num = input('print_num/d', 1);
        $print_width = input('print_width/d', 40);
        $mod_finance = d("Finance");
        $finance_ids_arr = input('finance_id/a');
        if (!$finance_ids_arr) {
            $this->alertMsg('无效的财务信息ID');
        }

        $where = array(
            "tsg_code" => $this->_user_info["tsg_code"],
            "finance_id" => array("in", $finance_ids_arr),
            "fin_type" => Finance::FIN_TYPE_IN,
            "fee_type" => Finance::FEE_TYPE_PAY
        );
        $finance_list = $mod_finance->where($where)->select();
        $this->assign("finance_list", $finance_list);
        $all_money = 0;

        foreach ($finance_list as $item) {
            $all_money += $item["fee_money"];
        }

        $finan_info = current($finance_list);
        $this->assign("finan_info", $finan_info);
        $fee_mode_list = $mod_finance->get_fee_mode_list_in();
        $this->assign("fee_mode_list", $fee_mode_list);
        $this->assign("all_money", $all_money);
        $this->assign("print_num", $print_num);
        $this->assign("print_width", $print_width);
        $this->assign("_user_info", $this->_user_info);
        $this->_assign_fin();
        return view();
    }

    public function editAction()
    {
        $finance_id = input('finance_id/d');
        $mod_finance = d("Finance");
        $finance_info = $mod_finance->where("finance_id", $finance_id)->find();
        $fee_mode_list = $mod_finance->get_fee_mode_list();
        $this->assign("fee_mode_list", $fee_mode_list);
        $fin_type_list = $mod_finance->get_fin_type_list();
        $this->assign("fin_type_list", $fin_type_list);

        if (!$this->isPost) {
            $this->assign("finance_info", $finance_info);

            if (!$finance_info) {
                $this->alertMsg(l("not_found_data"));
            }
            if ($finance_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }

            $mod_finance = d("Finance");
            $fin_mode_list = $mod_finance->get_fin_mode_list();
            $this->assign("fin_mode_list", $fin_mode_list);
            $fee_type_list = $mod_finance->get_fee_type_list();
            $this->assign("fee_type_list", $fee_type_list);
            return view();
        } else {
            if (!$finance_info) {
                $this->error(l("not_found_data"));
            }
            if ($finance_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(l("not_access_edit_data"));
            }
            $fee_type = input('fee_type/d');
            if ($finance_info["fee_type"] == Finance::FEE_TYPE_PAY) {
                $this->error('已现金支付的财务信息无法更改');
            } else {
                $save_data = array("fee_type" => $fee_type, 'remark' => input('remark'));
                $mod_dz_type = d("Dz_type");
                $mod_dz = d("dzgl");
                $dz_info = $mod_dz->field("dz_id,dz_code,real_name,unit_name,owe_money,dz_type_code,ple_money")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_id={$finance_info["dz_id"]}")->find();
                if (empty($dz_info)) {
                    $this->error('保存失败:该财务信息对应的读者不存在');
                }

                $dz_type_info = $mod_dz_type->field("dz_type_name,max_own_money")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_type_code='{$dz_info["dz_type_code"]}'")->find();
                if (empty($dz_type_info)) {
                    $this->error('保存失败:读者类型数据不存在!');
                }

                $dz_data = array();
                if ($finance_info["fin_type"] == Finance::FIN_TYPE_IN) {
                    if (($finance_info["fee_type"] == Finance::FEE_TYPE_NOPAY) && ($fee_type == Finance::FEE_TYPE_CANCEL)) {
                        $dz_data["owe_money"] = $dz_info["owe_money"] - $finance_info["fee_money"];
                    } else {
                        if (($finance_info["fee_type"] == Finance::FEE_TYPE_CANCEL) && ($fee_type == Finance::FEE_TYPE_NOPAY)) {
                            $dz_data["owe_money"] = $dz_info["owe_money"] + $finance_info["fee_money"];
                        } else if ($fee_type == Finance::FEE_TYPE_PAY) {
                            $dz_data["owe_money"] = $dz_info["owe_money"] - $finance_info["fee_money"];
                            if (($finance_info["fee_mode"] == Finance::FEE_MODE_DZCARD) || ($finance_info["fee_mode"] == Finance::FEE_MODE_DZCARD_ADD)) {
                                $dz_data["ple_money"] = $dz_info["ple_money"] + $finance_info["fee_money"];
                            }
                        }
                    }
                }

                if (isset($dz_data["owe_money"]) && (0 < $dz_type_info["max_own_money"]) && isset($dz_data["owe_money"]) && ($dz_type_info["max_own_money"] < $dz_data["owe_money"])) {
                    $mod_finance->rollback();
                    $this->error("保存失败:读者欠款最大限额为【{$dz_type_info["max_own_money"]}】,无法变更状态为【未支付】");
                }

                if ($finance_info["fin_type"] == Finance::FIN_TYPE_OUT) {
                    if (($finance_info["fee_mode"] == Finance::FEE_MODE_DZCARD_RE) || ($finance_info["fee_mode"] == Finance::FEE_MODE_DZCARD_SUB)) {
                        if ($fee_type == Finance::FEE_TYPE_PAY) {
                            $dz_data["ple_money"] = $dz_info["ple_money"] - $finance_info["fee_money"];
                        }
                    }
                }

                if (isset($dz_data["ple_money"]) && ($dz_info["ple_money"] < $finance_info["fee_money"])) {
                    $mod_finance->rollback();
                    $this->error("保存失败:读者当前押金为【{$dz_info["ple_money"]}】,无法变更状态为【已支付】");
                }

                try {
                    $mod_finance->startTrans();
                    if (!empty($dz_data)) {
                        $is_success = $mod_dz->update($dz_data, ['dz_id' => $finance_info["dz_id"]])->result;
                        if ($is_success === false) {
                            $mod_finance->rollback();
                            $this->error('保存失败:更新读者欠款信息失败');
                        }
                    }

                    $is_success = $mod_finance->update($save_data, ['finance_id' => $finance_id])->result;
                    if ($is_success === false) {
                        $mod_finance->rollback();
                        $this->error('保存失败:更新财务信息失败');
                    }
                    $mod_finance->commit();
                    $this->success('保存成功！');
                } catch (Exception $e) {
                    $mod_finance->rollback();
                    $this->error('保存失败:程序出现异常');
                }
            }
        }
    }

    public function _assign_fin()
    {
        $mod_finance = d("Finance");
        $fin_mode_list = $mod_finance->get_fin_mode_list();
        $this->assign("fin_mode_list", $fin_mode_list);
        $fee_type_list = $mod_finance->get_fee_type_list_form();
        $this->assign("fee_type_list", $fee_type_list);
    }

    private function add_log($add_data)
    {
        $mod_dz = d("Dzgl");
        $dz_info = $mod_dz->field("dz_id,tsg_code,dz_code,real_name")->where("dz_code='{$add_data["dz_code"]}'")->find();
        $mod_finance = d("Finance");
        $fee_mode_list = $mod_finance->get_fee_mode_list();
        $fee_type_list = $mod_finance->get_fee_type_list();
        $mod_lt_log = d("Dz_log");
        $log_type = ($add_data["fin_type"] == Finance::FIN_TYPE_IN ? LtLog::OP_TYPE_DZ_SHOUFEI : LtLog::OP_TYPE_DZ_TUIFEI);
        $op_str = ($add_data["fin_type"] == Finance::FIN_TYPE_IN ? "财务-收费" : "财务-退费");
        $op_str1 = $fee_mode_list[$add_data["fee_mode"]];
        $op_str2 = $fee_type_list[$add_data["fee_type"]];
        $mod_lt_log->addlog($log_type, $this->_user_info, array("book_id" => 0, "dck_id" => 0, "db1" => $add_data["dz_code"], "op_desc" => "操作:$op_str,收费方式:$op_str1,读者证号【{$add_data["dz_code"]}】,读者姓名【{$dz_info["real_name"]}】,费用金额【{$add_data["fee_money"]}】,收费状态【{$op_str2}】"));
    }


}
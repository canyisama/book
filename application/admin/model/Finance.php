<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-18
 * Time: 15:03
 */

namespace app\admin\model;


use think\Exception;
use think\Loader;

class Finance extends Base
{
    const FIN_TYPE_IN = 1;
    const FIN_TYPE_OUT = 2;
    const FIN_MODE_MONEY = 1;
    const FIN_MODE_IC = 2;
    const FEE_TYPE_PAY = 1;
    const FEE_TYPE_NOPAY = 2;
    const FEE_TYPE_CANCEL = 3;
    const FEE_MODE_DZCARD = 1;
    const FEE_MODE_DZCARD_ADD = 2;
    const FEE_MODE_GONGBEN = 9;
    const FEE_MODE_FUWUFEI = 10;
    const FEE_MODE_YANZHENG = 11;
    const FEE_MODE_LEND_OUT = 3;
    const FEE_MODE_LEND_LOSE = 4;
    const FEE_MODE_LEND_DIRTY = 5;
    const FEE_MODE_DZCARD_RE = 6;
    const FEE_MODE_DZCARD_SUB = 7;
    const FEE_MODE_RE = 8;

    protected $dateFormat = 'Y-m-d';
    protected $type = [
        'add_time' => 'timestamp',
    ];

    public function get_fin_type_list()
    {
        return array(self::FIN_TYPE_IN => "收费", self::FIN_TYPE_OUT => "退费");
    }

    public function get_fin_mode_list()
    {
        return array(self::FIN_MODE_MONEY => "现金", self::FIN_MODE_IC => "IC卡");
    }

    public function get_fee_type_list()
    {
        return array(self::FEE_TYPE_PAY => "已支付", self::FEE_TYPE_NOPAY => "未支付", self::FEE_TYPE_CANCEL => "已取消");
    }

    public function get_fee_type_list_form()
    {
        return array(self::FEE_TYPE_PAY => "已支付", self::FEE_TYPE_NOPAY => "未支付");
    }

    public function get_fee_mode_list()
    {
        return array(self::FEE_MODE_DZCARD => "读者证押金", self::FEE_MODE_DZCARD_ADD => "补交押金", self::FEE_MODE_GONGBEN => "工本费", self::FEE_MODE_FUWUFEI => "服务费", self::FEE_MODE_YANZHENG => "验证费", self::FEE_MODE_LEND_OUT => "超期罚款", self::FEE_MODE_LEND_LOSE => "丢失罚款", self::FEE_MODE_LEND_DIRTY => "污损罚款", self::FEE_MODE_DZCARD_RE => "退还押金", self::FEE_MODE_DZCARD_SUB => "减少押金", self::FEE_MODE_RE => "退款");
    }

    public function get_fee_mode_list_in()
    {
        return array(self::FEE_MODE_DZCARD => "读者证押金", self::FEE_MODE_DZCARD_ADD => "补交押金", self::FEE_MODE_GONGBEN => "工本费", self::FEE_MODE_FUWUFEI => "服务费", self::FEE_MODE_YANZHENG => "验证费", self::FEE_MODE_LEND_OUT => "超期罚款", self::FEE_MODE_LEND_LOSE => "丢失罚款", self::FEE_MODE_LEND_DIRTY => "污损罚款");
    }

    public function get_fee_mode_list_out()
    {
        return array(self::FEE_MODE_DZCARD_RE => "退还押金", self::FEE_MODE_DZCARD_SUB => "减少押金", self::FEE_MODE_RE => "退款");
    }

    /**
     * @param $mode @财务字段
     * @param int $all @是否获取全部数组
     * @return mixed|string
     * 财务类型获取
     */
    public static function getType($mode, $all = 0)
    {
        $type_lists = config('finance_type.' . $mode);
        if ($all === 0) {
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

    protected function getFinTypeAttr($value)
    {
        $type = [
            1 => '收费',
            2 => '退费'
        ];
        return isset($type[$value]) ? $type[$value] : '';
    }

    protected function getFinModeAttr($value)
    {
        $type = [
            1 => '现金',
            2 => 'IC卡'
        ];
        return isset($type[$value]) ? $type[$value] : '';
    }

    protected function getFeeTypeAttr($value)
    {
        $type = [
            1 => '已支付',
            2 => '未支付',
            3 => '已取消',
        ];
        return isset($type[$value]) ? $type[$value] : '';
    }

    protected function getFeeModeAttr($value)
    {
        $type = [
            6 => '退还押金',
            7 => '减少押金',
            8 => '退款',
            1 => '读者证押金',
            2 => '补交押金',
            3 => '超期罚款',
            4 => '丢失罚款',
            5 => '污损罚款',
            9 => '工本费',
            10 => '服务费',
            11 => '验证费'
        ];
        return isset($type[$value]) ? $type[$value] : '';
    }

    /**
     * @param $user_info @管理员信息
     * @param $add_data @添加的数据
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 添加财务信息
     */
    public function addFinance($user_info, $add_data)
    {
        if (!$add_data["fin_type"]) {
            $this->error = "财务类型为空";
            return false;
        }

        if (!$add_data["fin_mode"]) {
            $this->error = "财务支付方式为空";
            return false;
        }

        if (!$add_data["fee_type"]) {
            $this->error = "财务支付状态为空";
            return false;
        }


        if (!$add_data["fee_mode"]) {
            $this->error = "财务支付类型为空";
            return false;
        }

        if (!$add_data["dz_code"]) {
            $this->error = "读者证号为空";
            return false;
        }

        if (!$add_data['fee_money']) {
            $this->error = '财务金额不大于零';
            return false;
        }

        $field = "dz_id,tsg_code,dz_code,real_name,unit_name,owe_money,dz_type_code,ple_money";
        $where['dz_code'] = $add_data['dz_code'];
        $dz_info = Dzgl::field($field)->where($where)->find();

        if (!$dz_info) {
            $this->error = "读者不存在";
            return false;
        }

        $dz_type_model = Loader::model("DzType");
        $field = 'dz_type_code,max_own_money,is_inter';
        $dz_type_info = $dz_type_model->isDzType($dz_info->getData('tsg_code'), $dz_info->dz_type_code, $user_info['tsg_code'], $field);
        if ($dz_type_info === false) {
            $this->error = $dz_type_model->getError();
            return false;
        }
        $add_data["tsg_code"] = $user_info["manage_tsg_code"];
        $add_data["add_time"] = time();
        $add_data["op_user"] = $user_info["user_name"];
        $add_data["unit_name"] = $dz_info["unit_name"];
        $add_data["real_name"] = $dz_info["real_name"];
        $add_data["dz_id"] = $dz_info["dz_id"];

        try {
            $dz_data = array();

            //财务类型为收费
            if ($add_data["fin_type"] == 1) {
                //已支付
                if ($add_data["fee_type"] == 1) {
                    //收费类型为读者证押金 与 补交读者证押金
                    if (($add_data["fee_mode"] == 1) || ($add_data["fee_mode"] == 2)) {
                        $dz_data["ple_money"] = $dz_info["ple_money"] + $add_data["fee_money"];
                    }
                } //未支付
                else if ($add_data["fee_type"] == 2) {
                    $dz_data["owe_money"] = $dz_info["owe_money"] + $add_data["fee_money"];
                }
                //判断读者是否可以欠款
                if (isset($dz_data["owe_money"]) && (0 < $dz_type_info["max_own_money"]) && ($dz_type_info["max_own_money"] < $dz_data["owe_money"])) {
                    $this->error = "读者欠款最大限额为【" . $dz_type_info["max_own_money"] . "】,请现金支付";
                    return false;
                }
            }
            //财务类型为付费 并 判断是否已支付
            if (($add_data["fin_type"] == 2) && ($add_data["fee_type"] == 1)) {
                //付费类型为退还读者证押金 与 减少读者证押金
                if (($add_data["fee_mode"] == 6) || ($add_data["fee_mode"] == 7)) {
                    $dz_data["ple_money"] = $dz_info["ple_money"] - $add_data["fee_money"];
                    //读者的押金金额是否足够
                    if (isset($dz_data["ple_money"]) && ($dz_info["ple_money"] < $add_data["fee_money"])) {
                        $this->error = "读者当前押金为【" . $dz_info["ple_money"] . "】,无法超过此数额";
                        return false;
                    }
                }
            }

            if (!empty($dz_data)) {
                $is_success = Dzgl::update($dz_data, ['dz_id' => $dz_info['dz_id']])->result;
                if ($is_success === false) {
                    $this->error = "更新读者欠款信息失败";
                    return false;
                }
            }

            $add_data["fee_money"] = ($add_data["fin_type"] == 1 ? $add_data["fee_money"] : "-" . $add_data["fee_money"]);
            $is_success = self::create($add_data, true)->result;
            if ($is_success === false) {
                $this->error = "插入财务信息数据失败";
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;

        }
    }
}
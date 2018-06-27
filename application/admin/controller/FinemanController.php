<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/19
 * Time: 17:27
 */

namespace app\admin\controller;


use app\admin\model\Dzgl;
use app\admin\model\DzLog;
use app\admin\model\Finance;
use app\admin\model\Lend;
use app\admin\model\Ltrule;
use think\Db;
use think\Exception;

/**
 * Class FinemanController
 * @package app\admin\controller
 * 财务罚款管理
 */
class FinemanController extends BaseController
{
    public function indexAction()
    {
        return view();
    }

    /**
     * 异步获取
     */
    public function getJsonListAction()
    {
        $type = input('type/d');
        $dz_code = input('dz_code/d') ?: 0;
        $barcode = input('barcode/s') ?: '';
        $condition = [
            'tsg_code' => $this->adminInfo['tsg_code'],
            'fee_mode' => $type,
            'fee_type' => 2
        ];
        $dz_code !== 0 ? $condition['dz_code'] = $dz_code : $condition['barcode'] = $barcode;
        $params = $this->getQueryParams();//分页,排序,查询参数

        $list = Finance::getPageList($condition, $params->limit, $params->order);
        $count = Finance::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     *
     *罚款跳转
     */
    public function fineTypeAction()
    {
        $type = input('get.type/d');
        $lend_id = input('get.lend_id/d');
        switch ($type){
            case 3:
                return $this->redirect('lendOut',['lend_id'=>$lend_id]);
            case 4:
                return $this->redirect('lendLose',['lend_id'=>$lend_id]);
            default:
                return $this->redirect('lendDirty',['lend_id'=>$lend_id]);
        }
    }

    /**
     * @return string
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 超期罚款
     */
    public function lendOutAction()
    {
        //REG
        $lend_id = input('lend_id/d');
//        $fin_type = 1;
        $fin_type = Finance::FIN_TYPE_IN;
//        $fee_mode = 3;
        $fee_mode = Finance::FEE_MODE_LEND_OUT;

        if (!$this->request->isPost()) {

            $field = 'lend_id,is_inter_lend,dz_type_code,ltype_code,tsg_site_code,must_time,dz_code,real_name,add_time,unit_name,title,barcode';
            $lend_info = Lend::field($field)->where(['lend_id'=>$lend_id])->find();

            if (!$lend_info) {
                $this->alertMsg(lang('未找到借阅信息,无法进行超期罚款'));
            }

            if ($lend_info["is_inter_lend"] == 0) {
                $rule_info = Ltrule::getRule($this->adminInfo["tsg_code"], $lend_info["dz_type_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }
            else {
                $rule_info = Ltrule::getRuleInter($this->adminInfo["tsg_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }

            //超期罚款
            $time_now = time();
            $lend_info["out_days"] = ($lend_info["must_time"] < $time_now ? floor(($time_now - $lend_info["must_time"]) / 86400) : 0);
            $lend_info["out_money"] = $rule_info["out_fine"];
            $lend_info["out_max_fine"] = $rule_info["out_max_fine"];
            $fee_money = round($lend_info["out_days"] * $lend_info["out_money"], 2);
            $fee_money = ($lend_info["out_max_fine"] < $fee_money ? $lend_info["out_max_fine"] : $fee_money);
            $lend_info["fee_money"] = $fee_money;
            $lend_info['finally_fee_money'] = $fee_money;
            $lend_info["remark"] = "超期天数[".$lend_info["out_days"]."天】,超期每日罚金【".$lend_info["out_money"]."元】,超期应罚金额【".$lend_info["fee_money"]."元】";
//            $fin_type = $finance_model->get_fin_type_list();

            $lend_info['fin_type'] = Finance::getType('fin_type',$fin_type);
            $lend_info['fee_mode'] = Finance::getType('fee_mode',$fee_mode);

            $this->assignFin();
            $this -> assign('type','超期');
            $this -> assign('finance_type',$fee_mode);
            $this->assign("info", $lend_info);
            return $this->view->fetch('fine_lend');
        }
        try{
            $field = 'dz_id,dz_code,real_name,unit_name,dck_id,book_id,barcode,title,lend_id';
            $lend_info = Lend::field($field)->where(['lend_id'=>$lend_id])->find();


            if (!$lend_info) {
                $this->error('未找到借阅信息,无法进行超期罚款');
            }

            $data = $this->request->post();

            if (isset($data) && empty($data)) {
                $this->error('超期罚款失败：未能获取罚款数据');
            }
            $old_data = [
                "tsg_code" => $this->adminInfo["tsg_code"],
                "fin_type" => $fin_type,
                "fee_mode" => $fee_mode,
                "dz_id" => $lend_info["dz_id"],
                "dz_code" => $lend_info["dz_code"],
                "real_name" => $lend_info["real_name"],
                "unit_name" => $lend_info["unit_name"],
                "dck_id" => $lend_info["dck_id"],
                "book_id" => $lend_info["book_id"],
                "barcode" => $lend_info["barcode"],
                "op_user" => $this->adminInfo["user_name"],
                "title" => $lend_info["title"],
                "add_time" => time()
            ];
            $save_date = array_merge($data,$old_data);

            Db::startTrans();
            $finance_model = new Finance();
            $res = $finance_model->addFinance($this->adminInfo, $save_date);

            if ($res === false) {
                Db::rollback();
                $this->error('超期罚款失败:'.$finance_model->getError());
            }

            $dz_model = new Dzgl();
            $is_success = $dz_model->addViolateCnt($this->adminInfo["tsg_code"], $lend_info["dz_id"]);

            if ($is_success === false) {
                Db::rollback();
                $this->error('超期罚款失败:更新读者违章次数失败,'. $dz_model->getError());
            }

//                $vr_stop_arr = ($is_success === 1 ? ["is_vr_stop" => "1"] : "");
            $vr_stop_arr = ($is_success === 1 ? '读者违章次数上限，已暂停使用' : '');
            $is_success = $lend_info->rebook($this->adminInfo["tsg_code"], $lend_info['lend_id'],'在架',1);
            $is_reserve = '';

            if ($is_success === false) {

                Db::rollback();
                $this->error('超期罚款失败:'. $lend_info->getError());
            }elseif ($is_success === 1){
                $is_reserve = $lend_info->getError();
            }

            $is_success = $this->addLog($save_date);

            if ($is_success === false){
                Db::rollback();
                $this->error('写入财务日志失败，数据库异常');
            }

            Db::commit();
            $this->success('超期罚款成功!'.$is_reserve.$vr_stop_arr);
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 丢失罚款
     */
    public function lendLoseAction()
    {
        $lend_id = input('lend_id/d');
//        $fin_type = 1;
        $fin_type = Finance::FIN_TYPE_IN;
//        $fee_mode = 4;
        $fee_mode = Finance::FEE_MODE_LEND_LOSE;

        if (!$this->request->isPost()) {
            $field = 'lend_id,is_inter_lend,dz_type_code,ltype_code,tsg_site_code,must_time,dz_code,real_name,add_time,unit_name,price,price_sum,title,barcode';
            $lend_info = Lend::field($field)->where(['lend_id'=>$lend_id])->find();

            if (!$lend_info) {
                $this->alertMsg(lang('未找到借阅信息,无法进行丢失罚款'));
            }
            if ($lend_info["is_inter_lend"] == 0) {
                $rule_info = Ltrule::getRule($this->adminInfo["tsg_code"], $lend_info["dz_type_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }
            else {
                $rule_info = Ltrule::getRuleInter($this->adminInfo["tsg_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }

            //超期罚款
            $time_now = time();
            $lend_info["out_days"] = ($lend_info["must_time"] < $time_now ? floor(($time_now - $lend_info["must_time"]) / 86400) : 0);
            $lend_info["out_money"] = $rule_info["out_fine"];
            $lend_info["out_max_fine"] = $rule_info["out_max_fine"];
            $fee_money = round($lend_info["out_days"] * $lend_info["out_money"], 2);
            $fee_money = ($lend_info["out_max_fine"] < $fee_money ? $lend_info["out_max_fine"] : $fee_money);
            $lend_info["fee_money"] = $fee_money;

            //丢失罚款
            $lose_fee_money = ($rule_info["lose_type"] == 1 ? $lend_info["price"] : $lend_info["price_sum"]);
            $lose_fee_money = round($lose_fee_money * $rule_info["lose_rate"], 2);
            $lose_fee_money_show = $lose_fee_money;

            //最后罚款
            $lend_info["lose_fee_money_show"] = $lose_fee_money_show;
            $lose_fee_money = ($rule_info["lose_mode"] == 2 ? $lose_fee_money + $fee_money : $lose_fee_money);
            $lend_info["finally_fee_money"] = $lose_fee_money;

            $lose_type_list = Ltrule::getType('lose_type');
            $lose_mode_list = Ltrule::getType('lose_mode');
            $lend_info["lose_mode_txt"] = $lose_mode_list[$rule_info["lose_mode"]];
            $lend_info["lose_type_txt"] = $lose_type_list[$rule_info["lose_type"]];
            $lend_info['lose_rate'] = $rule_info['lose_rate'];

            $lend_info["remark"]  = '丢失罚款方式['.$lend_info['lose_mode_txt'];
            $lend_info['remark'] .= '],丢失罚款类型['.$lend_info["lose_type_txt"];
            $lend_info['remark'] .= '],丢失罚款倍率['.$lend_info["lose_rate"];
            $lend_info['remark'] .= '],丢失/超期罚金['.$lose_fee_money_show.'元/'.$fee_money;
            $lend_info['remark'] .= '元],丢失应罚金额['.$lose_fee_money.'元]';

            $lend_info['fin_type'] = Finance::getType('fin_type',$fin_type);
            $lend_info['fee_mode'] = Finance::getType('fee_mode',$fee_mode);

            $this->assignFin();
            $this -> assign('type','丢失');
            $this -> assign('finance_type',$fee_mode);
            $this->assign("info", $lend_info);
            return $this->view->fetch('fine_lend');

        }
        try{
            $field = 'dz_id,dz_code,real_name,unit_name,dck_id,book_id,barcode,title,lend_id';
            $lend_info = Lend::field($field)->where(['lend_id'=>$lend_id])->find();

            if (!$lend_info) {
                $this->error('未找到借阅信息,无法进行丢失罚款');
            }

            $data = $this->request->post();

            if (isset($data) && empty($data)) {
                $this->error('丢失罚款失败：未能获取罚款数据');
            }
            $old_data = [
                "tsg_code" => $this->adminInfo["tsg_code"],
                "fin_type" => $fin_type,
                "fee_mode" => $fee_mode,
                "dz_id" => $lend_info["dz_id"],
                "dz_code" => $lend_info["dz_code"],
                "real_name" => $lend_info["real_name"],
                "unit_name" => $lend_info["unit_name"],
                "dck_id" => $lend_info["dck_id"],
                "book_id" => $lend_info["book_id"],
                "barcode" => $lend_info["barcode"],
                "op_user" => $this->adminInfo["user_name"],
                "title" => $lend_info["title"],
                "add_time" => time()
            ];
            $save_date = array_merge($data,$old_data);
            Db::startTrans();
            $finance_model = new Finance();
            $res = $finance_model->addFinance($this->adminInfo, $save_date);

            if ($res === false) {
                Db::rollback();
                $this->error('丢失罚款失败:'.$finance_model->getError());
            }

            $dz_model = new Dzgl();
            $is_success = $dz_model->addViolateCnt($this->adminInfo["tsg_code"], $lend_info["dz_id"]);

            if ($is_success === false) {
                Db::rollback();
                $this->error('丢失罚款失败:更新读者违章次数失败,'.$dz_model->getError());
            }

            $vr_stop_arr = ($is_success === 1 ? '读者违章次数上限，已暂停使用' : '');
            $is_success = $lend_info->rebook($this->adminInfo["tsg_code"], $lend_info["lend_id"],'遗失');
//                $is_reserve = '';

            if ($is_success === false) {

                Db::rollback();
                $this->error('丢失罚款失败:'.$lend_info->getError());
            }
//                  elseif ($is_success === 1){
//                    $is_reserve = $lend_model->getError();
//                }

            $is_success = $this->addLog($save_date);

            if ($is_success === false){
                Db::rollback();
                $this->error('写入财务日志失败，数据库异常');
            }

            Db::commit();
            $this->success('丢失罚款成功!'.$vr_stop_arr);
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 污损罚款
     */
    public function lendDirtyAction()
    {
        $lend_id = input('lend_id/d');
//        $fin_type = 1;  //1为收费
        $fin_type = Finance::FIN_TYPE_IN;
//        $fee_mode = 5; //5为污损罚款
        $fee_mode = Finance::FEE_MODE_LEND_DIRTY; //5为污损罚款

        if (!$this->request->isPost()) {
//
            $field = 'lend_id,is_inter_lend,dz_type_code,ltype_code,tsg_site_code,must_time,dz_code,real_name,add_time,unit_name,price,price_sum,title,barcode';
            $lend_info = Lend::field($field)->where(['lend_id'=>$lend_id])->find();
            if (!$lend_info) {
                $this->alertMsg(lang('未找到借阅信息,无法进行污损罚款'));
            }
            if ($lend_info["is_inter_lend"] == 0) {
                $rule_info = Ltrule::getRule($this->adminInfo["tsg_code"], $lend_info["dz_type_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }
            else {
                $rule_info = Ltrule::getRuleInter($this->adminInfo["tsg_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
            }

            //超期罚款
            $time_now = time();
            $lend_info["out_days"] = ($lend_info["must_time"] < $time_now ? floor(($time_now - $lend_info["must_time"]) / 86400) : 0);
            $lend_info["out_money"] = $rule_info["out_fine"];
            $lend_info["out_max_fine"] = $rule_info["out_max_fine"];
            $fee_money = round($lend_info["out_days"] * $lend_info["out_money"], 2);
            $fee_money = ($lend_info["out_max_fine"] < $fee_money ? $lend_info["out_max_fine"] : $fee_money);
            $lend_info["fee_money"] = $fee_money;

            //污损罚款
            $dirty_fee_money = ($rule_info["dirty_type"] == 1 ? $lend_info["price"] : $lend_info["price_sum"]);
            $dirty_fee_money = round($dirty_fee_money * $rule_info["dirty_rate"], 2);
            $dirty_fee_money_show = $dirty_fee_money;

            //最后罚款
            $lend_info["dirty_fee_money_show"] = $dirty_fee_money_show;
            $dirty_fee_money = ($rule_info["dirty_mode"] == 2 ? $dirty_fee_money + $fee_money : $dirty_fee_money);
            $lend_info["finally_fee_money"] = $dirty_fee_money;

            $dirty_type_list = Ltrule::getType('dirty_type');
            $dirty_mode_list = Ltrule::getType('dirty_mode');
            $lend_info["dirty_mode_txt"] = $dirty_mode_list[$rule_info["dirty_mode"]];
//            return dump($lend_info["dirty_mode_txt"]);
            $lend_info["dirty_type_txt"] = $dirty_type_list[$rule_info["dirty_type"]];
            $lend_info["dirty_rate"] = $rule_info["dirty_rate"];

            $lend_info["remark"]  = '污损罚款方式['.$lend_info['dirty_mode_txt'];
//            $lend_info['remark'] = '';
            $lend_info['remark'] .= '],污损罚款类型['.$lend_info["dirty_type_txt"];
            $lend_info['remark'] .= '],污损罚款倍率['.$lend_info["dirty_rate"];
            $lend_info['remark'] .= '],污损/超期罚金['.$dirty_fee_money_show.'元/'.$fee_money;
            $lend_info['remark'] .= '元],污损应罚金额['.$dirty_fee_money.'元]';

            $lend_info['fin_type'] = Finance::getType('fin_type',$fin_type);
            $lend_info['fee_mode'] = Finance::getType('fee_mode',$fee_mode);
            $this->assignFin();
            $this -> assign('type','污损');
            $this -> assign('finance_type',$fee_mode);
            $this->assign("info", $lend_info);
            return $this->view->fetch('fine_lend');
        }
        try{
            $field = 'dz_id,dz_code,real_name,unit_name,dck_id,book_id,barcode,title,lend_id';

            $lend_info = Lend::field($field)->where(['lend_id' => $lend_id])->find();
            if (!$lend_info) {
                $this->error('未找到借阅信息,无法进行污损罚款');
            }
            $data = $this->request->post();

            if (isset($data) && empty($data)) {
                $this->error('污损罚款失败：未能获取罚款数据');
            }
            $old_data = [
                "tsg_code" => $this->adminInfo["tsg_code"],
                "fin_type" => $fin_type,
                "fee_mode" => $fee_mode,
                "dz_id" => $lend_info["dz_id"],
                "dz_code" => $lend_info["dz_code"],
                "real_name" => $lend_info["real_name"],
                "unit_name" => $lend_info["unit_name"],
                "dck_id" => $lend_info["dck_id"],
                "book_id" => $lend_info["book_id"],
                "barcode" => $lend_info["barcode"],
                "op_user" => $this->adminInfo["user_name"],
                "title" => $lend_info["title"],
                "add_time" => time()
            ];
            $save_date = array_merge($data, $old_data);

            Db::startTrans();
            $finance_model = new Finance();
            $res = $finance_model->addFinance($this->adminInfo, $save_date);
            if ($res === false) {
                Db::rollback();
                $this->error('污损罚款失败:' . $finance_model->getError());
            }
            $dz_model = new Dzgl();
            $is_success = $dz_model->addViolateCnt($this->adminInfo["tsg_code"], $lend_info["dz_id"]);

            if ($is_success === false) {
                Db::rollback();
                $this->error('污损罚款失败:更新读者违章次数失败,' . $dz_model->getError());
            }

//                $vr_stop_arr = ($is_success === 1 ? ["is_vr_stop" => "1"] : "");
            $vr_stop_arr = ($is_success === 1 ? '读者违章次数上限，已暂停使用' : '');

//            $lend_model = new Lend();
            $is_success = $lend_info->rebook($this->adminInfo["tsg_code"], $lend_info["lend_id"]);
            $is_reserve = '';

            if ($is_success === false) {

                Db::rollback();
//                $this->error(11111111);
                $this->error('污损罚款失败:' . $lend_info->getError());

            } elseif ($is_success === 1) {
                $is_reserve = $lend_info->getError();
            }

            $is_success = $this->addLog($save_date);

            if ($is_success === false) {
                Db::rollback();
                $this->error('写入财务日志失败，数据库异常');
            }
            Db::commit();
            $this->success('污损罚款成功!' . $is_reserve . $vr_stop_arr);
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @param $finance_id       @财务id
     * @param $fin_mode         @财务方式
     * 罚款未支付支付
     */
    public function finePayAction($finance_id,$fin_mode)
    {
        try{
            if (empty($finance_id)){
                $this->error('财务id异常');
            }
            if (empty($fin_mode)){
                $this->error('财务支付方式异常');
            }

            $finance_info = Finance::get($finance_id);
            if (!$finance_info){
                $this->error('财务信息异常');
            }
            $finance_data = [
                'fin_mode' => $fin_mode,
                'fee_type' => 1
            ];
            Db::startTrans();
            $res = Finance::update($finance_data,['finance_id'=>$finance_id])->result;
            if ($res === false){
                Db::rollback();
                $this->error('更新财务信息失败，请稍后再试');
            }

            $dz_info = Dzgl::get($finance_info['dz_id']);

            if (!$dz_info){
                Db::rollback();
                $this->error('此财务无读者信息，请稍后再试');
            }
            $new_money = $dz_info['owe_money'] - $finance_info['fee_money'];
            $dz_data = [
                'owe_money' => $new_money
            ];
            $dz_info -> owe_money = $new_money;
            $res = Dzgl::update($dz_data,['dz_id'=>$finance_info['dz_id']])->result;
            if ($res === false){
                Db::rollback();
                $this->error('更新读者信息失败，请稍后再试');
            }

            Db::commit();
            $this->success('财务信息更新成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    /**
     * 财务公共显示数据模板
     * ---财务收款方式
     * ---财务收款|付款状态
     */
    private function assignFin()
    {
        $fin_mode_list = Finance::getType('fin_mode');
        $fee_type_list = Finance::getType('fee_type');
        $this->assign("fin_mode_list", $fin_mode_list);
        $this->assign("fee_type_list", $fee_type_list);
    }

    /**
     * @param $add_data
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 财务信息写入日志
     */
    private function addLog($add_data)
    {
        $dz_info = Dzgl::field("dz_id,tsg_code,dz_code,real_name")->where(["dz_code"=>$add_data["dz_code"]])->find();
        $log_type = ($add_data["fin_type"] == 1 ? 20 : 21);
        $op_str1 = Finance::getType('fee_mode',$add_data["fee_mode"]);
        $op_str2 = Finance::getType('fee_type',$add_data['fee_type']);
        $opdesc  = '[#],支付类型:['.$op_str1;
        $opdesc .= '],读者证号:['.$add_data["dz_code"];
        $opdesc .= '],读者姓名:['.$dz_info["real_name"];
        $opdesc .= '],费用金额:['.$add_data["fee_money"];
        $opdesc .= '],收费状态:['.$op_str2.']';
        $param = [
            "db_1" => $add_data["dz_code"],
            'op_desc' => $opdesc
        ];
        $res = DzLog::addLog($log_type,$this->adminInfo,$param);
        return $res;
    }

}
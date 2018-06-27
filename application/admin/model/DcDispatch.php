<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/14
 * Time: 17:18
 */

namespace app\admin\model;

use think\Db;

/**
 * Class DcDispatch
 * @package app\admin\model
 * 调拨模型类
 */
class DcDispatch extends Base
{
    public function disOne($dck_id, $tsg_code_dest, $tsg_site_code_dest, $lt_type_dest, &$user_info, &$tsgMap, &$tsg_site_map)
    {
        if (empty($dck_id)) {
            $this->error = "馆藏信息不存在";
            return false;
        }

        try {
            !$tsgMap && ($tsgMap = Tsg::getMap());
            !$tsg_site_map && ($tsg_site_map = TsgSite::getMap('tsg_site_code', 'site_name', $user_info['tsg_code']));
            $dck_info = Dck::join([['lib_book','lib_dck.book_id=lib_book.book_id']])->where(['dck_id'=>$dck_id])->find();
            if (!isset($tsgMap[$tsg_code_dest])) {
                $this->error = "目标分馆不存在";
                return false;
            }

            $dis_data = $dck_info;
            $dis_data = $dis_data->toArray();
            $dis_data["dispatch_time"] = mstrtotime(date("Y-m-d"));
            $dis_data["dispatch_user"] = $user_info["user_name"];
            $dis_data["tsg_code_dest"] = $tsg_code_dest;
            $dis_data["tsg_name_dest"] = $tsgMap[$tsg_code_dest];
            $site_name = $tsg_site_map[$dis_data["tsg_site_code"]];
            $dis_data["site_name"] = ($site_name ? $site_name : "");
            !empty($tsg_site_code_dest) && ($dis_data["tsg_site_code_dest"] = $tsg_site_code_dest);
            !empty($tsg_site_code_dest) && ($dis_data["site_name_dest"] = $tsg_site_map[$tsg_site_code_dest]);
            !empty($lt_type_dest) && ($dis_data["lt_type_dest"] = $lt_type_dest);

            $is_success = self::create($dis_data, true)->result;

            if ($is_success === false) {
                $this->error = "图书条码[" . $dck_info["barcode"] . "]增加调拨历史数据失败";
                return false;
            }

            $save_data = array();
            $save_data["tsg_code"] = $tsg_code_dest;
            $save_data["tsg_code_has"] = $tsg_code_dest;
            $save_data["tsg_site_code"] = ($tsg_site_code_dest ? $tsg_site_code_dest : "");
            $save_data["tsg_site_code_has"] = ($tsg_site_code_dest ? $tsg_site_code_dest : "");
            $save_data["lt_type"] = ($lt_type_dest ? $lt_type_dest : "");
            $is_success = Dck::update($save_data, ['dck_id' => $dck_id])->result;

            if ($is_success === false) {
                $this->error = "图书条码[" . $dck_info["barcode"] . "]更新馆藏数据失败";
                return false;
            }

            $zch_data = array("tsg_code" => $tsg_code_dest);
            $is_success = Zch::update($zch_data, ['dck_id' => $dck_id])->result;

            if ($is_success === false) {
                $this->error = "图书条码[" . $dck_info["barcode"] . "]更新种次号数据失败";
                return false;
            }

            $param = [
                "book_id" => $dck_info['book_id'],
                "dck_id" => $dck_id,
                "op_desc" => "[#],图书条码[" . $dck_info["barcode"] . "]调拨成功"
            ];

            DcLog::addlog(DcLog::OP_TYPE_BATCH_DISPATCH2, $user_info, $param);
            return true;
        }catch (\Exception $e){
            $this->error = '程序异常,请稍后再试';
//            $this->error = $e->getMessage();
            return false;
        }
    }
}
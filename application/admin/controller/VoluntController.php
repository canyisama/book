<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Tsg;
use app\admin\model\Volunt;

class VoluntController extends BaseController
{
    const BATCH_STATUS_YD = 1;
    const BATCH_STATUS_YANSHOU = 2;
    const BATCH_STATUS_FINISH = 3;

    /**
     * 义工管理
     */
    public function indexAction()
    {
        $mod_dz_type = d("Dz_type");
        $dz_type_list = $mod_dz_type->get_list($this->_user_info["tsg_code"], array("field" => "dz_type_code,dz_type_name"));
        $this->assign("dz_type_list", $dz_type_list);
        $mod_dz_unit = d("Dz_unit");
        $unit_name_list = $mod_dz_unit->get_list($this->_user_info["tsg_code"]);
        $this->assign("unit_name_list", $unit_name_list);
        $mod_volunt = d("Volunt");
        $this->assign("status_list", $mod_volunt->getStatusList());
        return view();
    }

    /**
     * 异步获取
     */
    public function getJsonListAction()
    {
        $condition = ['volunt.tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                if (in_array($search['field'], ['unit_name', 'dz_type_code'])) {  // 读者表字段
                    $condition['dz.' . $search['field']] = $search['value'];
                } else if (in_array($search['field'], ['time_beg', 'time_end'])) {
                    $condition[$search['field']] = $search['value'];
                } else {   // 义工表字段
                    $condition['volunt.' . $search['field']] = ['like', '%' . $search['value'] . '%'];
                }
            }
        }
        if ($condition['time_beg'] && $condition['time_end']) {
            $condition['volunt.beg_time'] = ['between', [strtotime($condition['time_beg']), strtotime($condition['time_end'])]];
            unset($condition['time_beg']);
            unset($condition['time_end']);
        }

        $list = Volunt::alias('volunt')->join('lib_dzgl dz', 'volunt.dz_id=dz.dz_id')
            ->where($condition)->fetchSql(false)
            ->fieldRaw('volunt.*,dz.dz_code,dz.real_name')
            ->limit($params->limit)->order($params->order)->select();
        $count = Volunt::alias('volunt')->join('lib_dzgl dz', 'volunt.dz_id=dz.dz_id')->where($condition)->count();
        foreach ($list as $key => $item) {
            $list[$key]["lt_time"] = round($item["lt_time"] / 60, 1) . " 分钟";
            $list[$key]["beg_time"] = fmt_date_time($item['beg_time']);
            $list[$key]["end_time"] = fmt_date_time($item['end_time']);
        }
        return $this->echoPageData($list, $count);
    }

    public function ltAction()
    {
        $mod_dz_type = d("Dz_type");
        $dz_map = $mod_dz_type->getMap($this->_user_info["tsg_code"]);
        $this->assign("dz_map", json_encode($dz_map));
        $mod_tsg = d("Tsg");
        $tsg_info = $mod_tsg->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        $this->assign("tsg_info", $tsg_info);

        if ($tsg_info["room_mode"] == 2) {
            cookie('lend_type', 1);
        }

        $mod_volunt_type = d("Volunt_type");
        $volunt_type_list = $mod_volunt_type->where("tsg_code='{$this->_user_info["tsg_code"]}'")->order("order_num")->select();
        $this->assign("volunt_type_list", $volunt_type_list);
        return view();
    }

    /**
     * 出勤签到/签退
     */
    public function autoAction()
    {
        $dz_code = input('dz_code');
        $volunt_type = input('volunt_type');
        $mod_volunt = d("Volunt");

        if (!$dz_code) {
            $this->error('签到失败:无效的读者证号');
        }
        $ret_info = array();
        $mod_dz = d("dzgl");
        $dz_info = $mod_dz->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_code='$dz_code'")->find();

        if (!$dz_info) {
            $this->error('签到失败:系统未找到读者信息');
        }
        $dz_info['portrait'] = get_img_full_path($dz_info['portrait']);
        $ret_info["dz"] = $dz_info;
        $this->clear();

        if ($dz_info["dz_status"] != "有效") {
            $this->result($ret_info, 0, "签到失败:读者证当前状态:【{$dz_info["dz_status"]}】,无法签到");
        }

        $lend_info = $mod_volunt->where("tsg_code='{$this->_user_info["tsg_code"]}' AND dz_code='$dz_code' AND lt_status=" . Volunt::LT_STATUS_ON)->find();
        $op_name = "签到";
        $add_data = array("tsg_code" => $this->_user_info["tsg_code"], "op_user" => $this->_user_info["user_name"], "volunt_type" => $volunt_type, "dz_id" => $dz_info["dz_id"], "dz_code" => $dz_info["dz_code"], "real_name" => $dz_info["real_name"], "lt_status" => Volunt::LT_STATUS_ON, "beg_time" => time());

        if (!$lend_info) {
            $is_success = $mod_volunt->create($add_data);
        } else {
            $time_now = time();
            $data = array("lt_status" => Volunt::LT_STATUS_FINISH, "lt_time" => $time_now - $lend_info["beg_time"], "end_time" => $time_now);
            $is_success = $mod_volunt->update($data, ['volunt_id' => $lend_info['volunt_id']])->result;
            $op_name = "签退";
        }

        if ($is_success === false) {
            $this->error("{$op_name}失败:数据库更新失败");
        } else {
            $this->result($ret_info, 1, $op_name . '成功');
        }
    }

    private function clear()
    {
        $time_now = strtotime(date("Y-m-d") . " 00:00:00");
        $volunt = d("Volunt");
        $volunt->query("update lib_room_lend set end_time=beg_time,lt_time=0,lt_status=" . Volunt::LT_STATUS_FINISH . " where beg_time<$time_now AND lt_status=" . Volunt::LT_STATUS_ON);
    }

    public function editAction()
    {
        $volunt_id = input('volunt_id/d');
        $mod_volunt = d("Volunt");
        $volunt_info = $mod_volunt->where("volunt_id=$volunt_id")->find();
        $volunt_info['beg_time'] = fmt_date_time($volunt_info['beg_time']);
        $volunt_info['end_time'] = fmt_date_time($volunt_info['end_time']);

        if (!$this->isPost) {
            $mod_volunt_type = d("Volunt_type");
            $volunt_type_list = $mod_volunt_type->where("tsg_code='{$this->_user_info["tsg_code"]}'")->order("order_num")->select();
            $this->assign("volunt_type_list", $volunt_type_list);
            $lend_status_list = $mod_volunt->getStatusList();
            $this->assign("lend_status_list", $lend_status_list);
            $this->assign("volunt_info", $volunt_info);

            if (!$volunt_info) {
                $this->alertMsg(l("not_found_data"));
            }

            if ($volunt_info["tsg_code"] !== $this->_user_info["tsg_code"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
            return view();
        } else {
            if (!$volunt_info) {
                $this->error(l("not_found_data"));
            }

            if ($volunt_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(l("not_access_edit_data"));
            }
            $save_data = [
                'volunt_type' => input('volunt_type'),
                'lt_status' => input('lt_status'),
                'beg_time' => input('beg_time'),
                'end_time' => input('end_time'),
                'comment_num' => input('room_name/d')
            ];
            if (!$save_data["beg_time"]) {
                $this->error('开始时间不能为空');
            }

            if ($save_data["beg_time"]) {
                $save_data["beg_time"] = strtotime($save_data["beg_time"]);
            }

            if ($save_data["end_time"]) {
                $save_data["end_time"] = strtotime($save_data["end_time"]);
            }

            if ($save_data["beg_time"] && $save_data["end_time"]) {
                $save_data["lt_time"] = $save_data["end_time"] - $save_data["beg_time"];
            }

            unset($save_data["volunt_id"]);
            unset($save_data["tsg_code"]);
            $is_success = $mod_volunt->update($save_data, ['volunt_id' => $volunt_id])->result;

            if ($is_success !== false) {
                $this->success('保存成功');
            } else {
                $this->error('保存失败:更新数据库失败');
            }
        }
    }

    public function commentAction()
    {
        if (!$this->isPost) {
            $volunt_id = input('volunt_id');
            if (!$volunt_id) {
                $this->alertMsg('出勤信息ID不能为空');
            }
            $mod_volunt = d("Volunt");
            $volunt_list = $mod_volunt->where("volunt_id in($volunt_id)")->select();

            foreach ($volunt_list as $item) {
                if ($item["lt_status"] != Volunt::LT_STATUS_FINISH) {
                    $this->alertMsg('有出勤信息状态不是已完成,无法评分');
                }
            }
            $mod_volunt_ct = d("Volunt_ct");
            $volunt_ct_list = $mod_volunt_ct->where("tsg_code='{$this->_user_info["tsg_code"]}'")->order("order_num")->select();
            $this->assign("volunt_ct_list", $volunt_ct_list);
            return view();

        } else {

            $volunt_id = input('volunt_id');
            $volunt_ct_id = input('volunt_ct_id/a');
            if (!$volunt_id) {
                $this->error('出勤信息ID不能为空');
            }
            if (!$volunt_ct_id) {
                $this->error('必须选择评价方面');
            }
            $comment_num = 0;
            foreach ($volunt_ct_id as $item) {
                $comment_num += $item;
            }

            $save_data = array("comment_num" => $comment_num, "comment_user" => $this->_user_info["user_name"], "lt_status" => Volunt::LT_STATUS_COMMENT);
            $mod_volunt = d("Volunt");
            $is_success = $mod_volunt->update($save_data, ['volunt_id' => ['in', explode(',', $volunt_id)], 'lt_status' => Volunt::LT_STATUS_FINISH])->result;

            if ($is_success === false) {
                $this->error('评分失败:数据库更新失败');
            } else {
                $this->success('评分成功');
            }
        }
    }

}
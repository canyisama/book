<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/14
 * Time: 13:56
 */

namespace app\admin\controller;


class DatabackController extends BaseController
{

    public function indexAction()
    {
        $cnf = c("databack");
        $this->assign("cnf", $cnf);
        return view();
    }

    public function getJsonListAction()
    {
        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $webback_obj = \TaskBase::getTask("WebBackupTask");
        $file_path = fpath($webback_obj->getBackDir());
        $file_list_raw = $webback_obj->getFileList();
        rsort($file_list_raw);
        $file_list = array();
        $i = 1;

        foreach ($file_list_raw as $item) {
            $file_size = filesize($file_path . DIRECTORY_SEPARATOR . $item);
            $file_size = round($file_size / 1048576, 1);
            $file_data = substr($item, 5, 4) . "-" . substr($item, 9, 2) . "-" . substr($item, 11, 2) . " " . substr($item, 13, 2) . ":" . substr($item, 15, 2) . ":" . substr($item, 17, 2);
            $file_list[] = array("no" => $i, "name" => $item, "size" => $file_size, "date" => $file_data);
            $i++;
        }
        return $this->echoPageData($file_list);
    }

    public function back_begAction()
    {
        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $webback_obj = \TaskBase::getTask("WebBackupTask");
        $re_info = $webback_obj->back();
        $is_success = (strpos($re_info, "系统数据备份成功") !== false ? true : false);

        if ($is_success) {
            $this->success($re_info);
        } else {
            $this->error($re_info);
        }
    }

    public function drop_fileAction()
    {
        $file_name = input('file_name');
        if (!$file_name) {
            $this->error('文件名不能为空');
        }

        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $webback_obj = \TaskBase::getTask("WebBackupTask");
        $file_path = fpath($webback_obj->getBackDir() . "/" . $file_name);

        if (!file_exists($file_path)) {
            $this->error('备份文件不存在');
        }
        if (unlink($file_path)) {
            $this->success('删除备份文件成功');
        } else {
            $this->error('删除备份文件失败');
        }
    }

    public function save_cnfAction()
    {
        $is_enable = input('is_enable/d');
        $back_type = input('back_type/d', 1);
        $day_hour = input('day_hour', '');
        $day_hour = substr($day_hour, 0, 5);
        $day_hour_str = preg_replace("/\D/", "", $day_hour);
        $back_interval = input('back_interval/d');
        $max_file_cnt = input('max_file_cnt/d');

        if ($is_enable) {
            if ($back_type == 1) {
                if (!isset($_POST["day_hour"])) {
                    $this->error('每天备份的时间不能为空');
                }

                if (strlen($day_hour_str) != 4) {
                    $this->error('每天备份时间设置错误,时间格式为:HH:MM');
                }

                $hour_str = substr($day_hour_str, 0, 2);
                $min_str = substr($day_hour_str, 2, 2);
                if (($hour_str < 0) || (23 < $hour_str)) {
                    $this->error('每天备份的小时范围为0-23');
                }
                if (($min_str < 0) || (59 < $min_str)) {
                    $this->error('每天备份的分钟范围为0-59');
                }
            }

            if (($back_type == 2) && !$back_interval) {
                $this->error('每隔几点备份不能为空');
            }
            if (!$max_file_cnt) {
                $this->error('限制备份文件数量不能为空');
            }
        }

        $cnf = array("is_enable" => $is_enable, "back_type" => $back_type, "day_hour" => $day_hour, "back_interval" => $back_interval, "max_file_cnt" => $max_file_cnt);
        loaddatasave("databack", $cnf);
        $this->success('保存设置成功');
    }
}
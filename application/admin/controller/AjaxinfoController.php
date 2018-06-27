<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/10
 * Time: 10:24
 */

namespace app\admin\controller;


class AjaxinfoController extends BaseController
{
    public function read_txtAction()
    {
        $file = $this->request->file('barcode_file');
        if (!$file) {
            $this->error('上传文件为空');
        }
        $save_path = ROOT_PATH . 'public\uploads\tempfiles' . $this->adminInfo['user_id'] . DS;
        $info = $file->validate(['size' => 2147483648, 'ext' => 'txt'])->move($save_path);

        if ($info) {
            $file_path = $info->getSaveName();
            $file_buff = file_get_contents($save_path . $file_path);
            $file_buff = str_replace("\r\n", ",", $file_buff);
            $file_buff = str_replace(",,", ",", $file_buff);
            unset($info);
            @unlink($save_path . $file_path);
            $this->success('载入成功', '', $file_buff);
        } else {
            $this->error($file->getError());
        }

    }

    public function get_pinyinAction()
    {
        $hz_list = input('hz_list');

        if (!$hz_list) {
            $this->success('ok');
        }

        $hz_list = urldecode($hz_list);
        $hz_list = explode(",", $hz_list);
        $mod_pinyin = d("Pinyin");
        $where = array();
        $where["hz"] = array("in", $hz_list);
        $py_list = $mod_pinyin->field("hz,py2")->where($where)->select();
        $re_list = array();

        foreach ($py_list as $item) {
            $re_list[$item["hz"]] = explode(",", $item["py2"]);
        }

        $this->result($re_list, 1);
    }

    public function split_marcrawAction()
    {
        $marc_str = (isset($_POST["marc_str"]) ? trim($_POST["marc_str"]) : "");
        $mencode = (isset($_POST["mencode"]) ? trim($_POST["mencode"]) : "");

        import('Marc\MARC', EXTEND_PATH, '.class.php');
        if ($mencode == "gbk") {
            import('String', EXTEND_PATH, '.class.php');
            $marc_str = \String::autoCharset($marc_str, "utf-8", "gbk");
            $marc_obj = new \MARC($marc_str, "gbk");
            $marc_arr = $marc_obj->getDataConvEncode("utf-8");
        } else {
            $marc_obj = new \MARC($marc_str);
            $marc_arr = $marc_obj->getData();
        }
        $this->result($marc_arr, 1);
    }

}
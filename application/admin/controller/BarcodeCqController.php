<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 14:04
 */

namespace app\admin\controller;


class BarcodeCqController extends BaseController
{

    public function indexAction()
    {
        return view();
    }

    public function getbarcodeAction()
    {
        $mod_dck = d("Dck");
        $where = array();
        $where["tsg_code"] = array("eq", $this->_user_info["tsg_code"]);
        if (!input('barcode_beg') && !input('barcode_end')) {
            $this->error('查询失败！请输入图书条码范围!');
        }

        $barcode_beg = input('barcode_beg/d', input('barcode_end/d'));
        $barcode_end = input('barcode_end/d', input('barcode_beg/d'));;

        if (strlen($barcode_beg) != strlen($barcode_end)) {
            $this->error('查询失败,两个图书条码长度必须一致');
        }

        $where["barcode"] = array(
            "between",
            array(intval($barcode_beg), intval($barcode_end))
        );
        $where[] = ['exp', 'LENGTH(barcode)=' . strlen($barcode_beg)];
        $fields = "barcode";
        $dck_list = $mod_dck->field($fields)->where($where)->fetchSql(false)->order("barcode")->select();
        $this->result($dck_list, 1);
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
        $savePath = ROOT_PATH . 'public/' . config('upload_path') . 'tempfiles/' . $this->_user_info["user_id"] . '/barcode_cq_' . date("YmdHis") . ".txt";
        $dir = dirname($savePath);

        if (!file_exists($dir)) {
            mkdir($dir, 504);
        }

        file_put_contents($savePath, $file_buff);
        $fname = basename($savePath);
        import("ORG\Net\Http", EXTEND_PATH, '.class.php');
        \Http::download($savePath, $fname, $file_buff, 1800);
        @unlink($savePath);
    }

}
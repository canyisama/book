<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/21
 * Time: 15:31
 */

namespace app\admin\controller;

use app\admin\model\Report;
use app\admin\model\ReportGs;
use think\Exception;

/**
 * 报表管理
 * Class ReportController
 * @package app\admin\controller
 */
class ReportController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        import('Report\ReportAbstract', EXTEND_PATH, '.class.php');
    }

    /**
     * 报表首页
     * @return \think\response\View
     * @throws \Exception
     */
    public function indexAction()
    {
        $report_id = input('report_id/d');
        try {
            $report_info = Report::get($report_id);
            if (empty($report_info)) {
                $this->error("未找到此报表！");
            }
            $report_gs_list = ReportGs::where(['report_id' => $report_id])->select();
            $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->adminInfo));
            $_curlocal = $report_obj->get_curlocal();
            $this->assign("_curlocal", $_curlocal);
            $this->assign("report_info", $report_info);
            $this->assign("report_gs_list", $report_gs_list);
            $data_list = $report_obj->getSearchFormData($this->adminInfo);
            foreach ($data_list as $key => $item) {
                $this->assign($key, $item);
            }
            $this->assign('_user_info', $this->_user_info);
            //print_r($data_list);exit;
            return view($report_info["search_form"]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * 预览
     * @return \think\response\View
     * @throws \Exception
     */
    public function previewAction()
    {
        $report_gs_id = input('report_gs_id/d');

        $mod_report = d("Report");
        $mod_report_gs = d("ReportGs");

        $report_gs_info = $mod_report_gs->where(['report_gs_id' => $report_gs_id])->find();

        if (empty($report_gs_info)) {
            $this->error("无效的报表格式！");
        }

        $report_info = $mod_report->where(['report_id' => $report_gs_info["report_id"]])->find();
        if (empty($report_info)) {
            $this->error("未找到对应的报表！");
        }

        $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
        $_curlocal = $report_obj->get_curlocal();
        $this->assign("_curlocal", $_curlocal);

        $param = $report_obj->getParam(1);

        $report_data = $report_obj->getReportData($report_gs_info, $param, true);
        $ext_data = $report_obj->get_ext_data($report_gs_info);
        $this->assign("ext_data", $ext_data);
        $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
        $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);
        $this->assign("report_gs_info", $report_gs_info);
        $this->assign("report_info", $report_info);
        $this->assign("report_param_data", bin2hex(serialize($param)));
        $this->assign("report_data", $report_data);
        return view($report_info["preview_form"]);
    }

    /**
     * 打印
     * @return bool|\think\response\View
     * @throws \Exception
     */
    public function report_printAction()
    {
        $report_gs_id = input('report_gs_id/d');
        try {
            $mod_report = d("Report");
            $mod_report_gs = d("ReportGs");
            $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();

            if (empty($report_gs_info)) {
                $this->error("无效的报表格式！");
                return false;
            }
            $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
            if (empty($report_info)) {
                $this->error("未找到对应的报表！");
            }

            $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
            $_curlocal = array(
                array("text" => "报表预览"),
                array("text" => $report_info["report_name"])
            );
            $this->assign("_curlocal", $_curlocal);
            $param = $report_obj->getParam(2);
            $report_data = $report_obj->getReportData($report_gs_info, $param, false);
            $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
            $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);
            $this->assign("report_gs_info", $report_gs_info);
            $ext_data = $report_obj->get_ext_data($report_gs_info);
            $this->assign("ext_data", $ext_data);
            $this->assign("report_param_data", bin2hex(serialize($param)));
            $this->assign("report_data", $report_data);
            return view($report_info["print_form"]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    /**
     * 导出弹出层
     * @return \think\response\View
     */
    public function exportselAction()
    {
        $report_gs_id = input('report_gs_id/d');
        $mod_report = d("Report");
        $mod_report_gs = d("ReportGs");
        $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();
        if (empty($report_gs_info)) {
            $this->error("无效的报表格式！");
        }
        $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
        if (empty($report_info)) {
            $this->error("未找到对应的报表！");
        }
        return view();
    }

    /**
     * 导出为PDF
     * @throws \Exception
     */
    public function export_pdfAction()
    {
        $report_gs_id = input('report_gs_id/d');
        $in_browse = input('in_browse/d');
        $mod_report = d("Report");
        $mod_report_gs = d("ReportGs");
        $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();
        if (empty($report_gs_info)) {
            $this->error("无效的报表格式！");
        }
        $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
        if (empty($report_info)) {
            $this->error("未找到对应的报表！");
        }

        $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
        $_curlocal = array(
            array("text" => "报表预览"),
            array("text" => $report_info["report_name"])
        );
        $this->assign("_curlocal", $_curlocal);
        $param = $report_obj->getParam(2);
        $report_data = $report_obj->getReportData($report_gs_info, $param, false);
        $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
        $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);
        $this->assign("report_gs_info", $report_gs_info);
        $this->assign("report_param_data", bin2hex(serialize($param)));
        $ext_data = $report_obj->get_ext_data($report_gs_info);
        $this->assign("report_data", $report_data);

        import('Tcpdf\ReportPdf', EXTEND_PATH, '.class.php');
        $tcpdf_path = APP_PATH . "Lib" . DIRECTORY_SEPARATOR . "Extend" . DIRECTORY_SEPARATOR . "Tcpdf" . DIRECTORY_SEPARATOR;
        $pdf = new \ReportPdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, "UTF-8", false);
        $pdf->head_info = array("title" => $report_gs_info["report_title"], "font" => "Droid_Sans_Fallback", "font_size" => "14");
        $pdf->foot_info = array("font" => "Droid_Sans_Fallback", "font_size" => "10");
        $pdf->SetCreator("weblib");
        $pdf->SetAuthor("weblib");
        $pdf->SetTitle("weblib");
        $pdf->SetSubject("weblib");
        $pdf->SetKeywords("weblib");
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $mar = array("left" => 2, "top" => 13, "right" => 2);
        $pdf->SetMargins($mar["left"], $mar["top"], $mar["right"]);
        $pdf->SetHeaderMargin(6);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        if (@file_exists($tcpdf_path . "/lang/chi.php")) {
            require_once($tcpdf_path . "/lang/chi.php");
            $pdf->setLanguageArray($l);
        }

        $pdf->SetFont("Droid_Sans_Fallback", "", $report_gs_info["page_cnf_arr"]["gs_font_size"]);
        $pdf->AddPage();
        $th_arr = array();

        foreach ($report_gs_info["fields_cnf_arr"] as $item) {
            $th_arr[] = "<th align=\"center\" width=\"{$item["width"]}{$item["width_dw"]}\">{$item["name"]}</th>";
        }

        $fields_list = array();
        foreach ($report_gs_info["fields_cnf_arr"] as $item) {
            $width_dw = ($item["width_dw"] == "%" ? "%" : "");
            $fields_list[$item["field"]] = $item["width"] . $width_dw;
        }

        $tr_arr = array();
        foreach ($report_data["data_list"] as $item) {
            $td_tmp = array();
            foreach ($report_gs_info["fields_cnf_arr"] as $key1 => $item1) {
                $val_tmp = \ReportAbstract::getCellVal($item[$item1["field"]], $item1);
                $width_dw = ($item1["width_dw"] == "%" ? "%" : "");
                $width_tmp = $item1["width"] . $width_dw;
                $td_tmp[] = "<td width=\"" . $width_tmp . "\">" . $val_tmp . "</td>";
            }
            $tr_arr[] = "<tr>" . implode("", $td_tmp) . "</tr>";
        }

        $tfoot_th = array();
        $i = 1;
        $tfoot_html = "";

        if (!empty($report_data["sum_data"])) {
            foreach ($report_gs_info["fields_cnf_arr"] as $item) {
                $th_str = ($report_data["sum_data"][$item["field"]] ? $report_data["sum_data"][$item["field"]] : (isset($report_data["sum_data"][$item["field"]]) ? 0 : ""));
                $th_str = nl2br($th_str);
                ($th_str !== "") && ($th_str .= $item["suffix"]);

                if ($i == 1) {
                    $tfoot_th[] = "<th>合计:$th_str</th>";
                } else {
                    $tfoot_th[] = "<th>$th_str</th>";
                }
                $i++;
            }
            $tfoot_html = " <tfoot> <tr>" . implode("", $tfoot_th) . "</tr></tfoot>";
        }

        $html = "<div class=\"report_data\" style=\"font-size:{$report_gs_info["page_cnf_arr"]["gs_font_size"]}; font-family:{$report_gs_info["page_cnf_arr"]["gs_font"]};text-align:center;width:100%;\">\r\n<table id=\"report_preview_table\" border=\"{$report_gs_info["page_cnf_arr"]["gs_border"]}\" cellpadding=\"{$report_gs_info["page_cnf_arr"]["gs_cell_jj"]}\" cellspacing=\"{$report_gs_info["page_cnf_arr"]["gs_cell_bj"]}\" width=\"{$report_gs_info["page_cnf_arr"]["gs_table_width"]}{$report_gs_info["page_cnf_arr"]["gs_table_width_dw"]}\">\r\n      <thead>\r\n\t  <tr><td align=\"center\">" . implode("&nbsp;&nbsp;&nbsp;&nbsp;", $ext_data) . "</td></tr>\r\n        <tr class=\"odd even\">" . implode("", $th_arr) . "</tr>\r\n      </thead>\r\n      <tbody>" . implode("", $tr_arr) . "</tbody>\r\n     " . $tfoot_html . "\r\n    </table>\r\n</div>";
        $pdf->writeHTML($html, true, false, true, false, "");
        $pdf->lastPage();
        $etype = ($in_browse == "1" ? "I" : "D");
        $pdf->Output(time() . ".pdf", $etype);
        exit;
    }

    /**
     * 导出为Excel
     * @return bool
     * @throws \Exception
     */
    public function export_excelAction()
    {
        try {
            $report_gs_id = input('report_gs_id/d');
            $ver = input('ver');
            $ver = (in_array($ver, array("Excel5", "Excel2007")) ? $ver : "Excel5");
            $page_width = input('page_width');
            $dpi = input('dpi');
            $mod_report = d("Report");
            $mod_report_gs = d("ReportGs");
            $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();

            if (empty($report_gs_info)) {
                $this->error("无效的报表格式！");
            }
            $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
            if (empty($report_info)) {
                $this->error("未找到对应的报表！");
            }
            $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
            $_curlocal = array(
                array("text" => "报表预览"),
                array("text" => $report_info["report_name"])
            );
            $this->assign("_curlocal", $_curlocal);
            $param = $report_obj->getParam(2);
            $report_data = $report_obj->getReportData($report_gs_info, $param, false);
            $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
            $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);
            $ext_data = $report_obj->get_ext_data($report_gs_info);
            import('phpExport\phpExport', EXTEND_PATH, '.class.php');
//            print_r($report_info["report_name"]);
//            exit();
            $exporter = new \ExportDataExcel("browser", $report_info["report_name"] . ".xls");
            $exporter->initialize();
            $head_list = array();


            foreach ($report_gs_info["fields_cnf_arr"] as $item) {
                $head_list[] = $item["name"];
            }
            $exporter->addRow($head_list);
            foreach ($report_data["data_list"] as $item) {
                $data_row = array();
                foreach ($report_gs_info["fields_cnf_arr"] as $key1 => $item1) {
                    $val_tmp = \ReportAbstract::getCellVal($item[$item1["field"]], $item1);
                    $data_row[] = $val_tmp;
                }
                $exporter->addRow($data_row);
                $data_row = array();
            }
            $data_row = array();
            $i = 0;
            foreach ($report_gs_info["fields_cnf_arr"] as $item) {
                $tmp_str = ($report_data["sum_data"][$item["field"]] ? $report_data["sum_data"][$item["field"]] : (isset($report_data["sum_data"][$item["field"]]) ? 0 : ""));
                if ($i == 0) {
                    $tmp_str = "合计:$tmp_str";
                }
                $i++;
                ($tmp_str !== "") && ($tmp_str .= $item["suffix"]);
                $data_row[] = $tmp_str;
            }
            $exporter->addRow($data_row);
            $exporter->finalize();
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    /**
     * 导出为文本
     * @return bool
     * @throws \Exception
     */
    public function export_txtAction()
    {
        try {
            $report_gs_id = input('report_gs_id/d');
            $txt_type = input('txt_type', '');
            $txt_type = (in_array($txt_type, array("txt", "csv")) ? $txt_type : "txt");
            $mod_report = d("Report");
            $mod_report_gs = d("ReportGs");
            $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();
            if (empty($report_gs_info)) {
                $this->error("无效的报表格式！");
            }
            $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
            if (empty($report_info)) {
                $this->error("未找到对应的报表！");
            }

            import('String', EXTEND_PATH, '.class.php');
            $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
            $param = $report_obj->getParam(2);
            $report_data = $report_obj->getReportData($report_gs_info, $param, false);
            $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
            $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);
            $ext_data = $report_obj->get_ext_data($report_gs_info);
            $col_cnt = count($report_gs_info["fields_cnf_arr"]);
            $field_list = array();

            foreach ($report_gs_info["fields_cnf_arr"] as $item) {
                $field_list[] = ($txt_type == "csv" ? \String::autoCharset($item["name"], "utf-8", "gbk") : $item["name"]);
            }

            $sep_str = ($txt_type == "txt" ? "\t" : ",");
            $down_file_name = $report_info["report_name"] . "." . $txt_type;
            $this->_beg_down($down_file_name);

            if ($txt_type == "csv") {
                $this->_beg_down($down_file_name, "gbk");
            } else {
                $this->_beg_down($down_file_name);
            }

            echo implode($sep_str, $field_list);
            echo "\r\n";
            $tr_arr = array();

            foreach ($report_data["data_list"] as $item) {
                $show_arr = array();

                foreach ($report_gs_info["fields_cnf_arr"] as $key1 => $item1) {
                    $val_tmp = $item[$item1["field"]];

                    if ($item1["type"] == "date") {
                        $val_tmp = ($item1["date_gs"] == 1 ? date("Y-m-d H:i:s", $val_tmp) : date("Y-m-d", $val_tmp));
                    }

                    if ($txt_type == "csv") {
                        if ($item1["field"] == "barcode") {
                            $val_tmp = "[$val_tmp]";
                        }

                        $val_tmp = str_replace($sep_str, "，", $val_tmp);
                    } else {
                        $val_tmp = str_replace($sep_str, "", $val_tmp);
                    }

                    ($val_tmp !== "") && ($val_tmp .= $item1["suffix"]);
                    $show_arr[] = ($txt_type == "csv" ? \String::autoCharset($val_tmp, "utf-8", "gbk") : $val_tmp);
                }

                echo implode($sep_str, $show_arr);
                echo "\r\n";
            }

            $show_arr = array();
            $i = 0;

            foreach ($report_gs_info["fields_cnf_arr"] as $item) {
                $tmp_str = ($report_data["sum_data"][$item["field"]] ? $report_data["sum_data"][$item["field"]] : (isset($report_data["sum_data"][$item["field"]]) ? 0 : ""));

                if ($i == 0) {
                    $tmp_str = "合计:$tmp_str";
                }

                ($tmp_str !== "") && ($tmp_str .= $item["suffix"]);
                $show_arr[] = ($txt_type == "csv" ? \String::autoCharset($tmp_str, "utf-8", "gbk") : $tmp_str);
                $i++;
            }

            echo implode($sep_str, $show_arr);
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    public function chartAction($report_gs_id)
    {
        try {
            $mod_report = d("Report");
            $mod_report_gs = d("ReportGs");
            $report_gs_info = $mod_report_gs->where("report_gs_id=$report_gs_id")->find();
            if (empty($report_gs_info)) {
                $this->error("无效的报表格式！");
            }
            $report_info = $mod_report->where("report_id={$report_gs_info["report_id"]}")->find();
            if (empty($report_info)) {
                $this->error("未找到对应的报表！");
            }

            $report_obj = \ReportAbstract::getReport($report_info["class_name"], array("report_info" => $report_info, "user_info" => $this->_user_info));
//            $_curlocal = $report_obj->get_curlocal();
//            $this->assign("_curlocal", $_curlocal);
            $param = $report_obj->getParam(1);

            $report_data = $report_obj->getReportData($report_gs_info, $param, false);
//            $color_arr = array("AFD8F8", "F6BD0F", "8BBA00", "FF8E46", "008E8E", "D64646", "8E468E", "588526", "B3AA00", "008ED6", "FFFFCC", "CCFFFF", "CCCCFF", "66CCCC", "CCFF66", "666699", "#CCFF00", "FF9900", "993366", "FF6666", "009999", "CCCC33", "CCCC66", "CC9933", "666633", "CC9966");
            $report_gs_info["fields_cnf_arr"] = unserialize($report_gs_info["fields_cnf"]);
            $report_gs_info["page_cnf_arr"] = unserialize($report_gs_info["page_cnf"]);

//            $chart_param = array("color_arr" => $color_arr, "graph_param" => "baseFontSize='12' baseFont='宋体'  chartLeftMargin='5' chartRightMargin='0' chartTopMargin='20'  yAxisMinValue='0' yAxisMaxValue='10'  chartBottomMargin='0' animation='1' pieFillAlpha='60' showLegend='1'  formatNumber='0' formatNumberScale='0' caption='{$report_gs_info["report_title"]}' showNames='1'");

            $chart_param = array('caption'=>$report_gs_info["report_title"]);
            $chart_data = $report_obj->getChartData($chart_param, $report_data, $report_gs_info);

//            $this->assign("rel_list", $chart_data["rel_list"]);
//            $ext_data = $report_obj->get_ext_data($report_gs_info);
//            $this->assign("ext_data", $ext_data);
//            $this->assign("data_list", $chart_data["data_list"]);
//            $this->assign("ext_data", $chart_data["ext_data"]);
//            $this->assign("report_gs_info", $report_gs_info);
//            $this->assign("report_info", $report_info);
//            $this->assign("report_param_data", bin2hex(serialize($param)));
//            $this->assign('type_list',$chart_data["ext_data"]['data_type_list']);

            $this->assign("swf_default", $chart_data["swf_default"]);
            $this->assign("swf_list", $chart_data["swf_list"]);

            $list = [];
            $chart_data['ext_data']['data_type_list'] = isset($chart_data['ext_data']['data_type_list']) ? $chart_data['ext_data']['data_type_list'] : ['sum'=>'数量'];
            //图表类型
            $list['chart'] = $chart_data['swf_default'];
            //图表标题
            $list['title'] = $chart_param['caption'];

            foreach ($chart_data["ext_data"]['data_type_list'] as $key => $value){
                $list['series'][$key]['name'] = $value;
                //隐藏所有y轴
                $list['series'][$key]['visible'] = false;
                $list['type_field'][] = $key;
            }

            foreach ($report_data['data_list'] as $key => $item){
                //x轴名称
                $list['xName'][] = $item[$chart_data['chart_name']];
                //数据格式化
                foreach ($list['type_field'] as $field){
                    $list['series'][$field]['data'][] = floatval($item[$field]);
                }
            }
            unset($list['type_field']);

            $list['series'] = array_values($list['series']);
            //显示y轴第一条数据
            $list['series'][0]['visible'] = true;

            $this->assign('list', json_encode($list));
            $this->assign('report_id', input('report_id/d'));
//            print_r($chart_data["tpl"]);exit();

            return view($chart_data["tpl"]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }

    public function _beg_down($filename, $charset = "utf-8")
    {
        if (empty($showname)) {
            $showname = $filename;
        }

        $showname = basename($showname);
        $type = "application/octet-stream";
        $expire = 180;
        header("Pragma: public");
        header("Cache-control: max-age=" . $expire);
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expire) . "GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . "GMT");
        header("Content-Disposition: attachment; filename=" . $showname);
        header("Content-type: " . $type);
        header("Content-Encoding: none");
        header("Content-Transfer-Encoding: binary");
        header("Content-Type:text/html;charset={$charset}");
    }

}
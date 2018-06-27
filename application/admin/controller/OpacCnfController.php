<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/16
 * Time: 14:36
 */

namespace app\admin\controller;


class OpacCnfController extends BaseController
{

    public function cnfAction()
    {
        if (!$this->isPost) {
            $mod_book = d("Book");
            import('Sphinx\Sphinx', EXTEND_PATH, '.class.php');
            $sph_obj = new \Sphinx();
            $cnt_list = array();
            $cnt_list["book"] = $mod_book->count("0");
            $ret_info = $sph_obj->clientObj->Query("", "weblib");
            $main_cnt = (isset($ret_info["total_found"]) ? $ret_info["total_found"] : 0);
            import('String', EXTEND_PATH, '.class.php');
            $status_str = ($ret_info === false ? "<span class=\"conn_fail\">连接失败:" . \String::autoCharset($sph_obj->clientObj->GetLastError(), "gbk", "utf-8") . "</span>" : "<span class=\"conn_ok\">正在运行</span>");
            $ret_info = $sph_obj->clientObj->Query("", "delta");
            $delta_cnt = (isset($ret_info["total_found"]) ? $ret_info["total_found"] : 0);
            $cnt_list["main"] = $main_cnt;
            $cnt_list["delta"] = $delta_cnt;
            $this->assign("cnt_list", $cnt_list);
            $this->assign("status_str", $status_str);
            $sphinx_data = c("sphinx");
            $this->assign("sphinx_data", $sphinx_data);
            return view();
        } else {
            $sphinx_data = c("sphinx");
            $data = array("web_title" => isset($_POST["web_title"]) ? trim($_POST["web_title"]) : "", "index_title" => isset($_POST["index_title"]) ? trim($_POST["index_title"]) : "", "sphinx_host" => isset($_POST["sphinx_host"]) ? trim($_POST["sphinx_host"]) : "", "sphinx_port" => isset($_POST["sphinx_port"]) ? trim($_POST["sphinx_port"]) : "", "path" => isset($_POST["path"]) ? trim($_POST["path"]) : "", "check_time" => isset($_POST["check_time"]) ? intval($_POST["check_time"]) : 0, "delta_time" => isset($_POST["delta_time"]) ? intval($_POST["delta_time"]) : 0, "merge_time" => isset($_POST["merge_time"]) ? intval($_POST["merge_time"]) : 0, "cas_open" => isset($_POST["cas_open"]) ? intval($_POST["cas_open"]) : 0, "cas_ver" => isset($_POST["cas_ver"]) && in_array($_POST["cas_ver"], array("1.0", "2.0")) ? trim($_POST["cas_ver"]) : "2.0", "cas_host" => isset($_POST["cas_host"]) ? trim($_POST["cas_host"]) : "", "cas_port" => isset($_POST["cas_port"]) ? trim($_POST["cas_port"]) : "", "cas_uri" => isset($_POST["cas_uri"]) ? trim($_POST["cas_uri"]) : "");

            if (!$data["sphinx_host"]) {
                $this->error("SPHINX服务器IP不能为空");
            }
            if (!$data["sphinx_port"]) {
                $this->error("SPHINX服务器端口不能为空");
            }
            if (!$data["path"]) {
                $this->error("SPHINX路径不能为空");
            }
            if (!file_exists($data["path"])) {
                $this->error("SPHINX路径不存在");
            }
            if (!file_exists($data["path"] . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "searchd.exe") && !file_exists($data["path"] . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "searchd")) {
                $this->error("不存在bin/searchd.exe文件(linux为searchd)");
            }
            if (!$data["check_time"]) {
                $this->error("SPHINX进程检测间隔不能为空");
            }
            if (!$data["delta_time"]) {
                $this->error("增量索引更新间隔不能为空");
            }
            if (!$data["merge_time"]) {
                $this->error("增量索引合并间隔不能为空");
            }

            $sphinx_data["web_title"] = $data["web_title"];
            $sphinx_data["index_title"] = $data["index_title"];
            $sphinx_data["sphinx_host"] = $data["sphinx_host"];
            $sphinx_data["sphinx_port"] = $data["sphinx_port"];
            $sphinx_data["sphinx_path"] = $data["path"];
            $sphinx_data["sphinx_check_time"] = $data["check_time"];
            $sphinx_data["sphinx_delta_time"] = $data["delta_time"];
            $sphinx_data["sphinx_merge_time"] = $data["merge_time"];
            $sphinx_data["cas_open"] = $data["cas_open"];
            $sphinx_data["cas_ver"] = $data["cas_ver"];
            $sphinx_data["cas_host"] = $data["cas_host"];
            $sphinx_data["cas_port"] = $data["cas_port"];
            $sphinx_data["cas_uri"] = $data["cas_uri"];
            loaddatasave("sphinx", $sphinx_data);
            import('PhpDaemon\PhpDaemon', EXTEND_PATH, '.class.php');
            $daemon_obj = new \PhpDaemon();
            $daemon_obj->quitDaemon();
            $this->success("配置保存成功");
        }
    }

    public function init_indexAction()
    {
        $is_pair = input('is_pair/d');

        if ($is_pair) {
            $meta_path = fpath("{$_SERVER["DOCUMENT_ROOT"]}/sphinx/var/data/binlog.meta");
            unlink($meta_path);
            $this->success('修复全文检索服务成功,可以尝试启动全文检索服务');
        }

        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $sphinx_obj = \TaskBase::getTask("SphinxTask");
        $re_info = array();
        $re_info["main"] = $sphinx_obj->mainIndex();
        $re_info["delta"] = $sphinx_obj->deltaIndex();
        $this->result($re_info, 1, '重建全文索引操作完成');
    }

    public function repair_sphinxAction()
    {
        $meta_path = fpath("{$_SERVER["DOCUMENT_ROOT"]}/sphinx/var/data/binlog.meta");
        unlink($meta_path);
        $this->success('修复全文检索服务成功,可以尝试启动全文检索服务');
    }

    public function index_delta_upAction()
    {
        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $sphinx_obj = \TaskBase::getTask("SphinxTask");
        $re_info = $sphinx_obj->deltaIndex();
        $this->result($re_info, 1, '更新增量索引操作完成');
    }

    public function start_sphinxAction()
    {
        import('Task\TaskBase', EXTEND_PATH, '.class.php');
        $sphinx_obj = \TaskBase::getTask("SphinxTask");
        $status = $sphinx_obj->isOpen();

        if ($status === false) {
            ignore_user_abort(true);
            set_time_limit(0);
            $path = \SphinxTask::getPath();
            $is_win = \SphinxTask::isWindows();
            $cmd = ($is_win ? "cd /d {$path["base"]} & {$path["bin"]}searchd  -c {$path["cnf"]}weblib.conf" : "cd {$path["base"]} ; {$path["bin"]}searchd  -c {$path["cnf"]}weblib.conf");
            $cmd_info = array();
            exec($cmd, $cmd_info);
            $this->result($cmd_info, 0, '启动全文检索服务遇到错误');
        }
        $this->success('全文检索服务已经运行,无法启动');
    }

}
<?php

namespace app\admin\controller;

use app\admin\model\Acl;
use app\admin\model\Admin;
use app\admin\model\Role;
use app\admin\model\User;
use think\Controller;
use think\Lang;

class BaseController extends Controller
{
    protected $web_title = '创启云图书馆';
    protected $needLogin = true;
    protected $adminInfo = null;
    protected $_user_info = null;
    // 异步操作返回值
    protected $ajaxResult = ['code' => 1, 'msg' => '操作成功!', 'data' => null];
    protected $MODULE_NAME = null;
    protected $ACTION_NAME = null;

    const CLOSE_LAYER = 0; // 关闭弹出层
    const PARENT_RELOAD = 1; // 刷新父级页面
    const SELF_RELOAD = 2; // 刷新当前

    public function _initialize()
    {
        $this->assign('web_title', $this->web_title);
        // 加载语言包
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home.php']);

        $isLogined = User::isLogined();
        if ($this->needLogin && !$isLogined) {
            $this->redirect('login/index');
            exit();
        }

        if (!$this->adminInfo) {
            $this->adminInfo = User::get(['user_id' => session('user_id')]);
            $this->adminInfo['tsg_code'] = session('tsg_code');
            $this->adminInfo['tsg_name'] = session('tsg_name');
            $this->adminInfo['is_admin'] = session('is_admin');
            $this->adminInfo['is_supper'] = session('is_supper');
            $this->adminInfo['is_main_tsg'] = session('is_main_tsg');

            $this->_user_info = $this->adminInfo;
        }

        $this->MODULE_NAME = $this->request->controller();
        $this->ACTION_NAME = $this->request->action();
        $this->assign('_ACTION_NAME_', $this->ACTION_NAME);
        if (!$this->check_acl()) {
            $this->error('没有访问权限!');
        }

        $this->getMenu();
    }

    /**
     * 输出页面菜单
     * menu_tab
     * menu_list_data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getMenu()
    {
        $nav = config('menu');
        $has_acl = Role::get_has_acl($this->adminInfo);
        !$has_acl && ($has_acl = array(0));

        $acl_info = Acl::where(['acl_id' => ['not in', $has_acl]])->field('acl_val')->select();
        $acl_list = array();

        if (!empty($acl_info)) {
            foreach ($acl_info as $item) {
                $acl_list[] = $item["acl_val"];
            }
        }

        if (!$this->adminInfo["is_supper"] && !$this->adminInfo["is_admin"]) {
            foreach ($nav as $key => $item) {
                foreach ($item["children"] as $key1 => $item1) {
                    foreach ($item1["children"] as $key2 => $item2) {
                        $url_tmp = str_replace("/", "_", $item2["url"]);

                        if (substr($url_tmp, 0, 7) == "_Report") {
                            $url_tmp = str_replace("_report_id", "", $url_tmp);
                        }

                        $curr_acl = APP_NAME . "_" . GROUP_NAME . $url_tmp;

                        if (in_array($curr_acl, $acl_list)) {
                            unset($nav[$key]["children"][$key1]["children"][$key2]);
                        }
                    }
                }
            }

            foreach ($nav as $key => $item) {
                foreach ($item["children"] as $key1 => $item1) {
                    foreach ($item1["children"] as $key2 => $item2) {
                        if (empty($item2)) {
                            unset($nav[$key]["children"][$key1]["children"][$key2]);
                        }
                    }
                }
            }

            foreach ($nav as $key => $item) {
                foreach ($item["children"] as $key1 => $item1) {
                    if (empty($item1["children"])) {
                        unset($nav[$key]["children"][$key1]);
                    }
                }
            }
        }

        array_walk_recursive($nav, function (&$value, $key) {
            if (($key == "text") || ($key == "subtext")) {
                $value = lang($value);
            }

            if ($key == "url") {
                $value = url($value);
            }
        });
        $menu_tab = array();

        foreach ($nav as $key => $item) {
            $menu_tab[$key] = $item["text"];
        }

        $this->assign("menu_tab", $menu_tab);
        $this->assign("menu_list_data", $nav);
        $this->display("index");
    }

    /**
     * 权限检查
     * @return bool
     * @throws \think\exception\DbException
     */
    private function check_acl()
    {
        // 控制器名称驼峰转下划线()
        $rs = preg_match_all('/[A-Z]/', $this->MODULE_NAME, $result);
        if ($rs) {
            if ($result[0][1]) {
                $this->MODULE_NAME = trim(str_replace($result[0][1], '_' . strtolower($result[0][1]), $this->MODULE_NAME), '_');
            }
        }

        $check_acl_val = APP_NAME . "_" . GROUP_NAME . "_" . $this->MODULE_NAME . "_" . $this->ACTION_NAME;
        if ($this->MODULE_NAME == 'Public') {
            return true;
        }
        if ($this->MODULE_NAME == "Report") {
            $check_acl_val = APP_NAME . "_" . GROUP_NAME . "_" . $this->MODULE_NAME . "_" . $this->ACTION_NAME . "_" . input('report_id');
        } else {
            if (($this->MODULE_NAME == "Catalog") && ($this->ACTION_NAME == "index")) {
                $curval = input('curval') ?: 1;
                $check_acl_val = APP_NAME . "_" . GROUP_NAME . "_" . $this->MODULE_NAME . "_" . $this->ACTION_NAME . "_" . $curval;
            }
        }
        $acl_info = Acl::get(['acl_val' => $check_acl_val]);
        if (!empty($acl_info) && !$this->adminInfo["is_supper"] && !$this->adminInfo["is_admin"]) {
            $has_acl = Role::get_has_acl($this->adminInfo);
            if (!in_array($acl_info["acl_id"], $has_acl)) {
                return false;
            }
        }

        return true;
    }

    protected function _post($param)
    {
        return input($param);
    }

    protected function _get($param)
    {
        return input($param);
    }

    /**
     * 统一返回列表查询参数对象
     * @return \stdClass
     */
    protected function getQueryParams()
    {
        $offset = input('offset/d');
        $limit = input('limit/d');
        $order = input('sort') . ' ' . input('order');
        $search = input('search');

        $params = new \stdClass();
        $params->limit = $offset . ',' . $limit;
        $params->order = $order;
        $params->search = $search ? json_decode($search, true) : null;
        return $params;
    }

    public function echoSuccess($data = null, $msg = '操作成功')
    {
        $this->ajaxResult['msg'] = $msg;
        $this->ajaxResult['data'] = $data;
        echo json_encode($this->ajaxResult);
        exit();
    }

    public function echoError($msg = '操作失败', $data = null)
    {
        $this->ajaxResult['msg'] = $msg;
        $this->ajaxResult['code'] = 0;
        $this->ajaxResult['data'] = $data;
        echo json_encode($this->ajaxResult);
        exit();
    }

    /**
     * 返回layer弹出框
     * @param $msg @提示消息
     * @param int $type 图标,0警告,1正确,2错误
     * @param int $callBackType
     */
    public function alertMsg($msg, $type = 0, $callBackType = 0)
    {
        echo '<script type="text/javascript"> parent.layer.alert("' . $msg . '", {icon: ' . $type . '}, function () {';
        switch ($callBackType) {
            case self::CLOSE_LAYER:
                echo 'parent.layer.closeAll();';
                break;
            case self::PARENT_RELOAD:
                echo 'parent.location.reload();';
                break;
            case self::SELF_RELOAD:
                echo 'location.reload();';
                break;
            default:
                break;
        }
        echo '});</script>';
        exit();
    }

    /**
     * 统一输出分页数据
     * @param $list @分页列表
     * @param int $total 总记录数
     * @param null $data 额外数据
     */
    public function echoPageData($list, $total = 0, $data = null)
    {
        echo json_encode(['rows' => $list, 'total' => $total, 'data' => $data]);
        exit;
    }

    public function dck_assign_common($book_id, $ac_type)
    {
        $mod_dck = d("Dck");
        $mod_batch = d("Batch");
        $mod_book = d("Book");
        $mod_jdbm_cnf = d("Jdbm_cnf");
        $mod_tsg_site = d("Tsg_site");
        $mod_user = d("User");
        $mod_tsg = d("Tsg");
        $mod_ltype = d("Ltype");
        $book_info = $mod_book->field("lib_book.book_id,lib_book.mt_id,title,isbn,clc,firstauthor,publisher,pubplace,pubdate,price_ms,bl_title,othertitle,fjno,fjtitle,marc")->where("book_id=$book_id")->find();

        if (empty($book_info)) {
            $this->error('不存在的书目信息,无法增加馆藏!');
        }

        $ltype_list = $mod_ltype->get_list($this->_user_info["tsg_code"]);
        $batch_list = $mod_batch->field("batch_no")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $currency_list = $mod_jdbm_cnf->field("remark,cnf_val")->where("cnf_type='货币类型' AND tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $jz_list = $mod_jdbm_cnf->field("remark,cnf_val")->where("cnf_type='介质类型' AND tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $ly_list = $mod_jdbm_cnf->field("remark,cnf_val")->where("cnf_type='图书来源' AND tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $site_code_list = $mod_tsg_site->field("tsg_site_code,site_name")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->order("tsg_site_code")->select();
        $user_info = $mod_user->field("batch_no_curr,mzd_calino")->where("user_id='{$this->_user_info["user_id"]}'")->find();
        $tsg_info = $mod_tsg->field("calino_type,is_calino_cf,loginno_accord,barcode_len,calino_has_sep2")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->find();


        $status_list = c("dck.status_list");

        if ($ac_type == "edit") {
        } else {
            import('Marc\MARC', EXTEND_PATH, '.class.php');
            $marc_obj = new \MARC();
            $mdata = \MARC::readMarcByStr($book_info["marc"]);
            $marc_obj->setData($mdata);
            $rel_arr = $marc_obj->getRelArray();
            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
            $book_info["firstauthor"] = \BookCalino::getAuthorByMarcObj($rel_arr);
            $calino_obj = new \BookCalino(array("author" => $book_info["firstauthor"], "calino" => $book_info["clc"] . "/", "tsg_code" => $this->_user_info["tsg_code"], "book_id" => $book_id, "calino_has_sep2" => $tsg_info["calino_has_sep2"], "calino_type" => $tsg_info["calino_type"], "mt_id" => $book_info["mt_id"]));
            $calino = $calino_obj->autoCalino();
            $calino = (strpos("/", $calino) != -1 ? $calino : $book_info["clc"] . "/");
            $last_dckprice = $mod_dck->field("price")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND book_id=$book_id ")->order("edit_time desc")->find();

            $select_sql = "select calino from lib_dck left join lib_zch on lib_dck.dck_id=lib_zch.dck_id where lib_dck.tsg_code='{$this->_user_info["tsg_code"]}' AND book_id=$book_id AND calino_type={$tsg_info["calino_type"]} order by edit_time desc limit 1";
            //$last_dckinfo = $mod_dck->join("lib_zch on lib_dck.dck_id=lib_zch.dck_id")->field("calino")->where("lib_dck.tsg_code='{$this->_user_info["tsg_code"]}' AND book_id=$book_id AND calino_type={$tsg_info["calino_type"]}")->order("edit_time desc")->find();
            $last_dckinfo = $mod_dck->query($select_sql);
            $last_dckinfo = $last_dckinfo ? $last_dckinfo[0] : [];
            //print_r($last_dckinfo);exit;

            $dck_price = "";
            if (!empty($last_dckprice)) {
                $dck_price = $last_dckprice["price"];
            } else {
                $field_arr = array("010d", "091d", "011d");

                foreach ($field_arr as $item) {
                    if (isset($rel_arr[$item]) && !empty($rel_arr[$item])) {
                        $dck_price = $rel_arr[$item];
                        $dck_price = $dck_price[0];
                        $dck_price = preg_replace("/[^0-9.]/", "", $dck_price);
                        break;
                    }
                }
            }

            if (!empty($last_dckinfo["calino"])) {
                $book_clc = $last_dckinfo["calino"];
            } else {
                $book_clc = $book_info["clc"];
                $book_clc = strtoupper($book_clc);
                $book_clc .= "/";

                if ($user_info["mzd_calino"]) {
                    $mzd_val_tmp = $marc_obj->getField($user_info["mzd_calino"]);

                    if ($mzd_val_tmp) {
                        $book_clc = $mzd_val_tmp;
                    }
                }
            }

            $this->assign("book_clc", $book_clc);
            $this->assign("dck_price", $dck_price);
        }

        $mod_cost = d("Cost");
        $cost_list = $mod_cost->field("cost_code,cost_sour")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $mod_bookseller = d("Bookseller");
        $bookseller_list = $mod_bookseller->field("seller_code,seller_name")->where("tsg_code='{$this->_user_info["tsg_code"]}'")->select();
        $this->assign("cost_list", $cost_list);
        $this->assign("bookseller_list", $bookseller_list);
        $this->assign("tsg_info", $tsg_info);
        $this->assign("user_info", $user_info);
        $this->assign("book_info", $book_info);
        $this->assign("currency_list", $currency_list);
        $this->assign("jz_list", $jz_list);
        $this->assign("ly_list", $ly_list);
        $this->assign("status_list", $status_list);
        $this->assign("batch_list", $batch_list);
        $this->assign("ltype_list", $ltype_list);
        $this->assign("site_code_list", $site_code_list);
        $mod_mt = d("Mt");
        $mt_info = $mod_mt->field("mt_code")->where("mt_id={$book_info["mt_id"]}")->find();
        $this->assign("mt_info", $mt_info);

    }

}
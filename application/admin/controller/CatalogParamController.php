<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 16:38
 */

namespace app\admin\controller;


use app\admin\model\User;
use app\admin\model\Z3950;

class CatalogParamController extends BaseController
{

    public function indexAction()
    {
        $mod_mt = d("Mt");
        $mod_user = d("User");
        $mod_z3950 = d("Z3950");
        $mod_user_z3950 = d("User_z3950");

        $user_info = $mod_user->where("user_id={$this->_user_info["user_id"]}")->find();
        $mt_list = $mod_mt->get_list();

        $this->assign("default_mt", $user_info["default_mt"]);
        $this->assign("z3950_stype", $user_info["z3950_stype"]);
        $this->assign("bm_del_empty", $user_info["bm_del_empty"]);
        $this->assign("mt_list", $mt_list);

        $z3950_list_tmp = $mod_z3950->get_list($this->_user_info["tsg_code"]);
        $z3950_list = array();
        foreach ($z3950_list_tmp as $item) {
            $z3950_list[$item["z3950_id"]] = $item;
        }

        $stype_list = Z3950::get_search_type_list();
        if (!$this->isPost) {
            $user_z3950_list = $mod_user_z3950->where("user_id=" . $this->_user_info["user_id"])->select();
            $z3950_sel_list = array();
            foreach ($user_z3950_list as $item) {
                $z3950_sel_list[] = $item["z3950_id"];
            }

            foreach ($z3950_list as $key => $item) {
                if (in_array($item["z3950_id"], $z3950_sel_list)) {
                    $z3950_list[$key]["is_checked"] = 1;
                } else {
                    $z3950_list[$key]["is_checked"] = null;
                }
            }

            $this->assign("user_z3950_list", $user_z3950_list);
            $this->assign("z3950_list", $z3950_list);
            $this->assign("stype_list", $stype_list);
            $this->_assign_fields();
            $mod_mt_script = d("Mt_script");
            $mt_script_list = $mod_mt_script->field("mt_script_id,script_name")->where("user_id={$this->_user_info["user_id"]}")->select();
            $this->assign("mt_script_list", $mt_script_list);
            $this->_assign_pinyin_config();
            return view();
        } else {
            $default_mt = input('default_mt/d');
            $z3950_stype = input('z3950_stype/d');
            $z3950_ids = input('z3950_id/a');
            $mod_user_z3950->where("user_id={$this->_user_info["user_id"]}")->delete();

            if ($z3950_ids) {
                foreach ($z3950_ids as $item) {
                    $mod_user_z3950->create(array("user_id" => $this->_user_info["user_id"], "z3950_id" => $item));
                }
            }

            $fields_hide = input('fields_hide/a');
            $field_list = array_keys($this->_get_field_list());

            foreach ($fields_hide as $key => $item) {
                if (!in_array($item, $field_list)) {
                    unset($fields_hide[$key]);
                }
            }

            $fields_hide = implode(",", $fields_hide);
            $rs = User::update(["bm_fields" => $fields_hide, "default_mt" => $default_mt, "z3950_stype" => $z3950_stype], ['user_id' => $this->_user_info["user_id"]])->result;

            $this->_save_pinyin_config();
            $this->success("保存编目参数成功！");
        }
    }

    public function _assign_fields()
    {
        $mod_user = d("User");
        $user_info = $mod_user->field("bm_fields")->where("user_id={$this->_user_info["user_id"]}")->find();
        $field_list = $this->_get_field_list();
        $field_sel_tmp = explode(",", $user_info["bm_fields"]);
        $fields_sel = array();
        $fields_nosel = array();

        foreach ($field_list as $key => $item) {
            if (in_array($key, $field_sel_tmp)) {
                $fields_sel[$key] = $item;
            } else {
                $fields_nosel[$key] = $item;
            }
        }

        $this->assign("fields_sel", $fields_sel);
        $this->assign("fields_nosel", $fields_nosel);
    }

    public function _assign_pinyin_config()
    {
        $mod_user = d("User");
        $user_info = $mod_user->where("user_id={$this->_user_info["user_id"]}")->find();
        $this->assign("user_info", $user_info);
    }

    public function _get_field_list()
    {
        return array("bl_title" => l("bl_title"), "doctype" => l("doctype"), "othertitle" => l("othertitle"), "fjtitle" => l("fjtitle"), "fjno" => l("fjno"), "otherauthor" => l("otherauthor"), "publisher" => l("publisher"), "pubplace" => l("pubplace"), "pubdate" => l("pubdate"), "series" => l("series"), "seriesauthor" => l("seriesauthor"), "edition" => l("edition"), "accessories" => l("accessories"), "charts" => l("charts"), "binding" => l("binding"), "lags" => l("lags"), "size" => l("size"), "subject" => l("subject"), "gennotes" => l("gennotes"), "clc" => l("clc"), "abstract" => l("abstract"), "price_ms" => l("price_ms"));
    }

    public function _save_pinyin_config()
    {
        $save_data = array("pinyin_config" => input('pinyin_config'), "pinyin_dx" => input('pinyin_dx'), "marc_type" => input('marc_type'), "bm_jd_marc" => input('bm_jd_marc'), "limit_clc_len" => input('limit_clc_len/d'), "bm_mscript" => input('bm_mscript/d'), "mzd_calino" => input('mzd_calino', ''), "bm_del_empty" => input('bm_del_empty/d'));
        $pinyin_config = User::update($save_data, ['user_id' => $this->_user_info["user_id"]]);
    }

}
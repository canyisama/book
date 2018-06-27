<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/7
 * Time: 14:55
 */

namespace app\admin\controller;


use app\admin\model\Mt;
use app\admin\model\User;
use app\admin\model\UserZ3950;
use app\admin\model\Z3950;
use think\Db;
use think\Exception;

/**
 * Class QkParamController
 * @package app\admin\controller
 * 期刊通用参数
 */
class QkParamController extends BaseController
{

    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 期刊通用参数配置
     */
    public function indexAction(){
        $user_info = User::field("qk_default_mt,z3950_stype")->where(['user_id'=>$this->adminInfo['user_id']])->find();

        $mt_list = Mt::get_list();

        $z3950_list_tmp = Z3950::get_list($this->_user_info["tsg_code"]);

        $z3950_list = array_under_reset($z3950_list_tmp, 'z3950_id');

        $stype_list = Z3950::get_search_type_list();

        if (!$this->isPost) {
            $user_z3950_list = UserZ3950::all(['user_id'=>$this->adminInfo['user_id']]);
            $z3950_sel_list = array();

            foreach ($user_z3950_list as $item ) {
                $z3950_sel_list[] = $item["z3950_id"];
            }

            foreach ($z3950_list as $key => $item ) {
                if (in_array($item["z3950_id"], $z3950_sel_list)) {
                    $z3950_list[$key]["is_checked"] = 1;
                }else{
                    $z3950_list[$key]["is_checked"] = 0;
                }
            }

            $this->assign("qk_default_mt", $user_info["qk_default_mt"]);
            $this->assign("z3950_stype", $user_info["z3950_stype"]);
            $this->assign("mt_list", $mt_list);

            $this->assign("user_z3950_list", $user_z3950_list);
            $this->assign("z3950_list", $z3950_list);
            $this->assign("stype_list", $stype_list);

            $this->_assign_fields();
            $this->_assign_pinyin_config();
            return view();
        }
        try {
            $qk_default_mt = input('qk_default_mt/d') ?: 0;
            $z3950_stype = input('z3950_stype/d') ?: 0;
            $z3950_ids = input("z3950_id/a")?:[];
//            $z3950_ids = $this->request->param();

            Db::startTrans();
            $is_success = UserZ3950::where(['user_id'=>$this->adminInfo['user_id']])->delete();
            if ($is_success === false){
                Db::rollback();
                $this->error('清空用户z3950服务器时错误，请稍后再试');
            }

            foreach ($z3950_ids as $item ) {
                $is_success = UserZ3950::create(['user_id'=>$this->adminInfo['user_id'],'z3950_id'=>$item],true)->result;
                if ($is_success === false){
                    Db::rollback();
                    $this->error('新增用户z3950服务器时错误，请稍后再试');
                }
            }

            $fields_hide = input("fields_hide/a")?:[];
            $field_list = array_keys($this->_get_field_list());

            foreach ($fields_hide as $key => $item ) {
                if (!in_array($item, $field_list)) {
                    unset($fields_hide[$key]);
                }
            }

            $fields_hide = implode(",", $fields_hide);
            $save_data = [
                "bm_fields" => $fields_hide,
                "qk_default_mt" => $qk_default_mt,
                "z3950_stype" => $z3950_stype
            ];
            $is_success = User::update($save_data,['user_id'=>$this->adminInfo['user_id']],true)->result;
//            $mod_user->field("bm_fields")->where("user_id={$this->_user_info["user_id"]}")->save(array());
            if ($is_success === false){
                Db::rollback();
                $this->error('更新用户编目字段失败，请稍后再试');
            }

            $is_success = $this->_save_pinyin_config();
            if ($is_success === false){
                Db::rollback();
                $this->error('更新用户拼音配置失败，请稍后再试');
            }
            Db::commit();
            $this->success("保存编目参数成功！");
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 初始化编目字段
     */
    public function _assign_fields()
    {
        $user_info = User::field("bm_fields")->where(['user_id'=>$this->adminInfo['user_id']])->find();
        $field_list = $this->_get_field_list();
        $field_sel_tmp = explode(",", $user_info["bm_fields"]);
        $fields_sel = array();
        $fields_nosel = array();

        foreach ($field_list as $key => $item ) {
            if (in_array($key, $field_sel_tmp)) {
                $fields_sel[$key] = $item;
            }
            else {
                $fields_nosel[$key] = $item;
            }
        }

        $this->assign("fields_sel", $fields_sel);
        $this->assign("fields_nosel", $fields_nosel);
    }

    public function _get_field_list()
    {
        return array("bl_title" => lang("bl_title"), "doctype" => l("doctype"), "othertitle" => l("othertitle"), "fjtitle" => l("fjtitle"), "fjno" => l("fjno"), "otherauthor" => l("otherauthor"), "publisher" => l("publisher"), "pubplace" => l("pubplace"), "pubdate" => l("pubdate"), "series" => l("series"), "seriesauthor" => l("seriesauthor"), "edition" => l("edition"), "accessories" => l("accessories"), "charts" => l("charts"), "binding" => l("binding"), "lags" => l("lags"), "size" => l("size"), "subject" => l("subject"), "gennotes" => l("gennotes"), "clc" => l("clc"), "abstract" => l("abstract"), "price_ms" => l("price_ms"));
    }

    /**
     * @throws \think\exception\DbException
     */
    public function _assign_pinyin_config()
    {
        $user_info = User::get($this->adminInfo['user_id']);
        $this->assign("user_info", $user_info);
    }

    /**
     * @return null
     *
     */
    public function _save_pinyin_config()
    {
        $save_data = array(
            "pinyin_config" => input("post.pinyin_config"),
            "pinyin_dx" => input("post.pinyin_dx/d"),
//            "marc_type" => input("post.marc_type"),
            "bm_jd_marc" => input("post.bm_jd_marc")?:1,
            "limit_clc_len" => input('limit_clc_len/d') ?: 0);

        $pinyin_config = User::update($save_data,['user_id'=>$this->adminInfo['user_id']])->result;
        return $pinyin_config;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/16
 * Time: 11:43
 */

namespace app\admin\controller;

use app\admin\model\Doctype;
use app\admin\model\MarcField;
use app\admin\model\MarcTpl;
use app\admin\model\Mt;
use app\admin\model\SysLog;
use app\admin\model\User;
use think\Exception;
use think\Lang;

class MarctypeController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/marctype.php']);
    }

    /**
     * MARC类型
     */
    public function indexAction()
    {
        return view();
    }

    /**
     * 异步获取列表
     */
    public function getJsonListAction()
    {
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Mt::getPageList($condition, $params->limit, $params->order);
        $count = Mt::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    /**
     * 新增/编辑
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editAction()
    {
        $mt_id = input('mt_id');
        $mt_id && ($info = Mt::get(['mt_id' => $mt_id]));
        if ($this->isPost) {
            $data = [];
            $data['mt_code'] = input('mt_code');
            $data['doctype_id'] = input('doctype_id');
            $data['remark'] = input('remark');
            $data['sort_num'] = input('sort_num');

            if (!Mt::unique($data["mt_code"], $mt_id)) {
                $this->error(l("mt_code_exist"));
            }

            if ($mt_id) {
                $result = Mt::update($data, ['mt_id' => $mt_id])->result;
                $log_type = SysLog::OP_TYPE_MT_SAVE;
            } else {
                $result = Mt::create($data)->result;
                $log_type = SysLog::OP_TYPE_MT_ADD;
            }
            if ($result !== false) {
                SysLog::addLog($log_type, $this->adminInfo, ["db1" => $mt_id, "op_desc" => "[#],MARC类型名称:{$data["mt_code"]}"]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $dt_list = Doctype::select();
        $this->assign('info', $info);
        $this->assign("dt_list", $dt_list);
        return view('edit');
    }

    public function dropAction()
    {
        $mt_id = input('mt_id');
        $mod_mt = d("Mt");
        $mt_info = $mod_mt->where(['mt_id' => $mt_id])->find();

        if (!$mt_info) {
            $this->error(lang("not_found_data"));
        }

        $mod_book = d("Book");
        $book_info = $mod_book->field("book_id")->where(['mt_id' => $mt_id])->find();

        if (!empty($book_info)) {
            $this->error("当前MARC类型存在书目数据,无法删除!");
        }

        try {
            $mod_mt->startTrans();
            $is_success = $mod_mt->where(['mt_id' => $mt_id])->delete();

            if ($is_success === false) {
                $mod_mt->rollback();
                $this->error("删除失败！错误提示:" . $mod_mt->getError());
            }

            $mod_indexrel_init = d("Indexrel_init");
            $mod_indexrel = d("Indexrel");
            $is_success = $mod_indexrel->where(['mt_id' => $mt_id])->delete();

            if ($is_success === false) {
                $mod_mt->rollback();
                $this->error("新增失败！错误提示:删除索引配置失败");
            }

            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_MT_DROP, $this->adminInfo, array("db1" => $mt_id, "op_desc" => "[#],MARC类型名称:{$mt_info["mt_code"]}"));
            $mod_mt->commit();
            $this->success("删除成功！");
        } catch (Exception $e) {
            $mod_mt->rollback();
            $this->ajaxReturn("", "新增失败！错误提示:程序处理时出现异常");
        }
    }

    /**
     * MARC字段配置
     * @return \think\response\View
     */
    public function fieldcnfAction()
    {
        $mod_mt = d("Mt");
        $mt_list = $mod_mt->get_list();
        $this->assign('mt_list', $mt_list);
        return view();
    }

    /**
     * MARC字段配置列表
     */
    public function fieldcnf_listAction()
    {
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition['field.' . $search['field']] = $search['value'];
            }
        }

        $fields = "marc_field_id,field.mt_id,zd_name,zd_text,zd_text_jd,zsf1,zsf2,zzd_bs,is_union,is_must,is_repeat,is_del,is_edit,len,zd_desc,mt_code";
        $list = MarcField::alias('field')
            ->join('lib_mt mt', 'field.mt_id=mt.mt_id')
            ->fetchSql(false)
            ->fieldRaw($fields)
            ->where($condition)
            ->order($params->order)
            ->select();
        $count = MarcField::alias('field')->join('lib_mt mt', 'field.mt_id=mt.mt_id')->where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * 新增MARC字段配置
     * @return \think\response\View
     * @throws \think\exception\DbException
     */
    public function add_fieldAction()
    {
        return $this->edit_fieldAction();
    }

    /**
     * 编辑MARC字段配置
     * @return \think\response\View
     * @throws \think\exception\DbException
     */
    public function edit_fieldAction()
    {
        $marc_field_id = input('marc_field_id/d');
        $mod_marc_field = d("Marc_field");
        $field_info = $mod_marc_field->where("marc_field_id=$marc_field_id")->find();

        if ($this->isPost) {
            $save_data = array(
                'mt_id' => input('mt_id'),
                'zd_name' => input('zd_name'),
                'zd_text' => input('zd_text'),
                'zd_text_jd' => input('zd_text_jd'),
                'zzd_bs' => input('zzd_bs'),
                'zsf1' => input('zsf1'),
                'zsf2' => input('zsf2'),
                'is_union' => input('is_union'),
                'is_must' => input('is_must'),
                'is_repeat' => input('is_repeat'),
                'is_del' => input('is_del'),
                'is_edit' => input('is_edit'),
                'len' => input('len'),
                'zd_desc' => input('zd_desc'),
            );

            if (!$save_data["zd_name"]) {
                $this->error(l("zd_name_required"));
            }
            if (!MarcField::unique($save_data["zd_name"], $save_data["mt_id"], $marc_field_id)) {
                $this->error(l("zd_name_exist"));
            }
            if ($marc_field_id) {
                $result = MarcField::update($save_data, ['marc_field_id' => $marc_field_id])->result;
            } else {
                $result = MarcField::create($save_data)->result;
            }

            if ($result !== false) {
                SysLog::addLog($marc_field_id ? SysLog::OP_TYPE_MT_ZD_SAVE : SysLog::OP_TYPE_MT_ZD_ADD, $this->_user_info,
                    array("db1" => $marc_field_id, "op_desc" => "[#],字段标识符:{$field_info["zd_name"]},字段含义简称:{$field_info["zd_text_jd"]}"));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }

        $this->assign("field_info", $field_info);
        $this->assign('mt_info', Mt::get(input('mt_id/d')));
        return view('field_edit');
    }

    public function drop_fieldAction()
    {
        $marc_field_id = input('marc_field_id/d');
        $mod_marc_field = d("Marc_field");
        $mt_info = $mod_marc_field->where("marc_field_id=$marc_field_id")->find();

        if (!$mt_info) {
            $this->error(l("not_found_data"));
        }
        $is_success = $mod_marc_field->where("marc_field_id=$marc_field_id")->delete();
        if ($is_success) {
            SysLog::addlog(SysLog::OP_TYPE_MT_ZD_DROP, $this->_user_info, array("db1" => $marc_field_id, "op_desc" => "[#],字段标识符:{$mt_info["zd_name"]},字段含义简称:{$mt_info["zd_text_jd"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败");
        }
    }

    /**
     * MARC索引配置
     * @return \think\response\View
     */
    public function indexsetAction()
    {
        $mod_mt = d("Mt");
        $mod_indexrel = d("Indexrel");
        $mt_id = input('mt_id/d');

        if ($this->isPost) {
            $save_data = array("title" => isset($_POST["title"]) ? trim($_POST["title"]) : "", "isbn" => isset($_POST["isbn"]) ? trim($_POST["isbn"]) : "", "subject" => isset($_POST["subject"]) ? trim($_POST["subject"]) : "", "clc" => isset($_POST["clc"]) ? trim($_POST["clc"]) : "", "publisher" => isset($_POST["publisher"]) ? trim($_POST["publisher"]) : "", "firstauthor" => isset($_POST["firstauthor"]) ? trim($_POST["firstauthor"]) : "");
            $mt_id_save = input('mt_id/d');

            try {
                $mod_indexrel->startTrans();

                foreach ($save_data as $key => $item) {
                    $is_success = $mod_indexrel->update(['sour_mfield' => $item], ['mt_id' => $mt_id_save, 'sour_field' => $key])->result;
                    if ($is_success === false) {
                        $mod_indexrel->rollback();
                        $this->error("保存失败！错误提示:" . $mod_indexrel->getError());
                    }
                }

                SysLog::addlog(SysLog::OP_TYPE_MT_INDEX_SAVE, $this->_user_info, array("db1" => "无"));
                $mod_indexrel->commit();
                $this->success("保存参数成功!");
            } catch (Exception $e) {
                $mod_indexrel->rollback();
                $this->error("保存失败！程序处理出现异常!");
            }
        }


        $mt_list = $mod_mt->get_list();
        $this->assign("mt_list", $mt_list);
        if (!empty($mt_list) && empty($mt_id)) {
            $mt_id = $mt_list[0]["mt_id"];
        }

        if (!empty($mt_id)) {
            $cnf_list_tmp = $mod_indexrel->field("sour_field,sour_mfield")->where("mt_id=$mt_id")->select();
            $cnf_list = array();

            foreach ($cnf_list_tmp as $key => $item) {
                $cnf_list[$item["sour_field"]] = $item["sour_mfield"];
            }
            $this->assign("cnf_list", $cnf_list);
        }
        $this->assign('mt_id', $mt_id);
        return view();
    }

    /**
     * MARC映射条目
     * @return \think\response\View
     */
    public function configAction()
    {
        $mod_mt = d("Mt");
        $mt_id = input('mt_id/d');

        if ($this->isPost) {
            $catalog_cnf = loaddata("catalog");
            $marc_mapper_default = $catalog_cnf["marc_mapper_default"];
            $save_data_tmp = array();

            foreach ($marc_mapper_default as $key => $item) {
                $save_data_tmp[$key] = (isset($_POST[$key]) ? trim($_POST[$key]) : "");
            }

            $save_data = array("mapper" => serialize($save_data_tmp));
            $mt_id_save = input('mt_id/d');
            $is_success = $mod_mt->update($save_data, ['mt_id' => $mt_id_save])->result;

            if ($is_success !== false) {
                SysLog::addlog(SysLog::OP_TYPE_MT_MAP_SAVE, $this->_user_info, array("db1" => "无"));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }

        $mt_list = $mod_mt->get_list();
        $this->assign("mt_list", $mt_list);
        if (!empty($mt_list) && empty($mt_id)) {
            $mt_id = $mt_list[0]["mt_id"];
        }

        if (!empty($mt_id)) {
            $mt_info = $mod_mt->field("mapper")->where("mt_id=$mt_id")->find();
            $mapper_info = unserialize($mt_info["mapper"]);
            $this->assign("mapper_info", $mapper_info);
        }

        return view();
    }

    public function tplAction()
    {

        $mod_mt = new Mt();
        $mt_list = $mod_mt->get_list();
        $this->assign("mt_list", $mt_list);

        $mod_user = new User();
        $default_tpl = $mod_user->get_tpl_list($this->_user_info["user_id"]);

        $this->assign("default_tpl", json_encode($default_tpl));

        return view();
    }

    public function tpl_listAction()
    {
        $condition = [];
        $param = $this->getQueryParams();
        if ($param->search){
            foreach ($param->search as $search){
                if ($search['field'] == 'mt_id'){
                    $condition['lib_marc_tpl.mt_id'] = $search['value'];
                }else{
                    $condition[$search['field']] = ['like','%'.$search['value'].'%'];
                }
            }
        }
        $fields = "marc_tpl_id,lib_marc_tpl.mt_id,tpl_name,mt_code";
        $join = [['lib_mt','lib_marc_tpl.mt_id=lib_mt.mt_id']];
        $list = MarcTpl::join($join)->field($fields)->where($condition)->limit($param->limlt)->order($param->order)->select();
        $count = MarcTpl::join($join)->where($condition)->count();
        return $this->echoPageData($list,$count);
    }


    /**
     * @return \think\response\View
     * @throws \think\exception\DbException
     * Marc模板添加
     */
    public function add_tplAction()
    {
        import('Marc/MARC', EXTEND_PATH, '.class.php');
        if (!$this->isPost) {
            $mt_id = input('mt_id/d',0);
            $mt_info = Mt::get($mt_id);
            $this->assign("mt_info", $mt_info);

            if (empty($mt_info)) {
                $this->alertMsg('错误的MARC类型,无法增加模板');
            }

            $catalog_cnf = loaddata("catalog");
            $tpl = $catalog_cnf["marc_template_default"];
            $tpl_info["tpl"] = $this->decode_tpl($tpl);
            $this->assign("tpl_info", $tpl_info);
            return view('tpl_form');
        } else {

            $add_data = $this->request->post();

            if (!$add_data["tpl_name"]) {
                $this->error(l('tpl_name_required'));
            }


            if (!$add_data["mt_id"]) {
                $this->error(l('mt_id_required'));
            }
            if (!MarcTpl::unique($add_data["tpl_name"])) {
                $this->error(l("tpl_name_exist"));
            }

            $add_data["tpl"] = $this->encode_tpl($_POST["tpl"]);
            $is_success = MarcTpl::create($add_data,true)->result;

            if ($is_success !== false) {
                SysLog::addlog(SysLog::OP_TYPE_MT_TPL_ADD, $this->_user_info, array("db1" => $marc_tpl_id, "op_desc" => "[#],模板名称:{$add_data["tpl_name"]}"));
                $this->success('添加成功');
            } else {
                $this->error('添加失败:插入数据库失败');
            }
        }
    }

    public function edit_tplAction()
    {
        $marc_tpl_id = input('marc_tpl_id/d',0);
        import('Marc/MARC', EXTEND_PATH, '.class.php');
        $tpl_info = MarcTpl::get($marc_tpl_id);

        if (!$this->isPost) {
            $mt_id = $tpl_info["mt_id"];
            $mt_info = Mt::get($mt_id);
            $this->assign("mt_info", $mt_info);
            $tpl_info["tpl"] = $this->decode_tpl(unserialize($tpl_info["tpl"]));
            $this->assign("tpl_info", $tpl_info);
            return view("tpl_form");
        } else {
            $save_data = $this->request->post();
            if (!$save_data["tpl_name"]) {
                $this->error(l('tpl_name_required'));
            }

            if (!MarcTpl::unique($save_data["tpl_name"], $marc_tpl_id)) {
                $this->error(l("tpl_name_exist"));
            }

            unset($save_data["mt_id"]);
            $save_data["tpl"] = $this->encode_tpl($save_data["tpl"]);
            $is_success  = MarcTpl::update($save_data,['marc_tpl_id'=>$marc_tpl_id],true)->result;

            if ($is_success !== false) {
                SysLog::addlog(SysLog::OP_TYPE_MT_TPL_SAVE, $this->_user_info, array("db1" => $marc_tpl_id, "op_desc" => "[#],模板名称:{$tpl_info["tpl_name"]}"));
                $this->success('保存成功');
            } else {
                $this->error('保存失败！');
            }
        }
    }

    public function encode_tpl($tpl)
    {
        if (empty($tpl)) {
            return "";
        }

        $rows = explode(chr(10), $tpl);
        $tem_arr = array(
            "head" => "",
            "fields" => array()
        );

        foreach ($rows as $item) {
            $zd_name = mb_substr($item, 0, 3, "utf-8");
            $zd_zsf = mb_substr($item, 4, 2, "utf-8");

            if ($zd_zsf == "__") {
                $zd_zsf = "";
            } else {
                $zd_zsf = str_replace("_", " ", $zd_zsf);
            }

            $zd_val = mb_substr($item, 7, mb_strlen($item, "UTF-8") - 7, "utf-8");

            if ($zd_name == "000") {
                $tem_arr["head"] = $zd_val;
            } else if ($zd_name < "010") {
                $tem_arr["fields"][] = array("name" => $zd_name, "zsf" => $zd_zsf, "val" => $zd_val);
            } else {
                $szd_list = array();
                $szd_rows = explode("§", $zd_val);

                foreach ($szd_rows as $item1) {
                    if (!empty($item1)) {
                        $szd_name = mb_substr($item1, 0, 1, "utf-8");
                        $szd_val = mb_substr($item1, 1, mb_strlen($item1, "UTF-8") - 1, "utf-8");
                        $szd_list[] = array("name" => $szd_name, "val" => $szd_val);
                    }
                }

                $tem_arr["fields"][] = array("name" => $zd_name, "zsf" => $zd_zsf, "val" => $szd_list);
            }
        }

        return serialize($tem_arr);
    }

    /**
     * @param $tpl
     * @return string
     * 模板修改
     */
    public function decode_tpl($tpl)
    {
        if (empty($tpl) || !is_array($tpl)) {
            return "";
        }

        $str_rows = array();

        if (isset($tpl["head"])) {
            $str_rows[] = "000    {$tpl["head"]}";
        }

        foreach ($tpl["fields"] as $item) {
            $zd_name = $item["name"];
            $zd_zsf = (!empty($item["zsf"]) ? str_replace(" ", "_", $item["zsf"]) : "__");
            $zd_val = $item["val"];

            if ($zd_name < "010") {
                $str_rows[] = "$zd_name $zd_zsf $zd_val";
            } else {
                $szd_rows = array();

                foreach ($zd_val as $item1) {
                    $szd_name = $item1["name"];
                    $szd_val = $item1["val"];
                    $szd_rows[] = "§$szd_name$szd_val";
                }

                $szd_str = implode("", $szd_rows);
                $str_rows[] = "$zd_name $zd_zsf $szd_str";
            }
        }

        $tpl_str = implode("\r\n", $str_rows);
        return $tpl_str;
    }

    /**
     * @throws \think\exception\DbException
     * 模板删除
     */
    public function drop_tplAction()
    {
        $marc_tpl_id = input('marc_tpl_id/d',0);
        $tpl_info = MarcTpl::get($marc_tpl_id);

        if (!$tpl_info) {
            $this->error(l('not_found_data'));
        }

        $is_success = $tpl_info->delete();

        if ($is_success) {
            SysLog::addlog(SysLog::OP_TYPE_MT_TPL_DROP, $this->_user_info, array("db1" => $marc_tpl_id, "op_desc" => "[#],模板名称:{$tpl_info["tpl_name"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败!");
        }
    }

    public function set_default_tplAction()
    {
        $mt_id = input('mt_id/d',0);
        $marc_tpl_id = input('marc_tpl_id/d',0);
        $mod_user = new User();
        $tpl_info = $mod_user->get_tpl_list($this->_user_info["user_id"]);
        $tpl_info[$mt_id] = $marc_tpl_id;
        $save_data["default_tpl"] = serialize($tpl_info);

        $is_success = User::update($save_data,['user_id'=>$this->adminInfo['user_id']])->result;
        if ($is_success) {
            $this->success("设置默认模板成功！");
        } else {
            $this->error("设置默认模板失败！错误提示:保存数据失败!");
        }
    }

    public function index_rehabAction()
    {
        $mod_book = d("Book");
        $mod_title = d("Index_title");
        $mod_title_order = d("Index_title_order");
        $mod_isbn = d("Index_isbn");
        $mod_isbn_order = d("Index_isbn_order");
        $mod_subject = d("Index_subject");
        $mod_subject_order = d("Index_subject_order");
        $mod_clc = d("Index_clc");
        $mod_clc_order = d("Index_clc_order");
        $mod_publisher = d("Index_publisher");
        $mod_publisher_order = d("Index_publisher_order");
        $mod_firstauthor = d("Index_firstauthor");
        $mod_firstauthor_order = d("Index_firstauthor_order");
        $mod_tsg_code = d("Index_tsg_code");
        $mod_pubdate = d("Index_pubdate");
        $mod_cataloger = d("Index_cataloger");
        $mod_catatime = d("Index_catatime");
        $book_cnt = $mod_book->count("0");
        $book_sum = array("书目库" => $book_cnt, "题名" => $mod_title->count("0"), "题名(排序)" => $mod_title_order->count("0"), "标准编号" => $mod_isbn->count("0"), "标准编号(排序)" => $mod_isbn_order->count("0"), "主题词" => $mod_subject->count("0"), "主题词(排序)" => $mod_subject_order->count("0"), "分类号" => $mod_clc->count("0"), "分类号(排序)" => $mod_clc_order->count("0"), "出版社" => $mod_publisher->count("0"), "出版社(排序)" => $mod_publisher_order->count("0"), "第一责任者" => $mod_firstauthor->count("0"), "第一责任者(排序)" => $mod_firstauthor_order->count("0"), "分馆代码" => $mod_tsg_code->count("0"), "出版日期" => $mod_pubdate->count("0"), "编目人" => $mod_cataloger->count("0"), "编目日期" => $mod_catatime->count("0"));
        $this->assign("book_cnt", $book_cnt);
        $this->assign("book_sum", $book_sum);
        $mod_book_share = d("Book_share");
        $mod_title_s = d("Index_title_s");
        $mod_title_order_s = d("Index_title_order_s");
        $mod_isbn_s = d("Index_isbn_s");
        $mod_isbn_order_s = d("Index_isbn_order_s");
        $mod_subject_s = d("Index_subject_s");
        $mod_subject_order_s = d("Index_subject_order_s");
        $mod_clc_s = d("Index_clc_s");
        $mod_clc_order_s = d("Index_clc_order_s");
        $mod_publisher_s = d("Index_publisher_s");
        $mod_publisher_order_s = d("Index_publisher_order_s");
        $mod_firstauthor_s = d("Index_firstauthor_s");
        $mod_firstauthor_order_s = d("Index_firstauthor_order_s");
        $mod_tsg_code_s = d("Index_tsg_code_s");
        $mod_pubdate_s = d("Index_pubdate_s");
        $mod_op_user_s = d("Index_op_user_s");
        $mod_add_time_s = d("Index_add_time_s");
        $book_share_cnt = $mod_book_share->count("0");
        $book_sum_share = array("套录库" => $book_share_cnt, "题名" => $mod_title_s->count("0"), "题名(排序)" => $mod_title_order_s->count("0"), "标准编号" => $mod_isbn_s->count("0"), "标准编号(排序)" => $mod_isbn_order_s->count("0"), "主题词" => $mod_subject_s->count("0"), "主题词(排序)" => $mod_subject_order_s->count("0"), "分类号" => $mod_clc_s->count("0"), "分类号(排序)" => $mod_clc_order_s->count("0"), "出版社" => $mod_publisher_s->count("0"), "出版社(排序)" => $mod_publisher_order_s->count("0"), "第一责任者" => $mod_firstauthor_s->count("0"), "第一责任者(排序)" => $mod_firstauthor_order_s->count("0"), "分馆代码" => $mod_tsg_code_s->count("0"), "出版日期" => $mod_pubdate_s->count("0"), "入库人" => $mod_op_user_s->count("0"), "入库时间" => $mod_add_time_s->count("0"));
        $this->assign("book_share_cnt", $book_share_cnt);
        $this->assign("book_sum_share", $book_sum_share);
//        var_dump($book_sum_share);exit();
        return view();
    }

    public function book_init()
    {
        import("@.Extend.AppLock.AppLock");

        if (AppLock::isLock("BookIndexRebuild")) {
            $this->ajaxReturn("", "系统正在重建书目库索引,不能重复操作", 0);
            return false;
        }

        import("@.Extend.IndexRepair.IndexRepair");

        if (IndexRepair::isRepair("lib_book")) {
            $this->ajaxReturn("", "系统正在修复书目库索引,无法重建索引", 0);
            return false;
        }

        AppLock::lock("BookIndexRebuild");
        import("@.Extend.TaskStatus.TaskStatus");
        TaskStatus::initTaskValue(self::IMPORT_TASK_NAME);
        $mod_book = d("Book");
        $book_cnt = $mod_book->count();
        $mod_mt = d("Mt");
        $mt_list = $mod_mt->get_list();
        $index_list_map = array();

        foreach ($mt_list as $item) {
            $index_list_map[$item["mt_id"]] = BookModel::get_indexrel($item["mt_id"], array("fileds" => "sour_field,dest_mod,order_mod,sour_mfield,dest_table,order_table"));
        }

        $index_list_first = current($index_list_map);

        foreach ($index_list_first as $item) {
            $mod_book->query("TRUNCATE TABLE {$item["dest_table"]}");

            if ($item["order_table"] != $item["dest_table"]) {
                $mod_book->query("TRUNCATE TABLE {$item["order_table"]}");
            }
        }

        $task_per = (1 / $book_cnt) * 100;
        $task_cnt = 0;
        $index_data_cache = array();
        $index_mod = array();
        import("@.Marc.MARC");
        $marc_obj = new MARC("");
        $i_mod = 0;

        for ($i = 0; $i < $book_cnt; $i += 10000) {
            $book_list = $mod_book->limit("$i,10000")->select();

            foreach ($book_list as $data) {
                $id = $data["book_id"];
                $mdata = MARC::readMarcByStr($data["marc"]);
                $marc_obj->setData($mdata);
                $rel_arr = $marc_obj->getRelArray();
                $index_list = (isset($index_list_map[$data["mt_id"]]) ? $index_list_map[$data["mt_id"]] : current($index_list_map));
                $index_add_data = BookModel::conv_index_data($id, $data, $rel_arr, $index_list);
                $mod_obj = new Model();
                $i_mod++;

                foreach ($index_add_data as $key => $item) {
                    $index_data_cache[$key][] = $item;
                }

                if (($i_mod % 100) == 0) {
                    foreach ($index_data_cache as $key1 => $item1) {
                        if (!$index_mod[$key1]) {
                            $index_mod[$key1] = d($key1);
                        }

                        $is_success = $index_mod[$key1]->addall($item1);

                        if ($is_success === false) {
                            Log::write("书目库重建索引错误  " . $index_mod[$key1]->db()->getError());

                            return false;
                        }
                    }

                    $index_data_cache = array();
                }

                $task_cnt += $task_per;
                TaskStatus::setTaskValue(self::IMPORT_TASK_NAME, $task_cnt);
            }
        }

        if ($index_data_cache) {
            foreach ($index_data_cache as $key1 => $item1) {
                if (!$index_mod[$key1]) {
                    $index_mod[$key1] = d($key1);
                }

                $is_success = $index_mod[$key1]->addall($item1);

                if ($is_success === false) {
                    Log::write("书目库重建索引错误  " . $index_mod[$key1]->db()->getError());

                    return false;
                }
            }
        }

        $this->ajaxReturn("", "索引重建成功", 1);
    }

    public function book_share_init()
    {
        import("@.Extend.AppLock.AppLock");

        if (AppLock::isLock("BookShareIndexRebuild")) {
            $this->ajaxReturn("", "系统正在重建套录库索引,不能重复操作", 0);
            return false;
        }

        import("@.Extend.IndexRepair.IndexRepair");

        if (IndexRepair::isRepair("lib_book_share")) {
            $this->ajaxReturn("", "系统正在修复套录库索引,无法重建索引", 0);
            return false;
        }

        AppLock::lock("BookShareIndexRebuild");
        import("@.Extend.TaskStatus.TaskStatus");
        TaskStatus::initTaskValue(self::IMPORT_TASK_NAME1);
        $mod_book_share = d("Book_share");
        $mod_book = d("Book");
        $book_cnt = $mod_book_share->count();
        $mod_mt = d("Mt");
        $mt_list = $mod_mt->get_list();
        $index_list_map = array();

        foreach ($mt_list as $item) {
            $index_list_map[$item["mt_id"]] = BookModel::get_indexrel($item["mt_id"], array("sour_mod" => "Book_share", "fileds" => "sour_field,dest_mod,order_mod,sour_mfield,dest_table,order_table"));
        }

        $index_list_first = current($index_list_map);

        foreach ($index_list_first as $item) {
            $mod_book_share->query("TRUNCATE TABLE {$item["dest_table"]}");

            if ($item["order_table"] != $item["dest_table"]) {
                $mod_book_share->query("TRUNCATE TABLE {$item["order_table"]}");
            }
        }

        $task_per = (1 / $book_cnt) * 100;
        $task_cnt = 0;
        import("@.Marc.MARC");
        $marc_obj = new MARC("");
        $index_data_cache = array();
        $index_mod = array();
        $i_mod = 0;

        for ($i = 0; $i < $book_cnt; $i += 10000) {
            $book_list = $mod_book_share->limit("$i,10000")->select();

            foreach ($book_list as $data) {
                $id = $data["book_share_id"];
                $mdata = MARC::readMarcByStr($data["marc"]);
                $marc_obj->setData($mdata);
                $rel_arr = $marc_obj->getRelArray();
                $index_list = (isset($index_list_map[$data["mt_id"]]) ? $index_list_map[$data["mt_id"]] : current($index_list_map));
                $index_add_data = Book_shareModel::conv_index_data($id, $data, $rel_arr, $index_list);
                $mod_obj = new Model();
                $i_mod++;

                foreach ($index_add_data as $key => $item) {
                    $index_data_cache[$key][] = $item;
                }

                if (($i_mod % 100) == 0) {
                    foreach ($index_data_cache as $key1 => $item1) {
                        if (!$index_mod[$key1]) {
                            $index_mod[$key1] = d($key1);
                        }

                        $is_success = $index_mod[$key1]->addall($item1);

                        if ($is_success === false) {
                            Log::write("套录库重建索引错误  " . $index_mod[$key1]->db()->getError());

                            return false;
                        }
                    }

                    $index_data_cache = array();
                }

                $task_cnt += $task_per;
                TaskStatus::setTaskValue(self::IMPORT_TASK_NAME1, $task_cnt);
            }
        }

        if ($index_data_cache) {
            foreach ($index_data_cache as $key1 => $item1) {
                if (!$index_mod[$key1]) {
                    $index_mod[$key1] = d($key1);
                }

                $is_success = $index_mod[$key1]->addall($item1);

                if ($is_success === false) {
                    Log::write("套录库重建索引错误  " . $index_mod[$key1]->db()->getError());

                    return false;
                }
            }
        }

        $this->ajaxReturn("", "索引重建成功", 1);
    }

    public function get_status()
    {
        import("@.Extend.TaskStatus.TaskStatus");
        $type = $this->_get("type");
        $val = TaskStatus::getTaskValue(self::IMPORT_TASK_NAME);
        $val = ($val === false ? 0 : $val);
        $this->ajaxReturn($val, "success", 1);
    }

    public function get_status1()
    {
        import("@.Extend.TaskStatus.TaskStatus");
        $type = $this->_get("type");
        $val = TaskStatus::getTaskValue(self::IMPORT_TASK_NAME1);
        $val = ($val === false ? 0 : $val);
        $this->ajaxReturn($val, "success", 1);
    }

    public function book_repair()
    {
        import("@.Extend.IndexRepair.IndexRepair");
        $is_success = IndexRepair::repair("lib_book", "marctype_book_repair");

        if ($is_success === false) {
            $this->ajaxReturn("", "修复错误:" . IndexRepair::getError(), 0);
            return NULL;
        }

        $this->ajaxReturn("", "书目库索引修复成功", 1);
    }

    public function book_share_repair()
    {
        import("@.Extend.IndexRepair.IndexRepair");
        $is_success = IndexRepair::repair("lib_book_share", "marctype_book_share_repair");

        if ($is_success === false) {
            $this->ajaxReturn("", "修复错误:" . IndexRepair::getError(), 0);
            return NULL;
        }

        $this->ajaxReturn("", "套录库索引修复成功", 1);
    }

    public function get_status2()
    {
        import("@.Extend.TaskStatus.TaskStatus");
        $type = $this->_get("type");
        $val = TaskStatus::getTaskValue("marctype_book_repair");
        $val = ($val === false ? 0 : $val);
        $this->ajaxReturn($val, "success", 1);
    }

    public function get_status3()
    {
        import("@.Extend.TaskStatus.TaskStatus");
        $type = $this->_get("type");
        $val = TaskStatus::getTaskValue("marctype_book_share_repair");
        $val = ($val === false ? 0 : $val);
        $this->ajaxReturn($val, "success", 1);
    }

}
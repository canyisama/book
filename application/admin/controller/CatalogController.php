<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/14
 * Time: 17:57
 */

namespace app\admin\controller;


use app\admin\model\BmLog;
use app\admin\model\Book;
use app\admin\model\Upload;
use think\Lang;
use think\Log;

class CatalogController extends BaseController
{

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/catalog.php']);
    }

    public function indexAction()
    {
        return $this->redirect('book/index', ['source' => 'none']);
    }

    /**
     * 图书编目 - 编目录入
     */
    public function frameworkAction()
    {
        return $this->redirect('book/index', ['source' => 'dck']);
    }

    public function uploadAction()
    {
        $mod_upload = d("Upload");

        if (!$this->isPost) {
            $file_list = $mod_upload->where("user_id={$this->_user_info["user_id"]} AND is_add=0 AND up_type=" . Upload::UP_TYPE_TAOLU)->order("add_time desc")->select();
            $this->assign("file_list", $file_list);
            $mod_mt = d("Mt");
            $mt_list = $mod_mt->get_list();
            $this->assign("mt_list", $mt_list);
            $mod_user = d("User");
            $user_info = $mod_user->field("default_mt")->where("user_id={$this->_user_info["user_id"]}")->find();
            $this->assign("mt_id", $user_info["default_mt"]);
            return view();
        } else {
            $mod_upload->clear_disuse_file();
            $mt_id = input('mt_id/d');

            if (empty($mt_id)) {
                $this->error("请选择有效的MARC类型!");
            }

            $file = request()->file('marc_file');
            if (!$file) {
                $this->error('请上传文件!');
            }
            $error = $_FILES['file']['error'];
            if ($error) {
                $this->error('上传失败，' . $error);
            }

            import("ORG.Net.UploadFile");
            $upload = new UploadFile();
            $upload->maxSize = 2147483648;
            $upload->allowExts = array("jpg", "gif", "png", "jpeg", "iso");
            $upload->savePath = "./Public/Uploads/marcfiles/" . $this->_user_info["user_id"] . "/";

            if (!file_exists($upload->savePath)) {
                mkdir($upload->savePath, 504);
            }

            if (!$upload->upload()) {
                $this->error($upload->getErrorMsg());
            } else {
                $info = $upload->getUploadFileInfo();
                $file_buff = file_get_contents($info[0]["savepath"] . $info[0]["savename"]);
                $marc_cnt = preg_match_all("/\\x1D/", $file_buff, $matchs);
                $add_data = array("user_id" => $this->_user_info["user_id"], "file_encode" => isset($_GET["file_encode"]) ? trim($_GET["file_encode"]) : "utf-8", "file_name" => $info[0]["name"], "file_path" => $info[0]["savepath"] . $info[0]["savename"], "marc_cnt" => $marc_cnt, "up_type" => UploadModel::UP_TYPE_TAOLU, "add_time" => time());
                $upload_id = $mod_upload->add($add_data);

                if (empty($upload_id)) {
                    $this->error("上传文件失败！错误信息:" . $mod_upload->getError());
                    unlink($add_data["file_path"]);
                    return false;
                }

                header("location:" . u("/Catalog/importbook/upload_id/" . $upload_id . "/mt_id/" . $mt_id));
            }
        }
    }

    public function addAction()
    {
        if (!$this->isPost) {
            $copy_book_id = (isset($_GET["copy_book_id"]) ? intval($_GET["copy_book_id"]) : 0);
            $is_qk = (isset($_GET["is_qk"]) ? intval($_GET["is_qk"]) : 0);
            $mod_user = d("User");
            $user_info = $mod_user->field("default_mt,default_tpl,qk_default_mt")->where("user_id={$this->_user_info["user_id"]}")->find();
            $default_tpl = unserialize($user_info["default_tpl"]);
            $mt_id = (isset($_GET["mt_id"]) ? intval($_GET["mt_id"]) : 0);

            if (empty($mt_id)) {
                if (!$is_qk) {
                    $mt_id = (!empty($user_info["default_mt"]) ? $user_info["default_mt"] : 0);
                } else {
                    $mt_id = (!empty($user_info["qk_default_mt"]) ? $user_info["qk_default_mt"] : 0);
                }
            }

            $marc_tpl_id = (isset($_GET["marc_tpl_id"]) ? intval($_GET["marc_tpl_id"]) : 0);
            if (empty($marc_tpl_id)) {
                $marc_tpl_id = (!empty($default_tpl[$mt_id]) ? $default_tpl[$mt_id] : 0);
            }

            $mod_mt = d("Mt");
            $mt_list = $mod_mt->get_list();
            $this->assign("mt_list", $mt_list);
            $mod_marc_tpl = d("Marc_tpl");
            $tpl_list = $mod_marc_tpl->field("marc_tpl_id,tpl_name")->where("mt_id=$mt_id")->order("marc_tpl_id")->select();
            $this->assign("tpl_list", $tpl_list);

            if (empty($mt_id)) {
                $first_mt_id = current($mt_list);
                $mt_id = (!empty($first_mt_id["mt_id"]) ? $first_mt_id["mt_id"] : 0);
            }

            $this->assign("mt_id", $mt_id);
            $tpl_is_exist = false;

            foreach ($tpl_list as $item) {
                if ($item["marc_tpl_id"] == $marc_tpl_id) {
                    $tpl_is_exist = true;
                    break;
                }
            }

            if (empty($marc_tpl_id) || !$tpl_is_exist) {
                $first_marc_tpl_id = current($tpl_list);
                $marc_tpl_id = (!empty($first_marc_tpl_id["marc_tpl_id"]) ? $first_marc_tpl_id["marc_tpl_id"] : 0);
            }

            $this->assign("marc_tpl_id", $marc_tpl_id);

            if (!empty($copy_book_id)) {
                $mod_book = d("Book");
                $book_info = $mod_book->find($copy_book_id);
                $this->assign("book_info", $book_info);
                import('Marc\MARC', EXTEND_PATH, '.class.php');
                $marc_arr = \MARC::readMarcByStr($book_info["marc"]);
                $this->assign("marc_json", json_encode($marc_arr));
            } else {
                $tpl = $mod_marc_tpl->get_tpl($marc_tpl_id);
                $this->assign("marc_json", json_encode($tpl));
            }

            $mapper = $mod_mt->get_mapper($mt_id);
            $this->assign("mapper_json", json_encode($mapper));
            $marc_fields = $mod_mt->get_field_list($mt_id);
            $this->assign("marc_fields", json_encode($marc_fields));
            $this->assign("form_title", "书目操作(添加)");
            $this->_assign_common();
            $this->assign("add_dck_msg", true);
            return view('edit');
        } else {
            $mod_book = d("Book");
            $add_data = array(
                'mt_id' => input('mt_id/d'),
                'is_verify' => input('is_verify/d'),
                'isbn' => input('isbn'),
                'title' => input('title'),
                'bl_title' => input('bl_title'),
                'othertitle' => input('othertitle'),
                'fjtitle' => input('fjtitle'),
                'fjno' => input('fjno'),
                'firstauthor' => input('firstauthor'),
                'series' => input('series'),
                'seriesauthor' => input('seriesauthor'),
                'publisher' => input('publisher'),
                'pubplace' => input('pubplace'),
                'pubdate' => input('pubdate'),
                'pages' => input('pages'),
                'edition' => input('edition'),
                'charts' => input('charts'),
                'gennotes' => input('gennotes'),
                'binding' => input('binding'),
                'lags' => input('lags'),
                'size' => input('size'),
                'abstract' => input('abstract'),
                'clc' => input('clc'),
                'subject' => input('subject'),
                'price_ms' => input('price_ms'),
            );
            if (!$add_data) {
                $this->error($mod_book->getError());
                return false;
            }

            if (!$add_data["mt_id"]) {
                $this->error("请选择有效的书目类型");
                return false;
            }

            $this->_filter_hide_fields($add_data);
            $is_verify = (isset($_POST["is_verify"]) ? intval($_POST["is_verify"]) : 0);
            $add_data["clc"] = strtoupper(trim($add_data["clc"]));
            $add_data["is_verify"] = $is_verify;
            $add_data["tsg_code"] = $this->_user_info["tsg_code"];
            $add_data["cataloger"] = $this->_user_info["user_name"];
            $add_data["catatime"] = time();
            $add_data["marc"] = $this->get_marc_str();
            $book_id = $mod_book->create($add_data)->getLastInsId();

            if ($book_id !== false) {
                $mod_bm_log = d("Bm_log");
                $mod_bm_log->addlog(BmLog::OP_TYPE_BOOK_ADD, $this->_user_info, array("book_id" => $book_id, "db1" => $book_id));
                $this->success('书目新增成功！');
            } else {
                $this->error('新增失败！错误提示:' . $mod_book->getError());
            }
        }
    }

    public function editAction()
    {
        $book_id = input('book_id/d');
        $mod_book = d("Book");
        $book_info = $mod_book->find($book_id)->toArray();
        import('Marc\MARC', EXTEND_PATH, '.class.php');

        if (!$this->isPost) {
            $mt_id = intval($book_info["mt_id"]);
            $book_info = $mod_book->where("book_id=$book_id")->find();

            if (!$book_info) {
                $this->alertMsg(l("not_found_data"));
            }

            $this->assign("has_edit_acc", $book_info["tsg_code"] == $this->_user_info["tsg_code"] ? "1" : "0");
            $mod_mt = d("Mt");
            $mt_list = $mod_mt->get_list();
            $this->assign("mt_list", $mt_list);
            $this->assign("mt_id", $mt_id);
            $mapper = $mod_mt->get_mapper($mt_id);
            $this->assign("mapper_json", json_encode($mapper));
            $marc_fields = $mod_mt->get_field_list($mt_id);
            $this->assign("marc_fields", json_encode($marc_fields));
            $marc_arr = \MARC::readMarcByStr($book_info["marc"]);
            $this->assign("marc_json", json_encode($marc_arr));
            $this->_assign_common();
            $this->assign('book_info', $book_info);
            $this->assign("form_title", "书目操作(编辑)");
            return view();
        } else {
            if (!$book_info) {
                $this->error(l("not_found_data"));
            }
            if ($book_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(l("not_access_edit_data"));
            }
            $save_data = array(
                'is_verify' => input('is_verify/d'),
                'isbn' => input('isbn'),
                'title' => input('title'),
                'bl_title' => input('bl_title'),
                'othertitle' => input('othertitle'),
                'fjtitle' => input('fjtitle'),
                'fjno' => input('fjno'),
                'firstauthor' => input('firstauthor'),
                'series' => input('series'),
                'seriesauthor' => input('seriesauthor'),
                'publisher' => input('publisher'),
                'pubplace' => input('pubplace'),
                'pubdate' => input('pubdate'),
                'pages' => input('pages'),
                'edition' => input('edition'),
                'charts' => input('charts'),
                'gennotes' => input('gennotes'),
                'binding' => input('binding'),
                'lags' => input('lags'),
                'size' => input('size'),
                'abstract' => input('abstract'),
                'clc' => input('clc'),
                'subject' => input('subject'),
                'price_ms' => input('price_ms'),
            );

            $save_data["clc"] = strtoupper(trim($save_data["clc"]));
            $is_verify = input('is_verify/d');
            $save_data["is_verify"] = $is_verify;
            import('Isbn\IsbnBase', EXTEND_PATH, '.class.php');
            $save_data["isbncode"] = \IsbnBase::convIsbnCode($save_data["isbn"]);
            $this->_filter_hide_fields($save_data);
            $save_data["tsg_code"] = $book_info["tsg_code"];
            $save_data["marc"] = $this->get_marc_str();
            unset($save_data["mt_id"]);

            try {
                $mod_book->startTrans();
                $index_list = Book::get_indexrel($book_info["mt_id"], array("fileds" => "sour_field,dest_mod,order_mod,sour_mfield"));

                if (!empty($index_list)) {
                    $marc_obj = new \MARC();
                    $mdata = \MARC::readMarcByStr($save_data["marc"]);
                    $marc_obj->setData($mdata);
                    $zd_001 = str_repeat("0", 9 - strlen($book_id)) . $book_id;
                    $marc_obj->setFieldVal("001", $zd_001);
                    $save_data["marc"] = $marc_obj->toString();
                    $rel_arr = $marc_obj->getRelArray();
                    $book_info = array_merge($book_info, $save_data);
                    $index_add_data = Book::conv_index_data($book_id, $book_info, $rel_arr, $index_list);

                    foreach ($index_add_data as $key => $item) {
                        $mod_dest = d($key);
                        $is_success = $mod_dest->update($item, ['bid' => $book_id])->result;

                        if ($is_success === false) {
                            $mod_book->rollback();
                            Log::write("保存书目更新索引失败  " . $mod_book->db()->getError());
                            $this->error('保存失败！错误提示:更新书目索引数据时遇到错误');
                        }
                    }
                }

                $is_success = $mod_book->update($save_data, ['book_id' => $book_id]);

                if ($is_success !== false) {
                    $mod_bm_log = d("Bm_log");
                    $mod_bm_log->addlog(BmLog::OP_TYPE_BOOK_SAVE, $this->_user_info, array("book_id" => $book_id, "db1" => $book_id));
                    $mod_book->commit();
                    $this->success('保存成功！');
                } else {
                    $mod_book->rollback();
                    Log::write("保存书目失败  " . $mod_book->db()->getError());
                    $this->error('保存失败！错误提示:' . $mod_book->getError());
                }
            } catch (Exception $e) {
                $mod_book->rollback();
                Log::write("保存书目遇到异常  " . $e->getMessage());
                $this->error('保存失败！错误提示:索引数据更新失败');
            }
        }
    }

    public function dropAction()
    {
        $book_id = input('book_id/d');
        $mod_book = d("Book");
        try {
            $book_info = $mod_book->where("book_id=$book_id")->find();

            if (!$book_info) {
                $this->error(l("not_found_data"));
            }
            if ($book_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->error(l("not_access_edit_data"));
            }
            $mod_dck = d("Dck");
            $dck_info = $mod_dck->field("dck_id")->where("book_id=$book_id")->find();

            if ($dck_info) {
                $this->error("书目内存在馆藏信息,无法删除!");
            }
            $mod_book->startTrans();
            $is_success = $mod_book->where("tsg_code='{$this->_user_info["tsg_code"]}' AND book_id=$book_id")->delete();
            if ($is_success !== false) {
                $mod_user = d("User");
                $mt_id = $mod_user->get_mt_id($this->_user_info["user_id"]);
                $mod_indexrel = d("indexrel");
                $index_list = $mod_indexrel->field("dest_mod,order_mod")->where("sour_mod='Book' AND mt_id=$mt_id")->select();

                foreach ($index_list as $item) {
                    $mod_dest = d($item["dest_mod"]);
                    $is_success = $mod_dest->where("bid=$book_id")->delete();

                    if ($is_success === false) {
                        $mod_book->rollback();
                        $this->error('删除失败！错误提示:删除书目索引数据时遇到错误');
                    }

                    if ($item["dest_mod"] != $item["order_mod"]) {
                        $mod_order = d($item["order_mod"]);
                        $is_success = $mod_order->where("bid=$book_id")->delete();
                        if ($is_success === false) {
                            $mod_book->rollback();
                            $this->error('删除失败！错误提示:删除书目索引数据时遇到错误');
                        }
                    }
                }

                $mod_bm_log = d("Bm_log");
                $mod_bm_log->addlog(BmLog::OP_TYPE_BOOK_DROP, $this->_user_info, array("book_id" => $book_id, "db1" => $book_id));
                $mod_book->commit();
                $this->success("删除成功！");
            } else {
                $mod_book->rollback();
                $this->error("删除失败！错误提示:" . $mod_book->getError());
            }
        } catch (Exception $e) {
            $mod_book->rollback();
            $this->error("删除失败！错误提示:程序出现异常!");
        }
    }

    public function authorcodeselAction()
    {
        $author = input('author_str');
        import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');

        if (!$this->isPost) {
            $option = \BookCalino::genAuthorCodeOption($author);
            $this->assign("option", $option);
            //TODO 未完成
            return view();
        } else {
            $option = array("ming_pys" => input('ming_pys'), "xing_all" => input('xing_all'), "xing_py" => input('xing_py'));
            $author_code = \BookCalino::genAuthorCode($option);
            $this->result($author_code);
        }
    }

    /**
     * 书目-审核/反审
     * @throws \think\exception\DbException
     */
    public function verifyAction()
    {
        $book_id = input('book_id/d');
        $is_verify = input('is_verify/d');
        $book_info = Book::get($book_id);

        if (!$book_info) {
            $this->error(lang('not_found_data'));
        }
        if ($book_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }

        $data = array("is_verify" => $is_verify);
        $is_success = Book::update($data, ['book_id' => $book_id]);

        if ($is_success !== false) {
            BmLog::addLog($is_verify ? BmLog::OP_TYPE_BOOK_VERIFY : BmLog::OP_TYPE_BOOK_NOVERIFY, $this->adminInfo, ['book_id' => $book_id, 'db1' => $book_id]);
            $this->success($is_verify ? '审校成功！' : '书目[取消审核]成功！');
        } else {
            $this->success($is_verify ? '审校失败！' : '书目[取消审核]失败！');
        }
    }

    public function get_marc_str()
    {
        import('Marc\MARC', EXTEND_PATH, '.class.php');
        $marc_arr = array();

        $zd_name_list = input('zd_name/a');
        foreach ($zd_name_list as $key => $item) {
            $zd_val = $_POST["zd_val"][$key];
            $zd_val = str_replace(array("\r", "\n", chr(29), chr(30), chr(31)), "", $zd_val);
            $marc_arr[] = array("zd_name" => $item, "zsf" => $_POST["zsf"][$key], "zd_val" => $zd_val);
        }

        return \MARC::getMarcStrByForm($marc_arr);
    }

    public function _filter_hide_fields(&$data)
    {
        $mod_user = d("User");
        $user_info = $mod_user->field("bm_fields")->where("user_id={$this->_user_info["user_id"]}")->find();

        if (empty($user_info["bm_fields"])) {
            return true;
        }

        $field_sel_tmp = explode(",", $user_info["bm_fields"]);

        foreach ($field_sel_tmp as $item) {
            $data[$item] = "";
        }

        return true;
    }

    public function _assign_common()
    {
        $this->assign("today_str", date("Ymd"));
        $mod_user = d("User");
        $user_info = $mod_user->where("user_id={$this->_user_info["user_id"]}")->find();
        $this->assign("pinyin_config", $user_info["pinyin_config"]);
        $this->assign("pinyin_dx", $user_info["pinyin_dx"]);
        $this->assign("bm_del_empty", $user_info["bm_del_empty"]);

        if (!empty($user_info["bm_fields"])) {
            $this->assign("has_fields_hide", true);
        }

        $field_sel_tmp = explode(",", $user_info["bm_fields"]);
        $this->assign("fields_hide_json", json_encode($field_sel_tmp));
        $this->assign("bm_jd_marc", $user_info["bm_jd_marc"]);
        $mod_jdbm_cnf = d("Jdbm_cnf");
        $binding_list = $mod_jdbm_cnf->field("cnf_val")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND cnf_type='装帧方式'")->select();
        $lags_list = $mod_jdbm_cnf->field("cnf_val")->where("tsg_code='{$this->_user_info["tsg_code"]}' AND cnf_type='图书语种'")->select();
        $this->assign("binding_list", $binding_list);
        $this->assign("lags_list", $lags_list);
        $this->assign('_user_info', $this->_user_info);
    }
}
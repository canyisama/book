<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 10:46
 */

namespace app\admin\controller;


use app\admin\model\Book;
use app\admin\model\CfLog;
use app\admin\model\Destine;
use app\admin\model\DestineBatch;
use app\admin\model\JdbmCnf;
use app\admin\model\Mt;
use app\admin\model\Tsg;
use app\admin\model\Upload;
use app\admin\model\User;
use app\admin\model\Ys;
use think\File;

class DestineController extends BaseController
{
    const IMPORT_TASK_NAME = "destine_import";
    const STATUS_YD = 1;
    const STATUS_TD = 2;

    public function frameworkAction()
    {
        //return $this->redirect('Catalog/index', ['source' => 'destine', 'curval' => 2]);
        return $this->redirect('book/index', ['source' => 'destine']);
    }

    public function destine_manAction()
    {
        $destine_batch_list = DestineBatch::where('tsg_code', $this->adminInfo['tsg_code'])->select();
        $this->assign('destine_batch_list', $destine_batch_list);
        return view();
    }

    public function getJsonListAction()
    {
        $condition = [];
        $book_condition = [];

        // 预订管理 => 查询所有分馆 (source=destine_man)
        if (input('source') == 'destine_man') {

        } else {
            // 直接预订 => 设置了 cooke show_all_tsg 时查询所有分馆
            if (cookie('show_all_tsg') != 1) {
                $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
            }
            $condition['book_id'] = input('book_id/d');
        }

        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                switch ($search['field']) {
                    case 'destine_batch_code':
                        $condition['destine_batch_code'] = $search['value'];
                        break;
                    case 'status':
                        $condition['status'] = $search['value'];
                        break;
                    default:
                        $book_condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                        break;
                }
            }
        }
        if ($book_condition) {
            $book_list1 = Book::where($book_condition)->field('book_id')->select();
            $book_search_ids = [0];
            foreach ($book_list1 as $i) {
                $book_search_ids[] = $i['book_id'];
            }
            $condition['book_id'] = ['in', $book_search_ids];
        }

        $list = Destine::getPageList($condition, $params->limit, $params->order);
        $count = Destine::where($condition)->count();
        $book_ids = $tsg_code_list = [];
        foreach ($list as $item) {
            $book_ids[] = $item['book_id'];
            $tsg_code_list[] = $item['tsg_code'];
        }
        $tsg_list = Tsg::where(['tsg_code' => ['in', $tsg_code_list]])->select();
        $tsg_list = array_under_reset($tsg_list, 'tsg_code');
        $book_list = Book::where(['book_id' => ['in', $book_ids]])->select();
        $book_list = array_under_reset($book_list, 'book_id');

        foreach ($list as &$item) {
            $tsg = $tsg_list[$item['tsg_code']];
            $book = $book_list[$item['book_id']];
            $item['clc'] = $book['clc'];
            $item['title'] = $book['title'];
            $item['tsg_code'] = $tsg['tsg_code'] . ' | ' . $tsg['tsg_name'];
            $item['discount'] = $item['discount'] . ' %';
            $item['publisher'] = $book['publisher'];
        }
        unset($item);
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    /**
     * 编辑/新增图书预定信息
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function editAction()
    {
        $book_id = input('book_id/d');
        $destine_id = input('destine_id/d');
        $destine_info = Destine::get($destine_id);

        if ($this->isPost) {
            if ($destine_id) {
                if ($destine_info["tsg_code"] != $this->adminInfo['tsg_code']) {
                    $this->error(lang('not_access_edit_data'));
                }
            }
            $save_data = input('');
            if (!$save_data["ori_price"]) {
                $this->error(lang('ori_price_required'));
            }
            if (!$save_data["discount"]) {
                $this->error(lang('discount_required'));
            }
            if (!$save_data["price"]) {
                $this->error(lang('price_required'));
            }
            if (!$save_data["book_cnt"]) {
                $this->error(lang('book_cnt_required'));
            }
            unset($save_data["destine_id"]);

            $ori_price = floatval($save_data["ori_price"]);
            $discount = floatval($save_data["discount"]);
            $save_data["price"] = round(($ori_price * $discount) / 100, 2);

            if ($destine_id) {
                unset($save_data["book_id"]);
                $is_success = Destine::update($save_data, ['destine_id' => $destine_id])->result;
            } else {
                $save_data['add_time'] = time();
                $save_data['add_user'] = $this->adminInfo['user_name'];
                $is_success = Destine::create($save_data)->result;
            }

            if ($is_success !== false) {
                CfLog::addLog($destine_id ? CfLog::OP_TYPE_YD_SAVE : CfLog::OP_TYPE_YD_ADD, $this->adminInfo, array("book_id" => $destine_info["book_id"], "db1" => $destine_id));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
        if ($destine_info && $this->adminInfo['tsg_code'] != $destine_info['tsg_code']) {
            $this->alertMsg(lang('not_access_edit_data'));
        }
        $user_info = User::get($this->adminInfo['user_id']);
        if (empty($user_info)) {
            $this->alertMsg('未设定默认预订批次!');
        }
        $batch_info = DestineBatch::where(['destine_batch_code' => $user_info['destine_batch_curr'], 'tsg_code' => $this->adminInfo['tsg_code']])->find();
        if (empty($batch_info)) {
            $this->alertMsg('默认预订批次数据库不存在,请重新设定!');
        }
        if ($batch_info["status"] != DestineBatch::BATCH_STATUS_YD) {
            $this->alertMsg('默认预订批次必须为预订状态才可编辑预订信息!');
        }

        $ly_list = JdbmCnf::where(['cnf_type' => '图书来源', 'tsg_code' => $this->adminInfo['tsg_code']])->select();
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['tsg_code'])->find();
        $this->assign('info', $destine_info);
        $this->assign('book_id', $book_id);
        $this->assign("ly_list", $ly_list);
        $this->assign("tsg_info", $tsg_info);
        $this->assign("user_info", $user_info);
        $this->assign("batch_info", $batch_info);
        return view('edit');
    }

    /**
     * 退订/重订
     * @throws \think\exception\DbException
     */
    public function setStateAction()
    {
        $state = input('status/d');
        $destine_id = input('destine_id/d');

        $destine_info = Destine::get($destine_id);
        if (!$destine_info) {
            $this->error(lang('not_found_data'));
        }
        if ($destine_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }
        $data = array("status" => $state);
        $is_success = Destine::update($data, ['destine_id' => $destine_id]);
        if ($is_success) {
            CfLog::addLog($state == 1 ? CfLog::OP_TYPE_YD_CD : CfLog::OP_TYPE_YD_TD, $this->adminInfo, ['book_id' => $destine_info['book_id'], 'db1' => $destine_id]);
            $this->success($state == 1 ? '重订成功！' : '退订成功！');
        } else {
            $this->error($state == 1 ? '重订失败！' : '退订失败！');
        }
    }

    /**
     * 删除预定
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function dropAction()
    {
        $destine_id = input('destine_id/d');
        $destine_info = Destine::get($destine_id);
        if (!$destine_info) {
            $this->error(lang('not_found_data'));
        }
        if ($destine_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }
        $ys_info = Ys::where('destine_id', $destine_id)->find();
        if (!empty($ys_info)) {
            $this->error('本预订数据存在验收信息,无法删除！');
        }
        $is_success = Destine::where(['tsg_code' => $this->adminInfo['tsg_code'], 'destine_id' => $destine_id])->delete();
        if ($is_success) {
            CfLog::addLog(CfLog::OP_TYPE_YD_DROP, $this->adminInfo, ['book_id' => $destine_info['book_id'], 'db1' => $destine_id]);
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

    public function uploadAction()
    {
        if ($this->isPost) {
            Upload::clear_disuse_file();
            $mt_id = input('mt_id_change/d');
            $file_encode = input('file_encode', 'utf-8');
            if (empty($mt_id)) {
                $this->error('请选择有效的MARC类型!');
            }

            $file = request()->file('marc_file');
            if (!$file) {
                $this->error('请上传文件!');
            }
            $error = $_FILES['file']['error'];
            if ($error) {
                $this->error('上传失败，' . $error);
            }
            $dir = config('upload_path') . 'marcfiles/';
            $info = $file->move(ROOT_PATH . 'public/' . $dir);

            $saveName = 'marcfiles/' . $info->getSaveName();
            $data['fileName'] = $saveName;
            $data['filePath'] = get_img_full_path($saveName);
            if ($file->getError()) {
                $this->error($file->getError());
            }
            $file_buff = file_get_contents(ROOT_PATH . 'public/' . $dir . $info->getSaveName());
            $marc_cnt = preg_match_all("/\\x1D/", $file_buff, $matchs);

            $add_data = [];
            $add_data['user_id'] = $this->adminInfo['user_id'];
            $add_data['file_encode'] = $file_encode;
            $add_data['file_name'] = $file->getInfo()['name'];
            $add_data['file_path'] = $saveName;
            $add_data['marc_cnt'] = $marc_cnt;
            $add_data['up_type'] = Upload::UP_TYPE_DESTINE;
            $add_data['add_time'] = time();
            $result = Upload::create($add_data)->result;
            if ($result) {
                // 立即导入文件 TODO
                //$this->redirect('', ['upload_id' => $result, 'mt_id' => $mt_id]);
                $this->success('上传成功');
            } else {
                @unlink(get_img_real_path($add_data["file_path"]));
                $this->error('上传文件失败！');
            }

        }
        $mt_list = Mt::get_list();
        $file_list = Upload::where(['user_id' => $this->adminInfo['user_id'], 'is_add' => 0, 'up_type' => Upload::UP_TYPE_DESTINE])->order('add_time desc')->select();
        $this->assign("mt_id", $this->adminInfo["default_mt"]);
        $this->assign('mt_list', $mt_list);
        $this->assign('file_list', $file_list);
        return view();
    }

    public function importbookAction()
    {
        exit();
        $mt_id = input('mt_id/d');
        $upload_id = input('upload_id/d');
        $mt_info = Mt::get($mt_id);

        if (empty($mt_info)) {
            $this->error("请选择有效的MARC类型!");
        }
        $this->assign("mt_info", $mt_info);
        $file_info = Upload::where(['upload_id' => $upload_id, 'user_id' => $this->adminInfo['user_id'], 'is_add' => 0, 'up_type' => Upload::UP_TYPE_DESTINE])->find();
        if (empty($file_info)) {
            $this->error("在数据库未找到该文件信息！");
        }
        if (!file_exists(get_img_real_path($file_info["file_path"]))) {
            $file_info->delete();
            $this->error("在服务器上未找到该文件！");
        }
        $user_info = User::where('user_id', $this->adminInfo['user_id'])->field("destine_batch_curr")->find();
        if (empty($user_info)) {
            $this->error("请先设定默认预订批次!");
        }
        $batch_info = DestineBatch::field("destine_batch_code,seller_code,cost_code,status")
            ->where("destine_batch_code='{$this->adminInfo['destine_batch_curr']}' AND tsg_code='{$this->adminInfo["tsg_code"]}'")->find();
        if (empty($batch_info)) {
            $this->error("无效默认预订批次,请重新设定默认预订批次!");
        }
        if ($batch_info["status"] != DestineBatchController::BATCH_STATUS_YD) {
            $this->error("默认预订批次必须为预订状态,请重新设定默认预订批次!");
        }

        if (!$this->isPost) {
            $change_file_encode = input('change_file_encode');
            if (($change_file_encode != "") && ($change_file_encode != $file_info["file_encode"])) {
                Upload::where("upload_id=$upload_id")->save(array("file_encode" => $change_file_encode));
                $file_info["file_encode"] = $change_file_encode;
            }

            import("@.Marc.MARCReader");
            $reader1 = new MARCReader($file_info["file_path"], $file_info["file_encode"]);
            $marc_list = array();
            $raw_list = array();

            for ($i = 0; ($marc = $reader1->next()) != false; $i++) {
                $marc_list[] = nl2br($marc->toString("", array("zsf_replace" => "_", "field_space_replace" => "&nbsp;", "field_head_replace" => "§")));
                $raw_list[] = $marc->getDataConvEncode("utf-8");
            }

            $this->assign("raw_list", json_encode($raw_list));
            $this->assign("file_info", $file_info);
            $this->assign("batch_info", $batch_info);
            $this->assign("marc_list", $marc_list);
            $this->display();
        } else {
            import('TaskStatus\TaskStatus', EXTEND_PATH, '.class.php');
            TaskStatus::initTaskValue(self::IMPORT_TASK_NAME);
            $beg_time = time();
            $cnf = $this->getcnf();
            $mod_book = d("Book");
            $mod_destine = d("Destine");
            import("@.Marc.MARCReader");
            import("@.Extend.Scws.Scws");
            import('String', EXTEND_PATH, '.class.php');
            $msg_gbk = array(String::autoCharset("重复数据", "utf-8", "gbk"), String::autoCharset("预订数据不完整(单价、折后价、复本数都为空)", "utf-8", "gbk"), String::autoCharset("插入数据错误", "utf-8", "gbk"), String::autoCharset("程序处理出现异常", "utf-8", "gbk"), String::autoCharset("插入预订数据错误", "utf-8", "gbk"), String::autoCharset("增加书目索引数据时遇到错误", "utf-8", "gbk"));

            if (!class_exists("IsbnBase")) {
                import("@.Extend.Isbn.IsbnBase");
            }

            $reader1 = new MARCReader($file_info["file_path"], $file_info["file_encode"]);
            $marc_list = array();
            $del_fields = $cnf["del_fields"];
            $import_err = "";
            $import_cnt = 0;
            $import_err_cnt = 0;
            $cq_table = array();
            $cq_where_map = array();

            foreach ($cnf["zd_cnf"] as $key => $item) {
                if (!in_array($key, array("ori_price", "price", "book_cnt", "jzinfo", "order_no", "order_sour", "remark"))) {
                    unset($cnf["zd_cnf"][$key]);
                }
            }

            $index_list = BookModel::get_indexrel($mt_id, array("fileds" => "sour_field,dest_mod,order_mod,sour_mfield,order_table"));

            foreach ($index_list as $item) {
                if (in_array($item["sour_field"], $cnf["field_cnf"])) {
                    $cq_table[] = $item["order_table"];
                    $cq_where_map[$item["sour_field"]] = $item["order_table"] . ".val";
                }
            }

            $is_cq = (!empty($cq_table) ? true : false);
            $cq_table_str = "";
            $first_cq_table = "";

            for ($i = 0; $i < count($cq_table); $i++) {
                if ($i == 0) {
                    $first_cq_table = $cq_table[0];
                    $cq_table_str = $cq_table[0];
                } else {
                    $prev_i = $i - 1;
                    $cq_table_str .= " INNER JOIN $cq_table[$i] on $cq_table[$prev_i].bid=$cq_table[$i].bid";
                }
            }

            $all_marc_cnt = $file_info["marc_cnt"];
            $task_per = (1 / $all_marc_cnt) * 100;

            for ($task_cnt = 0; ($marc = $reader1->next()) != false;) {
                $task_cnt += $task_per;
                TaskStatus::setTaskValue(self::IMPORT_TASK_NAME, $task_cnt);

                try {
                    if (!$marc->hasError()) {
                        $add_data = $marc->getSimpleData($mt_id);

                        if ($is_cq) {
                            $cq_where = array();

                            foreach ($cq_where_map as $key => $item) {
                                $val_tmp = ($add_data[$key] ? $add_data[$key] : "");
                                $cq_where[$item] = array("eq", $val_tmp);
                            }

                            $cq_info = $mod_book->field("$first_cq_table.bid")->table($cq_table_str)->where($cq_where)->find();

                            if (!empty($cq_info)) {
                                $import_err_cnt++;
                                $import_err .= $msg_gbk[0] . "  " . $marc->getMarcRaw() . chr(13);
                                continue;
                            }
                        }

                        $add_data["tsg_code"] = $this->_user_info["tsg_code"];
                        $add_data["cataloger"] = $this->_user_info["user_name"];
                        $add_data["catatime"] = time();
                        $add_data["mt_id"] = $mt_id;
                        $rel_arr = $marc->getRelArray();
                        $marc->dropField($del_fields);
                        $add_data["marc"] = $marc->toString();
                        $yd_data = array();

                        foreach ($cnf["zd_cnf"] as $key => $item) {
                            if ($item["type_sel"] == 1) {
                                if (!empty($item["marc_input"]) && (strlen($item["marc_input"]) == 4)) {
                                    $yd_data[$key] = (!empty($rel_arr[$item["marc_input"]][0]) ? $rel_arr[$item["marc_input"]][0] : "");
                                }
                            } else {
                                $yd_data[$key] = $item["custom_val"];
                            }
                        }

                        if ((empty($yd_data["price"]) && empty($yd_data["ori_price"])) || empty($yd_data["book_cnt"])) {
                            $import_err_cnt++;
                            $import_err .= $msg_gbk[1] . "  " . $marc->getMarcRaw() . chr(13);
                            continue;
                        }

                        $yd_data["price"] = (empty($yd_data["price"]) ? floatval($yd_data["ori_price"]) : floatval($yd_data["price"]));
                        $yd_data["ori_price"] = (empty($yd_data["ori_price"]) ? floatval($yd_data["price"]) : floatval($yd_data["ori_price"]));
                        $mod_book->startTrans();
                        $book_id = $mod_book->addFast($add_data);

                        if ($book_id === false) {
                            $import_err_cnt++;
                            $mod_book->rollback();
                            $import_err .= $msg_gbk[2] . "    " . $marc->getMarcRaw() . chr(13);
                            Log::write("预订数据导入-插入数据错误     " . $mod_book->getError());
                            continue;
                        }

                        $index_add_data = BookModel::conv_index_data($book_id, $add_data, $rel_arr, $index_list);

                        foreach ($index_add_data as $key => $item) {
                            $mod_dest = d($key);
                            $is_success = $mod_dest->add($item);

                            if ($is_success === false) {
                                $mod_book->rollback();
                                $import_err_cnt++;
                                $import_err .= $msg_gbk[5] . "    " . $marc->getMarcRaw() . chr(13);
                                Log::write("插入书目索引失败    " . $mod_dest->db()->getError());
                                continue 2;
                            }
                        }

                        $yd_data["book_id"] = $book_id;
                        $yd_data["tsg_code"] = $this->_user_info["tsg_code"];
                        $yd_data["destine_batch_code"] = $batch_info["destine_batch_code"];
                        $yd_data["seller_code"] = $batch_info["seller_code"];
                        $yd_data["cost_code"] = $batch_info["cost_code"];
                        $yd_data["discount"] = round(($yd_data["price"] / $yd_data["ori_price"]) * 100, 2);
                        $yd_data["add_time"] = mstrtotime(date("Y-m-d"));
                        $yd_data["add_user"] = $this->_user_info["user_name"];
                        $destine_id = $mod_destine->add($yd_data);

                        if ($destine_id !== false) {
                            $mod_book->commit();
                            $import_cnt++;
                        } else {
                            $mod_book->rollback();
                            $import_err_cnt++;
                            $import_err .= $msg_gbk[4] . "    " . $marc->getMarcRaw() . chr(13);
                            Log::write("预订数据导入-插入预订数据错误     " . $mod_destine->db()->getError());
                        }
                    } else {
                        $mod_book->rollback();
                        $import_err_cnt++;
                        $import_err .= $marc->getError() . "    " . $marc->getMarcRaw() . chr(13);
                    }
                } catch (Exception $e) {
                    $import_err_cnt++;
                    $import_err .= $msg_gbk[3] . "    " . $marc->getMarcRaw() . chr(13);
                }
            }

            $save_data = array();

            if (0 < $import_err_cnt) {
                $file_name_tmp = basename($file_info["file_path"]);
                $file_name_tmp = explode(".", $file_name_tmp);
                $file_name_tmp = "err_" . $file_name_tmp[0] . ".txt";
                $err_file_path = dirname($file_info["file_path"]) . "/" . $file_name_tmp;
                file_put_contents($err_file_path, $import_err);
                $save_data["err_file"] = $err_file_path;
                $file_info["err_file"] = $err_file_path;
            }

            $save_data["is_add"] = 1;
            $save_data["disuse_time"] = mstrtotime(date("Y-m-d"));
            $mod_upload->where("upload_id=$upload_id")->save($save_data);
            $reader1->closeFile();
            unlink($file_info["file_path"]);
            $end_time = time();
            $use_time = $end_time - $beg_time;
            $use_time = floor($use_time / 3600) . "小时" . floor(($use_time % 3600) / 60) . "分钟" . floor($use_time % 3600 % 60) . "秒";
            $this->assign("use_time", $use_time);
            $this->assign("file_info", $file_info);
            $this->assign("import_cnt", $import_cnt);
            $this->assign("import_err_cnt", $import_err_cnt);
            $this->assign("import_err", $import_err);
            $this->assign("marc_list", $marc_list);
            $this->display("importinfo");
            return NULL;
        }
    }

    public static function get_status_list()
    {
        return array(self::STATUS_YD => "预订", self::STATUS_TD => "退订");
    }

}
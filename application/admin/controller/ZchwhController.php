<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/10
 * Time: 15:13
 */

namespace app\admin\controller;


use app\admin\model\BmLog;
use app\admin\model\ZchMax;
use think\Exception;
use think\Lang;

/**
 * 编目入库 -> 数据处理 -> 种次号维护
 * Class ZchwhController
 * @package app\admin\controller
 */
class ZchwhController extends BaseController
{

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/zchwh.php']);
    }

    public function indexAction()
    {
        $mod_mt = d('Mt');
        $mt_list = $mod_mt->get_list();
        $this->assign('mt_list', $mt_list);
        return view();
    }

    public function getJsonListAction()
    {
        $params = $this->getQueryParams();//分页,排序,查询参数
        $condition = ['tsg_code' => $this->_user_info['tsg_code']];
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = $search['value'];
            }
        }

        $mod_mt = d('Mt');
        $mt_list = $mod_mt->get_list();
        if (!empty($mt_list) && empty($condition['mt_id'])) {
            if ($mt_list[0]['mt_id']) {
                $condition['mt_id'] = $mt_list[0]['mt_id'];
            }
        }
        $fields = "zch_max_id,tsg_code,clc,max_zch";
        if (input('is_export')) {
            $zch_max_list = ZchMax::field($fields)->where($condition)->order($params->order)->select();
            $tmp_arr = array();

            foreach ($zch_max_list as $item) {
                $tmp_arr[] = "{$item["clc"]}\t{$item["max_zch"]}";
            }

            $file_buff = implode("\r\n", $tmp_arr);
            $savePath = ROOT_PATH . 'public/' . config('upload_path') . 'tempfiles/' . $this->_user_info["user_id"] . '/zchwh_export_' . date("YmdHis") . ".txt";
            $dir = dirname($savePath);

            if (!file_exists($dir)) {
                @mkdir($dir, 504);
            }

            file_put_contents($savePath, $file_buff);
            import("ORG\Net\Http", EXTEND_PATH, '.class.php');
            $fname = basename($savePath);
            \Http::download($savePath, $fname, $file_buff, 1800);
            @unlink($savePath);
            exit;
        }
        $list = ZchMax::getPageList($condition, $params->limit, $params->order, $fields);
        $count = ZchMax::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        $mt_id = input('mt_id/d');
        $mod_mt = d('Mt');
        $mt_info = $mod_mt->field('mt_id,mt_code')->where('mt_id', $mt_id)->find();

        if (!$this->isPost) {
            if (!$mt_info) {
                $this->alertMsg('请选择有效的MARC类型后再新增');
            }
            $this->assign('mt_info', $mt_info);
            return view('edit');
        } else {
            $mod_zch_max = d('Zch_max');
            $add_data = ['clc' => input('clc'), 'max_zch' => input('max_zch')];

            if (!$mt_info) {
                $this->error('必须选择有效的MARC类型!');
            }
            if (!$add_data['clc']) {
                $this->error(l('clc_require'));
            }
            if (!$add_data['max_zch']) {
                $this->error(l('max_zch_require'));
            }
            if (!$mod_zch_max->unique($this->_user_info['tsg_code'], $add_data['clc'], $mt_id)) {
                $this->error(l('clc_exist'));
            }

            $add_data['mt_id'] = $mt_id;
            $add_data['tsg_code'] = $this->_user_info['tsg_code'];
            $zch_max_id = $mod_zch_max->create($add_data)->getLastInsID();

            if ($zch_max_id !== false) {
                $mod_bm_log = d('Bm_log');
                $mod_bm_log->addlog(BmLog::OP_TYPE_ZCH_WH_ADD, $this->_user_info, array('db1' => $add_data['clc'], 'db2' => $add_data['max_zch']));
                $this->success('新增成功！');
            } else {
                $this->error('新增失败！错误提示:' . $mod_zch_max->getError());
            }
        }
    }

    public function editAction()
    {
        $zch_max_id = input('zch_max_id/d');
        $mod_zch_max = d('Zch_max');
        $mt_id = input('mt_id/d');
        $mod_mt = d('Mt');
        $mt_info = $mod_mt->field('mt_id,mt_code')->where('mt_id', $mt_id)->find();
        $zch_max_info = $mod_zch_max->find($zch_max_id);

        if (!$this->isPost) {
            if (!$mt_info) {
                $this->alertMsg('请选择有效的MARC类型后再新增');
            }
            $this->assign('zch_max_info', $zch_max_info);
            if (!$zch_max_info) {
                $this->alertMsg(l('not_found_data'));
            }
            if ($zch_max_info['tsg_code'] != $this->_user_info['tsg_code']) {
                $this->alertMsg(l('not_access_edit_data'));
            }
            $mod_mt = d('Mt');
            $mt_info = $mod_mt->field('mt_code')->where('mt_id', $zch_max_info['mt_id'])->find();
            if (!$mt_info) {
                $this->alertMsg('请选择有效的MARC类型后再新增');
            }
            $this->assign('mt_info', $mt_info);
            return view('edit');
        } else {
            if (!$mt_info) {
                $this->error('请选择有效的MARC类型后再新增');
            }
            if (!$zch_max_info) {
                $this->error(l('not_found_data'));
            }
            if ($zch_max_info['tsg_code'] != $this->_user_info['tsg_code']) {
                $this->error(l('not_access_edit_data'));
            }

            $save_data = ['clc' => input('clc'), 'max_zch' => input('max_zch')];
            if (!$save_data['clc']) {
                $this->error(l('clc_require'));
            }
            if (!$save_data['max_zch']) {
                $this->error(l('max_zch_require'));
            }
            if (!$mod_zch_max->unique($this->_user_info['tsg_code'], $save_data['clc'], $mt_id, $zch_max_id)) {
                $this->error(l('clc_exist'));
            }
            unset($save_data['tsg_code']);
            $is_success = $mod_zch_max->update($save_data, ['zch_max_id' => $zch_max_id])->result;
            if ($is_success !== false) {
                $mod_bm_log = d('Bm_log');
                $mod_bm_log->addlog(BmLog::OP_TYPE_ZCH_WH_SAVE, $this->_user_info, array('db1' => $save_data['clc'], 'db2' => $save_data['max_zch']));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！错误提示:' . $mod_zch_max->getError());
            }
        }
    }

    public function dropAction()
    {
        $zch_max_id = input('zch_max_id/d');
        $mod_zch_max = d("Zch_max");
        $zch_max_info = $mod_zch_max->where("zch_max_id=$zch_max_id")->find();
        if (!$zch_max_info) {
            $this->error(l("not_found_data"));
        }
        if ($zch_max_info["tsg_code"] != $this->_user_info["tsg_code"]) {
            $this->error(l("not_access_edit_data"));
        }
        $is_success = $mod_zch_max->where("zch_max_id=$zch_max_id AND tsg_code='{$this->_user_info["tsg_code"]}'")->delete();
        if ($is_success) {
            $mod_bm_log = d("Bm_log");
            $mod_bm_log->addlog(BmLog::OP_TYPE_ZCH_WH_DROP, $this->_user_info, array("db1" => $zch_max_info["clc"], "db2" => $zch_max_info["max_zch"]));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_zch_max->getError());
        }
    }

    public function import_zchAction()
    {
        $mt_id = input('mt_id/d');
        $mod_mt = d("Mt");
        $mt_info = $mod_mt->field("mt_id,mt_code")->where("mt_id=$mt_id")->find();

        if (!$this->isPost) {
            if (!$mt_info) {
                $this->alertMsg('请选择有效的MARC类型后再导入');
            }
            return view();
        } else {
            if (!$mt_info) {
                $this->error('请选择有效的MARC类型后再导入');
            }

            $is_fugai = $this->_post("is_fugai");
            $file = request()->file('marc_file');
            if (!$file) {
                $this->error('请上传文件!');
            }
            $dir = config('upload_path') . 'tempfiles/' . $this->_user_info["user_id"] . '/';
            $info = $file->validate(['size' => 2147483648, 'ext' => 'txt'])->move(ROOT_PATH . 'public/' . $dir);
            if (!$info) {
                $this->error($file->getError());
            }
            $file_path = ROOT_PATH . 'public/' . $dir . $info->getSaveName();
            $file_buff = file($file_path);
            @unlink($file_path);
            $add_data_list = array();
            $clc_list = array();

            foreach ($file_buff as $key => $item) {
                $tmp_arr = preg_split("/\\t/", $item);
                $add_data_list[$key] = array("tsg_code" => $this->_user_info["tsg_code"], "mt_id" => $mt_id, "clc" => trim($tmp_arr[0]), "max_zch" => trim($tmp_arr[1]));
                $clc_list[$key] = trim($tmp_arr[0]);
            }

            $clc_list = array_unique($clc_list);
            $mod_zch_max = d("Zch_max");
            $tmp_list = array();

            foreach ($add_data_list as $key => $item) {
                if (!empty($item["clc"]) && in_array($item["clc"], $clc_list)) {
                    $tmp_list[] = $item;
                }
            }

            $chunk_list = array_chunk($tmp_list, 1000);
            $add_list = array();
            $save_list = array();

            foreach ($chunk_list as $item) {
                $clc_list = array();
                foreach ($item as $item1) {
                    $clc_list[] = "'{$item1["clc"]}'";
                }

                $clc_list = (!empty($clc_list) ? implode(",", $clc_list) : "0");
                $clc_list_db = $mod_zch_max->field("clc")->where("clc in($clc_list) AND tsg_code='{$this->_user_info["tsg_code"]}' AND mt_id=$mt_id")->select();
                $clc_list1 = array();

                foreach ($clc_list_db as $item2) {
                    $clc_list1[] = $item2["clc"];
                }

                foreach ($item as $item1) {
                    if (in_array($item1["clc"], $clc_list1)) {
                        $save_list[] = $item1;
                    } else {
                        $add_list[] = $item1;
                    }
                }
            }
            $mod_zch_max->insertAll($add_list);
            if ($is_fugai) {
                foreach ($save_list as $item) {
                    $mod_zch_max->update($item, ['clc' => $item['clc'], 'tsg_code' => $this->_user_info['tsg_code'], 'mt_id' => $mt_id]);
                }
            }

            $mod_bm_log = d("Bm_log");
            $mod_bm_log->addlog(BmLog::OP_TYPE_ZCH_WH_IMPORT, $this->_user_info, array("db1" => "无"));
            $this->alertMsg('导入成功', 1, self::PARENT_RELOAD);
        }
    }

    public function renzchAction()
    {
        $mt_id = input('mt_id/d');
        $mod_mt = d("Mt");
        $mt_info = $mod_mt->field("mt_id,mt_code")->where("mt_id=$mt_id")->find();

        if (!$mt_info) {
            $this->alertMsg('请选择有效的MARC类型后再重建');
        }
        $mod_zch_max = d("Zch_max");
        try {
            $mod_zch_max->startTrans();
            $is_success = $mod_zch_max->where("tsg_code='{$this->_user_info["tsg_code"]}' AND mt_id=$mt_id")->delete();

            if ($is_success === false) {
                $mod_zch_max->rollback();
                $this->error('批量重建失败！错误提示:' . $mod_zch_max->getError());
            }

            import('BookCalino\BookCalino', EXTEND_PATH, '.class.php');
            $is_success = $mod_zch_max->query("insert into lib_zch_max (mt_id,tsg_code,clc,max_zch) select $mt_id,tsg_code,clc,max(zch) from lib_zch where tsg_code='{$this->_user_info["tsg_code"]}' AND mt_id=$mt_id AND calino_type=" . \BookCalino::CLC_TYPE_ZCH . " group by clc");

            if ($is_success !== false) {
                $mod_bm_log = d("Bm_log");
                $mod_bm_log->addlog(BmLog::OP_TYPE_ZCH_WH_RE, $this->_user_info, array("db1" => "无"));
                $mod_zch_max->commit();
                $this->success('批量重建成功');
            } else {
                $mod_zch_max->rollback();
                $this->error('批量重建失败:更新数据库失败');
            }
        } catch (Exception $e) {
            $mod_zch_max->rollback();
            $this->error('批量重建失败！错误提示:' . $e->getMessage());
        }
    }

}
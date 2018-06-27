<?php

namespace app\admin\controller;

use app\admin\model\Pinyin;
use app\admin\model\SysLog;
use think\Lang;

class PinyinmanController extends BaseController
{

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/pinyinman.php']);
    }

    /**
     * 汉子拼音库维护
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

        $list = Pinyin::getPageList($condition, $params->limit, $params->order);
        $count = Pinyin::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $pinyin_id = input('pinyin_id/d');
        if ($this->isPost) {
            $data = [];
            $data['hz'] = input('hz');
            $data['py2'] = input('py2');
            $data['sjcode'] = input('sjcode');

            if (!Pinyin::unique($data["hz"], $pinyin_id)) {
                $this->error(l("hz_exist"));
            }

            if ($pinyin_id) {
                $result = Pinyin::update($data, ['pinyin_id' => $pinyin_id])->result;
                $log_type = SysLog::OP_TYPE_PINYIN_SAVE;
            } else {
                $result = Pinyin::create($data)->result;
                $log_type = SysLog::OP_TYPE_PINYIN_ADD;
            }
            if ($result !== false) {
                SysLog::addLog($log_type, $this->adminInfo, ['db1' => $pinyin_id]);
                $this->success('保存成功!');
            } else {
                $this->error('保存失败!');
            }
        }

        $info = Pinyin::get(['pinyin_id' => $pinyin_id]);
        $this->assign('info', $info);
        return view('edit');
    }

    /**
     * 删除
     */
    public function dropAction()
    {
        $pinyin_id = input('pinyin_id/d');
        $mod_pinyin = d("Pinyin");
        $pinyin_info = $mod_pinyin->where(['pinyin_id' => $pinyin_id])->find();

        if (!$pinyin_info) {
            $this->error(lang("not_found_data"));
        }

        $is_success = $mod_pinyin->where(['pinyin_id' => $pinyin_id])->delete();
        if ($is_success) {
            $mod_sys_log = d("Sys_log");
            $mod_sys_log->addlog(SysLog::OP_TYPE_PINYIN_DROP, $this->adminInfo, array("db1" => $pinyin_id, "op_desc" => "[#],汉字:{$pinyin_info["hz"]},拼音:{$pinyin_info["py2"]}"));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！错误提示:" . $mod_pinyin->getError());
        }
    }

}
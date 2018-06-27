<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/14
 * Time: 15:20
 */

namespace app\admin\controller;


use app\admin\model\Annou;
use think\Lang;

class AnnouController extends BaseController
{

    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/annou.php']);
    }

    public function indexAction()
    {
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
        $list = Annou::getPageList($condition, $params->limit, $params->order);
        $count = Annou::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $mod_annou = d("Annou");
        $annou_id = input('annou_id/d');
        $annou_info = $mod_annou->where('annou_id', $annou_id)->find();

        if ($this->isPost) {
            if ($annou_id) {   // 编辑
                if ($annou_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                    $this->error(l("not_access_edit_data"));
                }
            }
            $save_data = ['subject' => input('subject'), 'body' => input('body')];
            if (!$save_data["subject"]) {
                $this->error(l("subject_required"));
            }
            if (!$save_data["body"]) {
                $this->error(l("body_required"));
            }
            if ($annou_id) {
                $result = $mod_annou->update($save_data, ['annou_id' => $annou_id]);
            } else {
                $save_data['tsg_code'] = $this->adminInfo['tsg_code'];
                $result = $mod_annou->create($save_data);
            }
            if ($result->result !== false) {
                $this->success('保存成功');
            } else {
                $this->error('保存失败');
            }
        }

        if ($annou_info) {
            if ($annou_info["tsg_code"] != $this->_user_info["tsg_code"]) {
                $this->alertMsg(l("not_access_edit_data"));
            }
        }
        $this->assign('annou_info', $annou_info);
        return view('edit');
    }

    public function dropAction()
    {
        $annou_id = input('annou_id/d');
        $mod_annou = d("Annou");
        $annou_info = $mod_annou->where("annou_id=$annou_id")->find();

        if (!$annou_info) {
            $this->error(l("not_found_data"));
        }
        if ($annou_info["tsg_code"] != $this->_user_info["tsg_code"]) {
            $this->error(l("not_access_edit_data"));
        }

        $is_success = $mod_annou->where("annou_id=$annou_id")->delete();
        if ($is_success) {
            $this->success("删除成功");
        } else {
            $this->error("删除失败:更新数据库失败");
        }
    }

}
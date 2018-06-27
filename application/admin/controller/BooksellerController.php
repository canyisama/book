<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25
 * Time: 10:18
 */

namespace app\admin\controller;


use app\admin\model\Bookseller;
use app\admin\model\CfLog;
use app\admin\model\Tsg;
use think\Lang;

class BooksellerController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/bookseller.php']);
    }

    public function indexAction()
    {
        return view();
    }

    public function getJsonListAction()
    {
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数
        if ($params->search) {
            foreach ($params->search as $search) {
                $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
            }
        }

        $list = Bookseller::getPageList($condition, $params->limit, $params->order);
        $count = Bookseller::where($condition)->count();
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        foreach ($list as &$item) {
            $item['tsg_code'] = $tsg_info['tsg_code'] . ' | ' . $tsg_info['tsg_name'];
        }
        unset($item);
        return $this->echoPageData($list, $count);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $seller_code = input('seller_code');
        $info = Bookseller::get(['seller_code' => $seller_code, 'tsg_code' => $this->adminInfo['tsg_code']]);
        if ($this->isPost) {
            $hide_code = input('hide_code');
            $data = [];
            $data['tel'] = input('tel');
            $data['phone'] = input('phone');
            $data['email'] = input('email');
            $data['contact'] = input('contact');
            $data['seller_name'] = input('seller_name');

            if ($hide_code) {
                $log_type = CfLog::OP_TYPE_BOOKSELL_SAVE;
                $is_success = Bookseller::update($data, ['seller_code' => $hide_code, 'tsg_code' => $this->adminInfo['tsg_code']])->result;
            } else {
                if (Bookseller::where(['seller_code' => $seller_code])->count() > 0) {
                    $this->error('书商代码已存在!');
                }
                if (preg_match("/[^0-9a-zA-Z]/", $seller_code)) {
                    $this->error(lang('seller_code_limit'));
                }
                $data['tsg_code'] = $this->adminInfo['tsg_code'];
                $data['seller_code'] = $seller_code;
                $log_type = CfLog::OP_TYPE_BOOKSELL_ADD;
                $is_success = Bookseller::create($data)->result;
            }

            if ($is_success !== false) {
                CfLog::addLog($log_type, $this->_user_info, ['book_id' => 0, 'db1' => $seller_code ?: $hide_code, 'db2' => $data['seller_name']]);
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
        $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();
        $this->assign('info', $info);
        $this->assign('tsg_info', $tsg_info);
        return view('edit');
    }

    public function dropAction()
    {
        $seller_code = input('seller_code');
        $mod_bookseller = d("Bookseller");
        $bookseller_info = $mod_bookseller->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();

        if (!$bookseller_info) {
            $this->error("删除失败:书商信息不存在");
        }
        $mod_destine_batch = d("DestineBatch");
        $destine_batch_info = $mod_destine_batch->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($destine_batch_info)) {
            $this->error("删除失败:已有预订批次使用此书商信息");
        }
        $mod_destine = d("Destine");
        $destine_info = $mod_destine->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($destine_info)) {
            $this->error("删除失败:已有预订信息使用此书商信息");
        }
        $mod_batch = d("Batch");
        $batch_info = $mod_batch->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($batch_info)) {
            $this->error("删除失败:已有验收批次使用此书商信息");
        }
        $mod_ys = d("Ys");
        $ys_info = $mod_ys->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($ys_info)) {
            $this->error("删除失败:已有验收信息使用此书商信息");
        }
        $mod_dck = d("Dck");
        $dck_info = $mod_dck->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->find();
        if (!empty($dck_info)) {
            $this->error("删除失败:已有馆藏信息使用此书商信息");
        }

        $is_success = $mod_bookseller->where("seller_code='$seller_code' AND tsg_code='{$this->_user_info["tsg_code"]}'")->delete();
        if ($is_success) {
            CfLog::addLog(CfLog::OP_TYPE_BOOKSELL_DROP, $this->_user_info, array("book_id" => 0, "db1" => $bookseller_info["seller_code"], "db2" => $bookseller_info["seller_name"]));
            $this->success("删除成功！");
        } else {
            $this->error("删除失败！");
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/7
 * Time: 9:54
 */

namespace app\admin\controller;

use app\admin\model\BmLog;
use app\admin\model\JdbmCnf;
use app\admin\model\Tsg;
use think\Lang;

/**
 * Class CatalogCnfController
 * @package app\admin\controller
 * 期刊通用参数
 */
class CatalogCnfController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize();
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Home/catalog_cnf.php']);
    }

    public function index_qkAction()
    {
        $catalog_cnf = config("catalog");
        $this->assign("type_list", $catalog_cnf["jd_cnf_list"]);
        return view('index');
    }

    /**
     * 通用参数设置
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
        $condition = ['tsg_code' => $this->adminInfo['tsg_code']];
        $params = $this->getQueryParams();//分页,排序,查询参数

        if ($params->search) {
            foreach ($params->search as $search) {
                if ($search['field'] == 'cnf_type') {
                    $condition[$search['field']] = $search['value'];
                } else {
                    $condition[$search['field']] = ['like', '%' . $search['value'] . '%'];
                }

            }
        }

        $list = JdbmCnf::getPageList($condition, $params->limit, $params->order);
        $count = JdbmCnf::where($condition)->count();
        return $this->echoPageData($list, $count);
    }

    /**
     * @return \think\response\View
     * 新增
     */
    public function addAction()
    {
        //REG

        $catalog_cnf = config("catalog");
        $cnf_type_list = $catalog_cnf["jd_cnf_list"];

        if (!$this->isPost) {
            $this->assign("cnf_type_list", $cnf_type_list);
            return view();
        }
        else {

            $add_data = [
                'cnf_type' => input('cnf_type'),
                'cnf_val' => input('cnf_val'),
                'remark' => input('remark')
            ];

            if (!$add_data["cnf_type"]) {
                $this->error(lang('cnf_type_require'));
            }

            if (!$add_data["cnf_val"]) {
                $this->error(lang('cnf_val_require'));
            }

            $add_data["cnf_type"] = (in_array($add_data["cnf_type"], $cnf_type_list) ? $add_data["cnf_type"] : array_shift($cnf_type_list));
            $add_data["tsg_code"] = $this->adminInfo["tsg_code"];

            $jdbm_cnf_info = JdbmCnf::create($add_data);

            if ($jdbm_cnf_info->result) {
                BmLog::addlog(BmLog::OP_TYPE_BASE_PARAM_ADD, $this->adminInfo, array("db1" => $jdbm_cnf_info['jdbm_cnf_id']));
                $this->success('新增成功!');
            }
            else {
                $this->error('新增失败!');
            }
        }
    }

    /**
     * @return \think\response\View
     * @throws \think\exception\DbException
     * 编辑
     */
    public function editAction()
    {
        $jdbm_cnf_id = input('jdbm_cnf_id/d');
        $catalog_cnf = config("catalog");
        $cnf_type_list = $catalog_cnf["jd_cnf_list"];
        $jdbm_cnf_info = JdbmCnf::get($jdbm_cnf_id);

        if (!$this->isPost) {

            if (!$jdbm_cnf_info) {
                $this->alertMsg(lang('not_found_data'));
            }

            if ($jdbm_cnf_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->alertMsg(lang('not_access_edit_data'));
            }
            $tsg_info = Tsg::where('tsg_code', $this->adminInfo['belong_tsg_code'])->find();

            $this->assign("tsg_info", $tsg_info);
            $this->assign("cnf_type_list", $cnf_type_list);
            $this->assign("info", $jdbm_cnf_info);
            return view();
        }
        else {
            if (!$jdbm_cnf_info) {
                $this->error(lang('not_found_data'));
            }

            if ($jdbm_cnf_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
                $this->error(lang('not_access_edit_data'));
            }


            $save_data = [
                'cnf_type' => input('cnf_type'),
                'cnf_val' => input('cnf_val'),
                'remark' => input('remark')
            ];

            if (!$save_data["cnf_type"]) {
                $this->error(lang('cnf_type_require'));
            }

            if (!$save_data["cnf_val"]) {
                $this->error(lang('cnf_val_require'));
            }

            $save_data["cnf_type"] = (in_array($save_data["cnf_type"], $cnf_type_list) ? $save_data["cnf_type"] : array_shift($cnf_type_list));
//            $is_success = $mod_jdbm_cnf->where(['jdbm_cnf_id' => $jdbm_cnf_id])->save($save_data);
            $where = ['jdbm_cnf_id' => $jdbm_cnf_id];
            $is_success = JdbmCnf::update($save_data, $where);

            if ($is_success !== false) {
                BmLog::addlog(BmLog::OP_TYPE_BASE_PARAM_SAVE, $this->adminInfo, array("db1" => $jdbm_cnf_id));
                $this->success('保存成功');
            }
            else {
                $this->error('保存失败!');
            }
        }
    }

    /**
     * @throws \think\exception\DbException
     * 删除
     */
    public function dropAction()
    {
        $jdbm_cnf_id = input('jdbm_cnf_id/d');
        $jdbm_cnf_info = JdbmCnf::get($jdbm_cnf_id);

        if (!$jdbm_cnf_info) {
            $this->error(lang('not_found_data'));
        }

        if ($jdbm_cnf_info["tsg_code"] != $this->adminInfo["tsg_code"]) {
            $this->error(lang('not_access_edit_data'));
        }

        $is_success = $jdbm_cnf_info->delete();

        if ($is_success) {
            BmLog::addlog(BmLog::OP_TYPE_BASE_PARAM_DROP, $this->adminInfo, array("db1" => $jdbm_cnf_id));
            $this->success('删除成功！');
        }
        else {
            $this->error('删除失败!');
        }
    }

}
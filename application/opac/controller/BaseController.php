<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/15
 * Time: 16:43
 */

namespace app\opac\controller;


use app\admin\model\Tsg;
use think\Controller;
use think\Lang;

class BaseController extends Controller
{
    protected $web_title = '创启云图书馆';
    protected $controller = '';
    protected $action = '';
    protected $_tsg_list = '';
    protected $_dz_info = '';
    // 异步操作返回值
    protected $ajaxResult = ['code' => 1, 'msg' => '操作成功!', 'data' => null];
//    protected $is_login = false;

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 
     */
    public function _initialize()
    {
        $this->assign('web_title', $this->web_title);
        // 加载语言包
        Lang::load([APP_PATH . 'lang' . DS . 'zh-cn' . DS . 'Opac.php']);

        $tsg_list = Tsg::field('tsg_code,tsg_name')->order("tsg_code")->select();
        if (!is_array($tsg_list)){
            $tsg_list = $tsg_list->toArray();
        }
        $tsg_list = array_merge(array(
            array("tsg_code" => "", "tsg_name" => "所有分馆")
        ), $tsg_list);

//        var_dump($tsg_list);exit();
//        $tsg_list = array_chunk($tsg_list, 4);
        $this->_dz_info = session('dz_info');
        $this->_tsg_list = $tsg_list;
        $this->controller = request()->controller();
        $this->action = request()->action();
        $this->assign('tsg_list',$this->_tsg_list);
        $this->assign('controller',strtolower($this->controller));
        $this->getMenu();
    }

    private function getMenu()
    {
        $nav = config('opac_menu');
        $top_menu = config('opac_menu_top');

        $this->assign("top_menu", $top_menu);
        $this->assign("top_menu_right", $nav);
        $this->display("index");
    }

    public function check_login()
    {
        $dz_info = session("dz_info");
        if (empty($dz_info) || empty($dz_info["dz_id"])) {
            return false;
        }
        return true;
    }

    /**
     * 统一返回列表查询参数对象
     * @return \stdClass
     */
    protected function getQueryParams()
    {
        $offset = input('offset/d');
        $limit = input('limit/d');
        $order = input('sort') . ' ' . input('order');
        $search = input('search');

        $params = new \stdClass();
        $params->limit = $offset . ',' . $limit;
        $params->order = $order;
        $params->search = $search ? json_decode($search, true) : null;
        return $params;
    }

    /**
     * 统一输出分页数据
     * @param $list @分页列表
     * @param int $total 总记录数
     * @param null $data 额外数据
     */
    public function echoPageData($list, $total = 0, $data = null)
    {
        echo json_encode(['rows' => $list, 'total' => $total, 'data' => $data]);
        exit;
    }

    public function echoSuccess($data = null, $msg = '操作成功')
    {
        $this->ajaxResult['msg'] = $msg;
        $this->ajaxResult['data'] = $data;
        echo json_encode($this->ajaxResult);
        exit();
    }

    public function echoError($msg = '操作失败', $data = null)
    {
        $this->ajaxResult['msg'] = $msg;
        $this->ajaxResult['code'] = 0;
        $this->ajaxResult['data'] = $data;
        echo json_encode($this->ajaxResult);
        exit();
    }

}
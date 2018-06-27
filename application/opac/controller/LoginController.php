<?php
/**
 * Created by PhpStorm.
 * Admin: Administrator
 * Date: 2018/3/7
 * Time: 17:15
 */

namespace app\opac\controller;


use app\admin\model\Dzgl;
use app\admin\model\Tsg;
use app\opac\model\OpacLog;

class LoginController extends BaseController
{
    protected $needLogin = false;

    public function indexAction()
    {
        //判断是否登录
        if ($this->check_login()) {
            // 已登陆则跳转首页
            $this->redirect(url('User/index'));
            exit();
        }
        $error = '';
        $is_success = $this->checkLogin($error);
        if ($is_success){
            // 已登陆则跳转首页
            $this->redirect(url('User/index'));
            exit();
        }
        $this->assign('error',$error);
        return view();
    }

    private function checkLogin(&$error)
    {
        if ($this -> request ->isPost())
        {
            $validate = validate('Login');
            if ($validate ->check($this->request->post('')) == false){
                $error = $validate->getError();
                return false;
            }
            $data['dz_code'] = $this -> request -> post('dz_code');
            $data['pwd'] = $this -> request -> post('pwd');
            $field = 'dz_id,tsg_code,dz_code,pwd,real_name,dz_status,portrait,end_time';
            $dz_info = Dzgl::field($field)-> where(['dz_code'=>['=',$data['dz_code']]]) -> find();

            if (!$dz_info) {
                $error = '读者账号不存在';
                return false;
            }
            if ($dz_info['pwd'] != md5($data['pwd'])) {
                $error = '读者密码错误';
                return false;
            }

            if ($dz_info->getData('end_time') < time()) {
                Dzgl::update(['dz_status'=>'暂停'],['dz_id'=>$dz_info['dz_id']]);
                $error = '用户已过有效期,账户已被禁用,请联系管理员!';
                return false;
            }

            $tsg_info = Tsg::field("tsg_code,tsg_name,tsg_close")->where(['tsg_code'=>$dz_info['tsg_code']])->find();

            if ($tsg_info["tsg_close"] == 1) {
                $error = '您所属的图书馆已被禁用,请联系管理员!';
                return false;
            }

            session("dz_info", $dz_info);
//            $this->_dz_info = $dz_info;
            OpacLog::addlog($dz_info["dz_id"], $dz_info["dz_code"]);
            return true;
        }

        $error = '';
        return false;
    }

    /**
     * 注销登录
     */
    public function logoutAction()
    {
        session('dz_info',null);
        $this -> redirect(url('opac/Index/index'));
        return true;
    }
}

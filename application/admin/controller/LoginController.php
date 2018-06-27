<?php
/**
 * Created by PhpStorm.
 * Admin: Administrator
 * Date: 2018/3/7
 * Time: 17:15
 */

namespace app\admin\controller;

use app\admin\model\SysLog;
use app\admin\model\User;
use think\Controller;

class LoginController extends Controller
{
    protected $needLogin = false;

    public function indexAction()
    {
        //判断是否登录
        if (User::isLogined()) {
            // 已登陆则跳转首页
            $this->redirect('public/index');
            exit();
        }

        $error = $admin_name = '';
        if ($this->isPost) {
            $admin_name = input('admin_name');
            $password = input('password');
            $success = User::adminLogin($admin_name, $password, $error);
            if ($success) {
                $this->redirect('public/index');
                exit;
            }
        }

        $this->assign([
            'error' => $error,
            'admin_name' => $admin_name
        ]);
        return view();
    }

    /**
     * 注销登录
     */
    public function logoutAction()
    {
        $adminInfo = ['user_id' => session('user_id'), 'user_name' => session('user_name'), 'tsg_code' => session('tsg_code'), 'real_name' => session('real_name')];
        $sys_log = [
            "db1" => $adminInfo["user_id"],
            "op_desc" => "操作:用户登出,登录名:{$adminInfo["user_name"]},操作员名称:{$adminInfo["real_name"]}"
        ];
        SysLog::addLog(SysLog::OP_TYPE_LOGIN_OUT, $adminInfo, $sys_log);
        session(null);
        cookie(null);

        $this->redirect('login/index');
        return true;
    }
}

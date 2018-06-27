<?php

namespace app\admin\model;

class User extends Base
{
    /**
     * 判断用户是否登录
     * @return bool
     */
    public static function isLogined()
    {
        $c_uid = cookie('user_id');
        $s_uid = session('user_id');
        $s_name = session('user_name');

        return !is_null($c_uid) && !is_null($s_uid) && !is_null($s_name);
    }

    /**
     * 管理员登录
     * @param $adminName
     * @param $password
     * @param string $error
     * @return bool
     * @throws \think\exception\DbException
     */
    public static function adminLogin($adminName, $password, &$error = '')
    {
        $adminInfo = self::get(['user_name' => $adminName]);

        if (is_null($adminInfo)) {
            $error = '用户不存在！';
            return false;
        }

        // 校验密码
        if (md5($password) != $adminInfo['user_pwd']) {
            $error = '密码错误，请重新输入！';
            return false;
        }

        if ($adminInfo['is_close']) {
            $error = '用户已被禁用！';
            return false;
        }

        if ($adminInfo["expiry_date"] < $adminInfo["last_login"]) {
            $error = "用户已过有效期,账户已被禁用,请联系管理员!";
            $adminInfo->update(['is_close' => 1], ['user_id' => $adminInfo['user_id']]);
            return false;
        }

        if (!self::check_ip_limit($adminInfo["ip_limit"])) {
            $error = "当前IP不允许登录";
            return false;
        }

        $tsgInfo = Tsg::get($adminInfo['belong_tsg_code']);
        if (!$tsgInfo) {
            $error = "您所属的图书馆不存在!";
            return false;
        }

        if ($tsgInfo["tsg_close"] == 1) {
            $error = "您所属的图书馆已被禁用,请联系管理员!";
            return false;
        }

        $adminInfo["tsg_code"] = $tsgInfo["tsg_code"];
        $adminInfo["tsg_name"] = $tsgInfo["tsg_name"];
        $adminInfo["is_main_tsg"] = ($tsgInfo["tsg_code"] == 999 ? true : false);
        $adminInfo["is_supper"] = (!empty($user_role_info) ? true : false);
        $adminInfo["is_admin"] = ($adminInfo["user_name"] == "admin" ? true : false);

        // 记录日志
        $sys_log = [
            "db1" => $adminInfo["user_id"],
            "op_desc" => "操作:用户登录,登录名:{$adminInfo["user_name"]},操作员名称:{$adminInfo["real_name"]}"
        ];
        SysLog::addLog(SysLog::OP_TYPE_LOGIN_IN, $adminInfo, $sys_log);

        self::update(['last_login' => time()], ['user_name' => $adminName]);
        self::setCookieAndSession($adminInfo);
        return true;
    }

    /**
     * 写入cookie session
     * @param $adminInfo
     */
    private static function setCookieAndSession($adminInfo)
    {
        //设置会话
        session('user_id', $adminInfo['user_id']);
        session('user_name', $adminInfo['user_name']);
        session('real_name', $adminInfo['real_name']);
        cookie('user_id', $adminInfo['user_id'], 3600 * 24 * 1);

        session('tsg_code', $adminInfo['tsg_code']);
        session('tsg_name', $adminInfo['tsg_name']);
        session('is_admin', $adminInfo['is_admin']);
        session('is_supper', $adminInfo['is_supper']);
        session('is_main_tsg', $adminInfo['is_main_tsg']);
    }

    private static function check_ip_limit($ip_limit)
    {
        if (empty($ip_limit)) {
            return true;
        }

        function convIp($ip_str)
        {
            $arr = explode(".", $ip_str);
            $num = 0;

            if (isset($arr[0])) {
                $num += intval($arr[0]) * 255 * 255 * 255;
            }

            if (isset($arr[1])) {
                $num += intval($arr[1]) * 255 * 255;
            }

            if (isset($arr[2])) {
                $num += intval($arr[2]) * 255;
            }

            if (isset($arr[3])) {
                $num += intval($arr[3]);
            }

            return $num;
        }

        $ip_list = explode("\n", $ip_limit);
        $ip_addr = $_SERVER["REMOTE_ADDR"];

        foreach ($ip_list as $item) {
            $index = strpos($item, "-");

            if ($index === false) {
                if ($ip_addr == $item) {
                    return true;
                }
            } else {
                $ip_beg = substr($item, 0, $index);
                $ip_end = substr($item, $index + 1);
                $ip_beg = convip($ip_beg);
                $ip_end = convip($ip_end);
                $ip_num = convip($ip_addr);
                if (($ip_beg <= $ip_num) && ($ip_num <= $ip_end)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function get_mt_id($user_id)
    {
        $mt_id = 0;
        $user_info = self::field('default_mt')->where('user_id', $user_id)->find();
        if (empty($user_info) || empty($user_info["default_mt"])) {
            $mt_list = Mt::get_list();

            if (!empty($mt_list)) {
                $mt_tmp = array_shift($mt_list);
                $mt_id = $mt_tmp["mt_id"];
                self::update(['default_mt' => $mt_id], ['user_id' => $user_id]);
            }
        } else {
            $mt_id = $user_info["default_mt"];
        }

        return $mt_id;
    }

    public function unique($user_name, $user_id = 0)
    {
        $user_id = $this->field("user_id")->where(array(
            "user_name" => $user_name,
            "user_id" => array("neq", $user_id)
        ))->find();

        if ($user_id) {
            return false;
        }
        return true;
    }

    public function _edit_role($user_id, $role_code, $method = "", $user_role_list = NULL)
    {
        if(empty($role_code)){
            $role_code = [];
        }
        $mod_user_role = d("User_role");
        if (empty($user_id)) {
            return false;
        }

        if ($method == "add") {
            if (empty($role_code)) {
                return true;
            }
            $insertList = [];
            foreach ($role_code as $item) {
                $insertList = array("user_id" => $user_id, "role_code" => $item);
            }
            if ($insertList) {
                $mod_user_role->insertAll($insertList);
            }
            return true;
        } else {
            if (empty($role_code)) {
                $is_success = $mod_user_role->where("user_id=$user_id")->delete();
                if ($is_success === false) {
                    return false;
                }
            }
            $exist_role = array();
            foreach ($user_role_list as $item) {
                if (!in_array($item["role_code"], $role_code)) {
                    $mod_user_role->where("user_role_id={$item["user_role_id"]}")->delete();
                } else {
                    $exist_role[] = $item["role_code"];
                }
            }
            $insertList = [];
            foreach ($role_code as $item) {
                if (!in_array($item, $exist_role)) {
                    $insertList[] = array("user_id" => $user_id, "role_code" => $item);
                }
            }
            if ($insertList) {
                $mod_user_role->insertAll($insertList);
            }
            return true;
        }
    }

    public function get_tpl_list($user_id)
    {
        if (empty($user_id)) {
            return array();
        }
        $tpl = $this->field("default_tpl")->where("user_id=$user_id")->find();
        return unserialize($tpl["default_tpl"]);
    }
}
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
// 应用公共文件
define("APP_NAME", 'app');
define("GROUP_NAME", 'Home');
//头像存放路径
//define("PORTRAIT_PATH",ROOT_PATH . 'public\uploads\portrait' . DS);
define("UPLOAD_PATH",ROOT_PATH . 'public\uploads'.DS);
//define('BASE_URL', \think\Request::instance()->domain());
define('PORTRAIT_NAME', 'dz_default2.jpg');
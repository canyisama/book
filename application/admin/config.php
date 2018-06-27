<?php

return [
    // 扩展函数文件
    'extra_file_list' => [
        THINK_PATH . 'helper' . EXT
    ],
    // 视图输出字符串内容替换
    'view_replace_str' => [
        '__static__' => dirname($_SERVER['SCRIPT_NAME']) . 'static/admin',
    ],
    'default_controller' => 'Public',
    'default_action' => 'index',

    // 域名配置
//    'base_url' => 'http://book_dev.com/',
    'base_url' => \think\Request::instance()->domain(),
    // 上传目录
    'upload_path' => '/uploads/',
];

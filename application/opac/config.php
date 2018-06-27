<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/5
 * Time: 17:31
 */
return [
    // 扩展函数文件
    'extra_file_list' => [
        THINK_PATH . 'helper' . EXT
    ],
    // 视图输出字符串内容替换
    'view_replace_str' => [
        '__static__' => dirname($_SERVER['SCRIPT_NAME']) . 'static/admin'
    ]

//    'default_controller' => 'Index',
//    'default_action' => 'index'
];
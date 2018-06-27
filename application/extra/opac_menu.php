<?php

return array(
	array(
		"text"  => "我的图书馆",
		'url' => '/Opac/User/index',
		"child" => array(
			array("text" => "用户首页", "url" => "/Opac/User/index"),
			array("text" => "图书收藏", "url" => "/Opac/Book/collect_list"),
			array("text" => "期望阅读记录", "url" => "/Opac/User/expect_log")
			)
		),
	array(
		"text"  => "借阅管理",
        'url' => '/Opac/Book/lend',
		"child" => array(
			array("text" => "当前借阅信息", "url" => "/Opac/Book/lend"),
			array("text" => "当前预约信息", "url" => "/Opac/Book/reser"),
			array("text" => "当前预借信息", "url" => "/Opac/Book/lend_reser"),
			array("text" => "历史借阅信息", "url" => "/Opac/Book/lend_history"),
			array("text" => "历史预约信息", "url" => "/Opac/Book/reser_history"),
			array("text" => "历史预借信息", "url" => "/Opac/Book/lend_reser_history")
			)
		),
	array(
		"text"  => "账号管理",
        'url' => '/Opac/User/info',
		"child" => array(
			array("text" => "个人信息", "url" => "/Opac/User/info"),
			array("text" => "修改密码", "url" => "/Opac/User/pwd"),
			array("text" => "超期罚款信息", "url" => "/Opac/Book/lend_out"),
			array("text" => "污损罚款信息", "url" => "/Opac/Book/lend_dirty"),
			array("text" => "丢失罚款信息", "url" => "/Opac/Book/lend_lose"),
			array("text" => "财务信息", "url" => "/Opac/Book/finan_list"),
			array("text" => "登录历史", "url" => "/Opac/Book/login_list")
			)
		)
	);

?>

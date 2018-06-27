<?php

return array(
	array(
		"text"  => "全文检索",
		'url'   => "/Opac/Index/index",
		"child" => array(
			array("text" => "检索首页", "url" => "/Opac/Index/index"),
			array("text" => "高级检索", "url" => "/Opac/Index/advanced")
			)
		),
	array(
		"text"  => "分类检索",
        'url'   => "/Opac/Index/clc",
		"child" => array(
			)
		),
	array(
		"text"  => "新书通报",
        'url'   => "/Opac/Index/newbook",
		"child" => array(
			)
		),
    array(
        "text"  => "排行榜",
        'url'   => "/Opac/Top/lend",
        "child" => array(
            array("text" => "借阅排行", "url" => "/Opac/Top/lend"),
            array("text" => "收藏排行", "url" => "/Opac/Top/collect")
        )
    ),
    array(
        "text"  => "公告信息",
        'url'   => "/Opac/Top/lend_msg",
        "child" => array(
            array("text" => "借阅超期公告", "url" => "/Opac/Top/lend_msg"),
            array("text" => "预约到书公告", "url" => "/Opac/Top/reser_msg"),
            array("text" => "馆内公告", "url" => "/Opac/Top/msg")
        )
    )
);

?>

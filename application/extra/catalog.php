<?php

return array(
    "pinyin_config_default" => "200a|9,225a|9,517a|9,701a|9,702a|9,711a|9,712a|9",
    "jd_cnf_list" => array("图书语种", "装帧方式", "货币类型", "介质类型", "图书来源"),
    "jd_cnf_default" => array(
        "货币类型" => array(
            array("CNY", "人民币"),
            array("USD", "美元"),
            array("HKD", "港币"),
            array("TWD", "新台币"),
            array("JPY", "日元"),
            array("KRW", "韩元"),
            array("SGD", "新加坡元"),
            array("MYR", "马来西亚吉特"),
            array("THB", "泰铢"),
            array("EUR", "欧元")
        ),
        "图书语种" => array(
            array("chi", "中文汉语"),
            array("eng", "英文"),
            array("fre", "法文"),
            array("rus", "俄文"),
            array("jpn", "日文"),
            array("gre", "德文"),
            array("sit", "藏文")
        ),
        "介质类型" => array(
            array("纸张", ""),
            array("电子数据", ""),
            array("DVD", ""),
            array("VCD", ""),
            array("CD", ""),
            array("缩微胶片", ""),
            array("磁带", ""),
            array("录像带", "")
        ),
        "图书来源" => array(
            array("订购", ""),
            array("邮购", ""),
            array("交换", ""),
            array("赠送", "")
        ),
        "装帧方式" => array(
            array("平装", ""),
            array("精装", ""),
            array("套装", ""),
            array("软精装", ""),
            array("线装", ""),
            array("筒装", "")
        )
    ),
    "booklab_print_cnf" => array(
        "paper_height" => "297",
        "paper_weight" => "210",
        "line_num" => "10",
        "col_num" => "5",
        "word_size" => "16",
        "bl_height" => "29.50",
        "bl_width" => "40",
        "bl_right" => "0",
        "bl_bottom" => "0",
        "bl_align" => "center",
        "bl_repeat" => "1",
        "bl_font" => "黑体",
        "bl_bold" => "bold",
        "border_show" => "0",
        "fields_cnf" => array(
            array("field_type" => "class_no", "field_order" => "1", "field_type_order" => "1", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "1"),
            array("field_type" => "zch", "field_order" => "2", "field_type_order" => "2", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "0"),
            array("field_type" => "fzno", "field_order" => "3", "field_type_order" => "3", "pos_sp" => "", "pos_cz" => "10", "word_qz" => ":", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "1"),
            array("field_type" => "barcode", "field_order" => "4", "field_type_order" => "4", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "0", "is_br" => "0"),
            array("field_type" => "custom", "field_order" => "5", "field_type_order" => "5", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "0", "is_br" => "0")
        )
    ),
    "booklab_print_cnf1" => array(
        "paper_height" => "297",
        "paper_weight" => "210",
        "line_num" => "10",
        "col_num" => "4",
        "word_size" => "16",
        "bl_height" => "29.50",
        "bl_width" => "50",
        "bl_right" => "0",
        "bl_bottom" => "0",
        "bl_align" => "center",
        "bl_repeat" => "1",
        "bl_font" => "黑体",
        "bl_bold" => "bold",
        "border_show" => "0",
        "fields_cnf" => array(
            array("field_type" => "class_no", "field_order" => "1", "field_type_order" => "1", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "1"),
            array("field_type" => "zch", "field_order" => "2", "field_type_order" => "2", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "0"),
            array("field_type" => "fzno", "field_order" => "3", "field_type_order" => "3", "pos_sp" => "", "pos_cz" => "10", "word_qz" => ":", "word_hz" => "", "font_size" => "16", "is_show" => "1", "is_br" => "1"),
            array("field_type" => "barcode", "field_order" => "4", "field_type_order" => "4", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "0", "is_br" => "0"),
            array("field_type" => "custom", "field_order" => "5", "field_type_order" => "5", "pos_sp" => "", "pos_cz" => "10", "word_qz" => "", "word_hz" => "", "font_size" => "16", "is_show" => "0", "is_br" => "0")
        )
    ),
    "marc_mapper_default" => array("title" => "200a", "bl_title" => "200d", "isbn" => "010a", "othertitle" => "200e", "fjtitle" => "200i", "fjno" => "200h", "firstauthor" => "200f", "otherauthor" => "200g", "publisher" => "210c", "pubplace" => "210a", "pubdate" => "210d", "series" => "225a", "seriesauthor" => "225g", "pages" => "215a", "edition" => "205a", "accessories" => "215e", "charts" => "215c", "binding" => "010b", "lags" => "101a", "size" => "215d", "gennotes" => "300a", "clc" => "690a", "abstract" => "330a", "subject" => "606a"),
    "marc_template_default" => array(
        "head" => "nam0",
        "fields" => array(
            array("name" => "001", "zsf" => "", "val" => "01########"),
            array("name" => "005", "zsf" => "", "val" => "20080801080808.0"),
            array(
                "name" => "010",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "d", "val" => "")
                )
            ),
            array(
                "name" => "100",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => "261508d2014    em y0chiy0110    ea")
                )
            ),
            array(
                "name" => "101",
                "zsf" => "0 ",
                "val" => array(
                    array("name" => "a", "val" => "chi")
                )
            ),
            array(
                "name" => "102",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => "CN"),
                    array("name" => "b", "val" => "110000")
                )
            ),
            array(
                "name" => "105",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => "ay   z   000ay")
                )
            ),
            array(
                "name" => "106",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => "r")
                )
            ),
            array(
                "name" => "200",
                "zsf" => "1 ",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "f", "val" => ""),
                    array("name" => "g", "val" => "")
                )
            ),
            array(
                "name" => "210",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "c", "val" => ""),
                    array("name" => "d", "val" => "")
                )
            ),
            array(
                "name" => "215",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "d", "val" => "")
                )
            ),
            array(
                "name" => "606",
                "zsf" => "0 ",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "x", "val" => ""),
                    array("name" => "y", "val" => ""),
                    array("name" => "z", "val" => "")
                )
            ),
            array(
                "name" => "690",
                "zsf" => "",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "v", "val" => "4")
                )
            ),
            array(
                "name" => "701",
                "zsf" => " 0",
                "val" => array(
                    array("name" => "a", "val" => ""),
                    array("name" => "4", "val" => "")
                )
            ),
            array(
                "name" => "801",
                "zsf" => " 0",
                "val" => array(
                    array("name" => "a", "val" => "CN"),
                    array("name" => "b", "val" => "weblib"),
                    array("name" => "c", "val" => "20080808")
                )
            )
        )
    )
);

?>

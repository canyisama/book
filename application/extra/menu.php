<?php

return array(
    "cf" => array(
        "text" => "menu_cf",
        "subtext" => "menu_cf",
        "default" => "welcome",
        "children" => array(
            array(
                "text" => "menu_cf_yd",
                "subtext" => "menu_cf_yd",
                "default" => "welcome",
                "children" => array(
                    "yd_batch_man" => array("text" => "yd_batch_man", "url" => "/Destine_batch/index"),
                    "book_destine" => array("text" => "book_destine", "url" => "/Destine/framework"),
                    "book_destine_import" => array("text" => "book_destine_import", "url" => "/Destine/upload"),
                    "destine_man" => array("text" => "destine_man", "url" => "/Destine/destine_man")
                )
            ),
            array(
                "text" => "menu_cf_ys",
                "subtext" => "menu_cf_ys",
                "default" => "welcome",
                "children" => array(
                    "batch_man" => array("text" => "accept_batch_man", "url" => "/Batch/index"),
                    "destine_ys" => array("text" => "destine_ys", "url" => "/Ys/framework1"),
                    "direct_ys" => array("text" => "direct_ys", "url" => "/Ys/framework"),
                    "ys_man" => array("text" => "ys_man", "url" => "/Ys/ys_man")
                )
            ),
            array(
                "text" => "menu_cf_report",
                "default" => "menu_cf_report",
                "children" => array(
                    "menu_report_dinggou" => array("text" => "menu_report_dinggou", "url" => "/Report/index/report_id/2"),
                    "menu_report_tuiding" => array("text" => "menu_report_tuiding", "url" => "/Report/index/report_id/3"),
                    "menu_report_yanshou" => array("text" => "menu_report_yanshou", "url" => "/Report/index/report_id/4"),
                    "menu_report_cuique" => array("text" => "menu_report_cuique", "url" => "/Report/index/report_id/5"),
                    "menu_report_chaoyan" => array("text" => "menu_report_chaoyan", "url" => "/Report/index/report_id/13"),
                    "menu_report_daoshu" => array("text" => "menu_report_daoshu", "url" => "/Report/index/report_id/6"),
                    "menu_report_caichan" => array("text" => "menu_report_caichan", "url" => "/Report/index/report_id/7"),
                    "menu_report_zongkuo" => array("text" => "menu_report_zongkuo", "url" => "/Report/index/report_id/8"),
                    "menu_report_clctj" => array("text" => "menu_report_clctj", "url" => "/Report/index/report_id/14"),
                    "menu_report_ystj" => array("text" => "menu_report_ystj", "url" => "/Report/index/report_id/15")
                )
            ),
            array(
                "text" => "menu_cf_param",
                "subtext" => "menu_cf_param",
                "default" => "welcome",
                "children" => array(
                    "bookseller" => array("text" => "bookseller_man", "url" => "/Bookseller/index"),
                    "cost_manage" => array("text" => "cost_manage", "url" => "/Cost/index")
                )
            ),
            array(
                "text" => "menu_cf_log",
                "default" => "menu_cf_log_cx",
                "children" => array(
                    "menu_cf_log_cx" => array("text" => "menu_cf_log_cx", "url" => "/Log/cflog"),
                    "menu_cf_log_tj" => array("text" => "menu_cf_log_tj", "url" => "/Report/index/report_id/32")
                )
            )
        )
    ),
    "bm" => array(
        "text" => "menu_bm",
        "default" => "bm_base",
        "children" => array(
            array(
                "text" => "menu_bm_child",
                "default" => "menu_bm_child",
                "children" => array(
                    "zj_bm" => array("text" => "menu_zj_bm", "url" => "/Catalog/framework"),
                    "booklab_print" => array("text" => "booklab_print", "url" => "/Booklab_print/index"),
                    "menu_bm_clcsearch" => array("text" => "menu_bm_clcsearch", "url" => "/ClcSearch/index")
                )
            ),
            array(
                "text" => "menu_bm_param",
                "default" => "menu_bm_param",
                "children" => array(
                    "catalog_cnf" => array("text" => "catalog_cnf", "url" => "/Catalog_cnf/index"),
                    "catalog_param" => array("text" => "catalog_param", "url" => "/Catalog_param/index"),
                    "doctype_set" => array("text" => "doctype_set", "url" => "/Doctype/index")
                )
            ),
            array(
                "text" => "menu_bm_db",
                "default" => "menu_bm_db",
                "children" => array(
                    "bm_base" => array("text" => "menu_bm_base", "url" => "/Catalog/index/1"),
                    "importbook" => array("text" => "importbook", "url" => "/Catalog/upload"),
                    "menu_bm_db_tlk" => array("text" => "menu_bm_db_tlk", "url" => "/Taolu_wh/index"),
                    "menu_bm_db_zch" => array("text" => "menu_bm_db_zch", "url" => "/Zchwh/index"),
                    "menu_bm_report_kq" => array("text" => "menu_bm_report_kq", "url" => "/Barcode_cq/index")
                )
            ),
            array(
                "text" => "menu_bm_report",
                "default" => "menu_bm_report",
                "children" => array(
                    "menu_bm_report_zk" => array("text" => "menu_bm_report_zk", "url" => "/Report/index/report_id/9"),
                    "menu_bm_report_gz" => array("text" => "menu_bm_report_gz", "url" => "/Report/index/report_id/10"),
                    "menu_bm_report_xs" => array("text" => "menu_bm_report_xs", "url" => "/Report/index/report_id/11"),
                    "menu_bm_report_bm" => array("text" => "menu_bm_report_bm", "url" => "/Report/index/report_id/1"),
                    "menu_bm_report_zj" => array("text" => "menu_bm_report_zj", "url" => "/Report/index/report_id/12")
                )
            ),
            array(
                "text" => "menu_bm_log",
                "default" => "menu_bm_log",
                "children" => array(
                    "menu_bm_log_cx" => array("text" => "menu_bm_log_cx", "url" => "/Log/bmlog"),
                    "menu_bm_log_tj" => array("text" => "menu_bm_log_tj", "url" => "/Report/index/report_id/33")
                )
            )
        )
    ),
    "dc" => array(
        "text" => "menu_dc",
        "default" => "gcdj_menu",
        "children" => array(
            array(
                "text" => "menu_dc_sub",
                "default" => "gcdj_menu",
                "children" => array(
                    "gcdj_menu" => array("text" => "gcdj_menu", "url" => "/Dcbat/reg"),
                    "batch_rc_menu" => array("text" => "batch_rc_menu", "url" => "/Dcbat/batch_reg"),
                    "gctc_menu" => array("text" => "gctc_menu", "url" => "/Dcbat/drop"),
                    "csqd_menu" => array("text" => "csqd_menu", "url" => "/Dcbat/check"),
                    "qdcl_menu" => array("text" => "qdcl_menu", "url" => "/Dcbat/handle"),
                    "tmzh_menu" => array("text" => "tmzh_menu", "url" => "/Dcbat/barcode_tab"),
                    "dck_batch_proc" => array("text" => "dck_batch_proc", "url" => "/Dcbat/batch_proc"),
                    "dck_dist" => array("text" => "dck_dist", "url" => "/Dcbat/dist")
                )
            ),
            array(
                "text" => "menu_dc_tsg",
                "default" => "dc_dispatch",
                "children" => array(
                    "dc_dispatch" => array("text" => "dc_dispatch", "url" => "/Dcbat/dispatch"),
                    "dc_batch_dispatch" => array("text" => "dc_batch_dispatch", "url" => "/Dcbat/batch_dispatch"),
                    "dc_dispatch_report" => array("text" => "dc_dispatch_report", "url" => "/Report/index/report_id/16"),
                    "dc_dispatch_report_clc" => array("text" => "dc_dispatch_report_clc", "url" => "/Report/index/report_id/17")
                )
            ),
            array(
                "text" => "menu_dc_report",
                "default" => "gcdj_menu",
                "children" => array(
                    "tsg_list" => ['text'=>'tsg_list','url'=>'/Dcbat/tsgInfo'],
                    "dc_report_List" => array("text" => "dc_report_List", "url" => "/Report/index/report_id/18"),
                    "dc_report_tj" => array("text" => "dc_report_tj", "url" => "/Report/index/report_id/22"),
                    "dc_clc" => array("text" => "dc_clc", "url" => "/Report/index/report_id/21"),
                    "dc_site_tj" => array("text" => "dc_site_tj", "url" => "/Report/index/report_id/19"),
                    "dc_status_tj" => array("text" => "dc_status_tj", "url" => "/Report/index/report_id/20"),
                    "dc_op_report" => array("text" => "dc_op_report", "url" => "/Report/index/report_id/23"),
                    "dc_op_tj" => array("text" => "dc_op_tj", "url" => "/Report/index/report_id/24"),
                    "dc_tj_tsg" => array("text" => "dc_tj_tsg", "url" => "/Report/index/report_id/50")
                )
            ),
            array(
                "text" => "menu_dc_log",
                "default" => "menu_dc_log",
                "children" => array(
                    "menu_dc_log_cx" => array("text" => "menu_dc_log_cx", "url" => "/Log/dclog"),
                    "menu_dc_log_tj" => array("text" => "menu_dc_log_tj", "url" => "/Report/index/report_id/25")
                )
            )
        )
    ),
    "dz" => array(
        "text" => "menu_dz",
        "default" => "lt_dzman",
        "children" => array(
            array(
                "text" => "menu_lt_dzman",
                "default" => "lt_dzman",
                "children" => array(
                    "lt_dzman" => array("text" => "lt_dzman", "url" => "/Dzgl/index"),
                    "lt_dztype" => array("text" => "lt_dztype", "url" => "/Dz_type/index"),
                    "lt_dzunitman" => array("text" => "lt_dzunitman", "url" => "/Dz_unit/index"),
//					"lt_dzunit_swap" => array("text" => "lt_dzunit_swap", "url" => "/Dz_unit/swap"),
                    "lt_dz_import" => array("text" => "lt_dz_import", "url" => "/Dzgl/upload"),
                    "lt_dz_list" => array("text" => "lt_dz_list", "url" => "/Report/index/report_id/41"),
                    "lt_volunt" => array("text" => "lt_volunt", "url" => "/Volunt/index"),
                    "lt_volunt_type" => array("text" => "lt_volunt_type", "url" => "/Volunt_type/index"),
                    "lt_volunt_ct" => array("text" => "lt_volunt_ct", "url" => "/Volunt_ct/index"),
                    "expect_verify" => array("text" => "expect_verify", "url" => "/Dzgl/expect_verify")
                )
            ),
            array(
                "text" => "menu_lt_finan",
                "default" => "finan_man",
                "children" => array(
                    "finan_man" => array("text" => "finan_man", "url" => "/Finance/index"),
                    "finan_list" => array("text" => "finan_list", "url" => "/Report/index/report_id/37"),
                    "finan_tj" => array("text" => "finan_tj", "url" => "/Report/index/report_id/38")
                )
            ),
            array(
                "text" => "menu_lt_report",
                "default" => "lt_report_reser",
                "children" => array(
                    "lt_dz_tj" => array("text" => "lt_dz_tj", "url" => "/Report/index/report_id/39"),
                    "lt_dz_inc_tj" => array("text" => "lt_dz_inc_tj", "url" => "/Report/index/report_id/40"),
                    "volunt_list" => array("text" => "volunt_list", "url" => "/Report/index/report_id/54"),
                    "volunt_tj" => array("text" => "volunt_tj", "url" => "/Report/index/report_id/55"),
                    "expect_tj" => array("text" => "expect_tj", "url" => "/Report/index/report_id/57")
                )
            ),
            array(
                "text" => "menu_dz_log",
                "default" => "menu_dz_log_cx",
                "children" => array(
                    "menu_dz_log_cx" => array("text" => "menu_dz_log_cx", "url" => "/Log/dzlog"),
                    "menu_dz_log_tj" => array("text" => "menu_dz_log_tj", "url" => "/Report/index/report_id/56")
                )
            )
        )
    ),
    "lt" => array(
        "text" => "menu_lt",
        "default" => "menu_lt_man",
        "children" => array(
            array(
                "text" => "menu_lt_man",
                "default" => "book_jh_menu",
                "children" => array(
                    "book_jh_menu" => array("text" => "book_jh_menu", "url" => "/Dzgl/operpage"),
                    "book_read_room" => array("text" => "book_read_room", "url" => "/Lend/readroom"),
                    "book_reser" => array("text" => "book_reser", "url" => "/Reser/opage"),
                    "book_reser_man" => array("text" => "book_reser_man", "url" => "/Reser/index"),
                    "book_lend_reser" => array("text" => "book_lend_reser", "url" => "/Lend_reser/opage"),
                    "book_lend_reser_man" => array("text" => "book_lend_reser_man", "url" => "/Lend_reser/index"),
                    "book_ch_menu" => array("text" => "book_ch_menu", "url" => "/Lend/outnotice"),
                    "lt_fineman" => array("text" => "lt_fineman", "url" => "/Fineman/index")
                )
            ),
            array(
                "text" => "menu_lt_query",
                "default" => "lt_query",
                "children" => array(
                    "lt_query" => array("text" => "lt_query", "url" => "/Lend/index"),
                    "lt_query_history" => array("text" => "lt_query_history", "url" => "/Lend/index_history")
                )
            ),
            array(
                "text" => "menu_lt_param",
                "default" => "lt_dztype",
                "children" => array(
                    "lt_doctype" => array("text" => "lt_doctype", "url" => "/Ltype/index"),
                    "lt_guizhe" => array("text" => "lt_guizhe", "url" => "/Ltrule/index"),
                    "lt_guizhe_inter" => array("text" => "lt_guizhe_inter", "url" => "/Ltrule/index_inter"),
                    "lt_holiday_set" => array("text" => "lt_holiday_set", "url" => "/Holiday/index")
                )
            ),
            array(
                "text" => "menu_lt_report",
                "default" => "lt_report_reser",
                "children" => array(
                    "lt_report_reser" => array("text" => "lt_report_reser", "url" => "/Report/index/report_id/42"),
                    "lt_report_reser_tj" => array("text" => "lt_report_reser_tj", "url" => "/Report/index/report_id/43"),
                    "lt_report_lend_reser" => array("text" => "lt_report_lend_reser", "url" => "/Report/index/report_id/44"),
                    "lt_report_lend_reser_tj" => array("text" => "lt_report_lend_reser_tj", "url" => "/Report/index/report_id/45"),
                    "read_room_list" => array("text" => "read_room_list", "url" => "/Report/index/report_id/52"),
                    "read_room_tj" => array("text" => "read_room_tj", "url" => "/Report/index/report_id/53"),
                    "lt_report_lend_list" => array("text" => "lt_report_lend_list", "url" => "/Report/index/report_id/46"),
                    "lt_report_lend_tj" => array("text" => "lt_report_lend_tj", "url" => "/Report/index/report_id/47"),
                    "lt_report_lend_book_top" => array("text" => "lt_report_lend_book_top", "url" => "/Report/index/report_id/48"),
                    "lt_report_lend_dz_top" => array("text" => "lt_report_lend_dz_top", "url" => "/Report/index/report_id/49"),
                    "lend_tj_tsg" => array("text" => "lend_tj_tsg", "url" => "/Report/index/report_id/51")
                )
            ),
            array(
                "text" => "menu_lt_log",
                "default" => "menu_lt_log_cx",
                "children" => array(
                    "menu_lt_log_cx" => array("text" => "menu_lt_log_cx", "url" => "/Log/ltlog"),
                    "menu_lt_log_tj" => array("text" => "menu_lt_log_tj", "url" => "/Report/index/report_id/34")
                )
            )
        )
    ),
    "qk" => array(
        "text" => "menu_qk",
        "default" => "qk_list_menu",
        "children" => array(
            array(
                "text" => "menu_qkyd",
                "default" => "qkyd_batch",
                "children" => array(
                    "qkyd_batch" => array("text" => "qkyd_batch", "url" => "/Qk_batch/index"),
                    "qkyd_zj" => array("text" => "qkyd_zj", "url" => "/Qk/framework"),
                    "qkyd_import" => array("text" => "qkyd_import", "url" => "/Qk/upload"),
                    "qkyd_man" => array("text" => "qkyd_man", "url" => "/Qk/qkyd_man")
                )
            ),
            array(
                "text" => "menu_qkjd",
                "default" => "qkjd_zj",
                "children" => array(
                    "qkjd_zj" => array("text" => "qkjd_zj", "url" => "/Qk/check"),
                    "qkjd_batch" => array("text" => "qkjd_batch", "url" => "/Qk/ys_batch_zd")
                )
            ),
            array(
                "text" => "menu_qkzd",
                "default" => "qkzd_zj",
                "children" => array(
                    "qkjd_zj" => array("text" => "qkzd_zj", "url" => "/Qk/qk_framework"),
                    "qkjd_batch" => array("text" => "qkzd_batch", "url" => "/Qk/zdbatch"),
                    "booklab_print" => array("text" => "booklab_print", "url" => "/Booklab_print/index_qk")
                )
            ),
            array(
                "text" => "menu_qk_param",
                "default" => "qk_base_param",
                "children" => array(
                    "qk_base_param" => array("text" => "qk_base_param", "url" => "/Catalog_cnf/index_qk"),
                    "qk_user_param" => array("text" => "qk_user_param", "url" => "/Qk_param/index"),
                    "qk_param_pubcycle" => array("text" => "qk_param_pubcycle", "url" => "/Qk_cycle/index")
                )
            ),
            array(
                "text" => "menu_qk_report",
                "default" => "qk_report_order",
                "children" => array(
                    "qk_report_yd" => array("text" => "qk_report_yd", "url" => "/Report/index/report_id/26"),
                    "qk_report_ys" => array("text" => "qk_report_ys", "url" => "/Report/index/report_id/27"),
                    "qk_report_zd" => array("text" => "qk_report_zd", "url" => "/Report/index/report_id/28"),
                    "qk_report_tj_yd" => array("text" => "qk_report_tj_yd", "url" => "/Report/index/report_id/29"),
                    "qk_report_tj_ys" => array("text" => "qk_report_tj_ys", "url" => "/Report/index/report_id/30"),
                    "qk_report_tj_zd" => array("text" => "qk_report_tj_zd", "url" => "/Report/index/report_id/31")
                )
            ),
            array(
                "text" => "menu_qk_log",
                "default" => "qk_log_query",
                "children" => array(
                    "qk_log_query" => array("text" => "qk_log_query", "url" => "/Log/qklog"),
                    "qk_log_tj" => array("text" => "qk_log_tj", "url" => "/Report/index/report_id/35")
                )
            )
        )
    ),
    "sys" => array(
        "text" => "menu_sys",
        "default" => "user_manage",
        "children" => array(
            array(
                "text" => "menu_sys_canshu",
                "default" => "user_manage",
                "children" => array(
                    "user_manage" => array("text" => "user_manage", "url" => "/User/index"),
                    "role_manage" => array("text" => "role_manage", "url" => "/Role/index"),
//                    "tsg_manage" => array("text" => "tsg_manage", "url" => "/Tsg/index"),
                    "tsg_config" => array("text" => "tsg_config", "url" => "/Tsg/config"),
                    "tsg_site_manage" => array("text" => "tsg_site_manage", "url" => "/Tsg_site/index"),
                    "z3950_addr" => array("text" => "z3950_addr", "url" => "/Z3950/index"),
                    "pinyinman" => array("text" => "pinyinman", "url" => "/Pinyinman/index"),
                    "pubinfoman" => array("text" => "pubinfoman", "url" => "/Pubinfoman/index"),
                    "sys_tsginfo" => array("text" => "sys_tsginfo", "url" => "/Tsg/info"),
//                    "sys_databack" => array("text" => "sys_databack", "url" => "/Databack/index")
                )
            ),
            array(
                "text" => "menu_sys_opac",
                "default" => "user_manage",
                "children" => array(
                    "opac_cnf" => array("text" => "opac_cnf", "url" => "/Opac_cnf/cnf"),
                    "opac_msg" => array("text" => "opac_msg", "url" => "/Annou/index")
                )
            ),
            array(
                "text" => "menu_marctype",
                "default" => "marctype_set",
                "children" => array(
                    "marctype_set" => array("text" => "marctype_set", "url" => "/Marctype/index"),
                    "marc_field" => array("text" => "marc_field", "url" => "/Marctype/fieldcnf"),
                    "marc_index_define" => array("text" => "marc_index_define", "url" => "/Marctype/indexset"),
                    "marc_team_field" => array("text" => "marc_team_field", "url" => "/Marctype/config"),
                    "marc_template" => array("text" => "marc_template", "url" => "/Marctype/tpl"),
                    "marc_index_rehab" => array("text" => "marc_index_rehab", "url" => "/Marctype/index_rehab"),
                )
            ),
            array(
                "text" => "menu_sys_email",
                "default" => "email_man",
                "children" => array(
                    "email_man" => array("text" => "email_man", "url" => "/Email/index"),
                    "email_tpl" => array("text" => "email_tpl", "url" => "/Email/tpl"),
                    "email_config" => array("text" => "email_config", "url" => "/Email/config")
                )
            ),
            array(
                "text" => "menu_sys_sms",
                "default" => "sms_man",
                "children" => array(
                    "sms_man" => array("text" => "sms_man", "url" => "/Sms/index"),
                    "sms_tpl" => array("text" => "sms_tpl", "url" => "/Sms/tpl"),
                    "sms_config" => array("text" => "sms_config", "url" => "/Sms/config")
                )
            ),
            array(
                "text" => "menu_sys_log",
                "default" => "sys_log_query",
                "children" => array(
                    "menu_sys_log_cx" => array("text" => "menu_sys_log_cx", "url" => "/Log/syslog"),
                    "menu_sys_log_tj" => array("text" => "menu_sys_log_tj", "url" => "/Report/index/report_id/36")
                )
            )
        )
    )
);

?>

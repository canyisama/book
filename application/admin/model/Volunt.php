<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-27
 * Time: 15:31
 */

namespace app\admin\model;


class Volunt extends Base
{
    const LT_STATUS_ON = 1;
    const LT_STATUS_FINISH = 2;
    const LT_STATUS_COMMENT = 3;

    public function getStatusList()
    {
        return array(self::LT_STATUS_ON => "签到", self::LT_STATUS_FINISH => "完成", self::LT_STATUS_COMMENT => "已评分");
    }

    public function addDefaultData($tsg_code)
    {
        if (!$tsg_code) {
            self::error("分馆代码不能为空") ;
        }

        $type_list = array(
            array("type_name" => "流通值勤", "order_num" => "1"),
            array("type_name" => "书库排架上架", "order_num" => "2"),
            array("type_name" => "阅览室整理", "order_num" => "3"),
            array("type_name" => "打扫清洁", "order_num" => "4"),
            array("type_name" => "读者咨询服务", "order_num" => "5"),
            array("type_name" => "编目加工值勤", "order_num" => "6"),
            array("type_name" => "其他", "order_num" => "7")
        );

        foreach ($type_list as $key => $item ) {
            $type_list[$key]["tsg_code"] = $tsg_code;
        }

        $mod_volunt_type = d("Volunt_type");
        $mod_volunt_type->insertAll($type_list);
        $ct_list = array(
            array("ct_name" => "基本评价分", "order_num" => "1", "ct_cnt" => "50"),
            array("ct_name" => "准时到岗", "order_num" => "2", "ct_cnt" => "10"),
            array("ct_name" => "工作积极、热情", "order_num" => "3", "ct_cnt" => "10"),
            array("ct_name" => "遵守纪律", "order_num" => "4", "ct_cnt" => "10"),
            array("ct_name" => "积极提建议", "order_num" => "5", "ct_cnt" => "10"),
            array("ct_name" => "富有团体精神", "order_num" => "5", "ct_cnt" => "10")
        );

        foreach ($ct_list as $key => $item ) {
            $ct_list[$key]["tsg_code"] = $tsg_code;
        }

        $mod_volunt_ct = d("Volunt_ct");
        $mod_volunt_ct->insertAll($ct_list);
        return true;
    }
}
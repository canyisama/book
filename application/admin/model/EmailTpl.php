<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/22
 * Time: 10:40
 */

namespace app\admin\model;


class EmailTpl extends Base
{
    const TPL_TYPE_RESER = 1;
    const TPL_TYPE_LEND_OUT = 2;

    public static function get_tpl_type_list()
    {
        return array(self::TPL_TYPE_RESER => "图书预约", self::TPL_TYPE_LEND_OUT => "借阅超期");
    }

    public static function get_notice_type_list()
    {
        return array(self::TPL_TYPE_RESER => "图书预约通知", self::TPL_TYPE_LEND_OUT => "借阅超期通知");
    }

    public function getTplTypeAttr($value)
    {
        $type = self::get_tpl_type_list();
        return $type[$value] ?: '-';
    }


    /**
     * @param int $tpl_type  模板类型
     * @return array
     * 获取标签列表
     */
    public static function get_lable_list($tpl_type = 0)
    {
        $lable_list = array("{tsg}" => "分馆名称", "{date}" => "发件日期");

        if ($tpl_type == self::TPL_TYPE_RESER) {
            $lable_list["{barcode}"] = "到书条码";
            $lable_list["{title}"] = "书目题名";
            $lable_list["{mustdate}"] = "取书截止日期";
            $lable_list["{dzname}"] = "读者姓名";
            $lable_list["{unitname}"] = "单位名称";
        }
        else if ($tpl_type == self::TPL_TYPE_LEND_OUT) {
            $lable_list["{barcode}"] = "图书条码";
            $lable_list["{title}"] = "书目题名";
            $lable_list["{mustdate}"] = "超期日期";
            $lable_list["{dzname}"] = "读者姓名";
            $lable_list["{unitname}"] = "单位名称";
        }

        return $lable_list;
    }

    /**
     * @param $tsg_code             @分馆代码
     * @param $tpl_type             @模板类型
     * @param int $email_tpl_id     @模板id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     *
     * 模板类型是否唯一
     */
    public static function unique($tsg_code, $tpl_type, $email_tpl_id = 0)
    {
        $where = [
            'tsg_code' => $tsg_code,
            'tpl_type' => $tpl_type,
            'email_tpl_id' => [ 'neq',$email_tpl_id]
        ];
        $email_tpl_id = self::field("email_tpl_id")->where($where)->find();

        return $email_tpl_id ? false : true;
    }


    /**
     * @param $tsg_code       @分馆代码
     * @param array $option  @选项参数
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取邮件模板列表
     */
    public static function get_list($tsg_code, $option = array())
    {
        $where = (isset($option["where"]) ? $option["where"] : array());
        $where["tsg_code"] = $tsg_code;
        $order = (isset($option["order"]) ? $option["order"] : "email_tpl_id desc");
        $field = (isset($option["field"]) ? $option["field"] : "email_tpl_id,tpl_name,tpl_type");
        return self::field($field)->where($where)->order($order)->select();
    }


    /**
     * @param $tpl_info
     * @param array $parse_data
     * @param int $tpl_type
     * 解析模板
     */
    public static function parseTpl(&$tpl_info, $parse_data = array(), $tpl_type = 0)
    {
        $label_list = self::get_lable_list($tpl_type);
        $label_list = array_keys($label_list);
        $replace_list = array();

        import("String",EXTEND_PATH,'.class.php');

        foreach ($label_list as $item ) {
            $tmp_zd = str_replace("{", "", $item);
            $tmp_zd = str_replace("}", "", $tmp_zd);
            $val_tmp = (isset($parse_data[$tmp_zd]) ? $parse_data[$tmp_zd] : "");

            if ($tmp_zd == "title") {
                $val_tmp = \String::msubstr($val_tmp, 0, 10);
            }

            $replace_list[] = $val_tmp;
        }

        $tpl_info["tpl_subject"] = str_replace($label_list, $replace_list, $tpl_info["tpl_subject"]);
        $tpl_info["tpl_body"] = str_replace($label_list, $replace_list, $tpl_info["tpl_body"]);
    }

}
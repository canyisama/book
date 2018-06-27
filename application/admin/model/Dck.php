<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 17:00
 */

namespace app\admin\model;


/**
 * Class Dck
 * @package app\admin\model
 * 书目馆藏状态模型类
 */
class Dck extends Base
{
    const DT_TYPE_BOOK = 1;
    const DT_TYPE_QK = 2;
    public $errorData = "";

    /**
     * @param $status @图书状态
     * @return bool
     * 判断图书是否可借阅
     */
    public static function isLend($status)
    {
        $status_list = config("dck.status_list");
        return $status_list[$status] == 1;
    }

    public static function getStatusList()
    {
        $status_list = config("dck.status_list");
        return $status_list;
    }

    /**
     * @return \think\model\relation\BelongsTo
     * 关联图书书目表
     */
    public function book()
    {
        return $this->belongsTo('Book', 'book_id', 'book_id');
    }

    public function isUniqueBarcode($tsg_code, $barcode, $pkey_id = 0, $pkey_name = "dck_id")
    {
        if (empty($tsg_code) || empty($barcode)) {
            return false;
        }
        $barcode_str = "";
        if (is_array($barcode)) {
            foreach ($barcode as $key => $item) {
                $barcode[$key] = "'$item'";
            }
            $barcode_str = implode(",", $barcode);
        } else {
            $barcode_str = "'$barcode'";
        }

        $where_dck = array();
        $where_dck[$pkey_name] = array("neq", $pkey_id);
        $where_dck[] = "barcode in(binary $barcode_str)";
        $where_str = $pkey_name . '<>' . $pkey_id . ' and ' . "barcode in(binary $barcode_str)";
        $dck_list = self::field("barcode")->where($where_str)->select();
        if (!empty($dck_list)) {
            $re = array();
            $re["type"] = "dck";
            $re["msg"] = "馆藏库已存在相同条码";
            $re["code_list"] = array();
            foreach ($dck_list as $item) {
                $re["code_list"][] = $item["barcode"];
            }
            $this->errorData = $re;
            return false;
        }

        $mod_dz = d("dzgl");
        $where_dz = array();
        $where_dz[] = "dz_code in(binary $barcode_str)";
        $dz_list = $mod_dz->field("dz_code")->where("dz_code in(binary $barcode_str)")->select();
        if (!empty($dz_list)) {
            $re = array();
            $re["type"] = "dz";
            $re["msg"] = "读者库已存在相同条码";
            $re["code_list"] = array();

            foreach ($dz_list as $item) {
                $re["code_list"][] = $item["dz_code"];
            }

            $this->errorData = $re;
            return false;
        }

        return true;
    }
}
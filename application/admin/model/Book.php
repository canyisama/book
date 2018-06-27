<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/17
 * Time: 18:43
 */

namespace app\admin\model;


class Book extends Base
{
    /**
     * @param $book_info @图书的信息
     * @return string
     * 获取完整的图书标题 --- 书名 + 并列题名 + 其他责任者 + 分辑号 + 分辑名
     */
    public static function getFullTitle($book_info)
    {
        $full_title = $book_info["title"] .
            ($book_info["bl_title"] ? "=" . $book_info["bl_title"] : "") .
            ($book_info["othertitle"] ? ":" . $book_info["othertitle"] : "") .
            ($book_info["fjno"] ? "." . $book_info["fjno"] : "") .
            ($book_info["fjtitle"] ? "," . $book_info["fjtitle"] : "");
        return $full_title;
    }

    /**
     * @return \think\model\relation\HasMany
     * 关联图书馆藏表
     */
    public function dck()
    {
        return $this->hasMany('Dck', 'book_id', 'book_id');
    }

    public function lend()
    {
        return $this->hasMany('Lend', 'book_id', 'book_id');
    }

    public function bookCollect()
    {
        return $this->hasMany('app\opac\model\BookCollect', 'book_id', 'book_id');
    }

    static public function get_indexrel($mt_id, $option = array())
    {
        if (empty($mt_id)) {
            return false;
        }

        $field = (isset($option["fileds"]) ? $option["fileds"] : "");
        $sour_mod = (isset($option["sour_mod"]) ? $option["sour_mod"] : "Book");
        $mod_indexrel = d("indexrel");
        $index_list = $mod_indexrel->field($field)->where("sour_mod='$sour_mod' AND mt_id=$mt_id")->select();
        $arr_index = array();

        foreach ($index_list as $item) {
            $item["sour_mfield"] = explode(",", $item["sour_mfield"]);
            $arr_index[$item["sour_field"]] = $item;
        }

        return $arr_index;
    }

    static public function conv_index_data($book_id, &$simple_data, &$marc_data, &$indexrel, $data_encode = "utf-8")
    {
        if (empty($book_id)) {
            return false;
        }

        /*if (!class_exists("Scws")) {
            import("@.Extend.Scws.Scws");
        }*/

        import('Isbn\IsbnBase', EXTEND_PATH, '.class.php');
        $no_ext_fields = array("tsg_code", "pubdate", "cataloger", "catatime");
        $data_list = array();

        foreach ($indexrel as $key => $item) {
            if (in_array($key, $no_ext_fields)) {
                $data_list[$item["order_mod"]] = array("bid" => $book_id, "val" => isset($simple_data[$key]) ? $simple_data[$key] : "");
            } else {
                $mval_tmp = array();

                foreach ($item["sour_mfield"] as $item1) {
                    if (isset($marc_data[$item1]) && !empty($marc_data[$item1])) {
                        foreach ($marc_data[$item1] as $key2 => $item2) {
                            if ($key == "isbn") {
                                $marc_data[$item1][$key2] = \IsbnBase::convIsbnCode($item2);
                            } else if ($key == "clc") {
                                $marc_data[$item1][$key2] = \IsbnBase::formatClc($item2);
                            } else {
                                $marc_data[$item1][$key2] = $item2;
                            }
                        }

                        $mval_tmp[] = implode(" , ", $marc_data[$item1]);
                    }
                }

                $mval_tmp = implode(" ; ", $mval_tmp);

                if ($data_encode != "utf-8") {
                    $mval_tmp = mb_convert_encoding($mval_tmp, "utf-8", $data_encode);
                }

                // TODO
                if (($key == "title") || ($key == "subject")) {
                    //$mval_tmp = \Scws::segment_str($mval_tmp);
                }

                $data_list[$item["dest_mod"]] = array("bid" => $book_id, "val" => $mval_tmp);
                $data_list[$item["order_mod"]] = array("bid" => $book_id, "val" => isset($simple_data[$key]) ? $simple_data[$key] : "");
            }
        }

        return $data_list;
    }
}
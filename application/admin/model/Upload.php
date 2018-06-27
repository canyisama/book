<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/21
 * Time: 13:45
 */

namespace app\admin\model;


class Upload extends Base
{
    const UP_TYPE_TAOLU = 1;
    const UP_TYPE_DESTINE = 2;
    const UP_TYPE_BOOK_ACCEPT = 3;
    const UP_TYPE_QKYD = 4;
    const UP_TYPE_DZ_IMPORT = 5;
    const UP_TYPE_DZ_UNIT_IMPORT = 6;

    public static function clear_disuse_file()
    {
        $clear_time = mstrtotime(date("Y-m-d")) - 864000;
        $file_list = self::where("(is_add=1 AND disuse_time<=$clear_time) OR (up_type=" . self::UP_TYPE_DZ_IMPORT . " AND add_time<=$clear_time)")->select();
        self::where("(is_add=1 AND disuse_time<=$clear_time) OR (up_type=" . self::UP_TYPE_DZ_IMPORT . " AND add_time<=$clear_time)")->delete();

        foreach ($file_list as $item) {
            if (file_exists($item["file_path"])) {
                unlink($item["file_path"]);
            }

            if (file_exists($item["err_file"])) {
                unlink($item["err_file"]);
            }

            if (file_exists($item["err_file1"])) {
                unlink($item["err_file1"]);
            }
        }
    }
}
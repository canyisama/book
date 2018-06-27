<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/17
 * Time: 13:58
 */

namespace app\opac\model;

use app\admin\model\Base;


/**
 * Class BookCollect
 * @package app\common\model
 * 图书收藏模型类
 */
class BookCollect extends Base
{
    public function book()
    {
        return $this->belongsTo('app\admin\model\Book', 'book_id', 'book_id');
    }
    public function collect($dz_id, $book_id)
    {
        if (!$dz_id) {
            $this->error = "读者ID不能为空";
            return false;
        }

        if (!$book_id) {
            $this->error = "书目ID不能为空";
            return false;
        }

        $dz_info = Dzgl::field('dz_id')->where(['dz_id'=>$dz_id])->find();

        if (!$dz_info) {
            $this->error = "数据库未找到读者信息";
            return false;
        }

        $is_exist = self::field('dz_id')->where(['dz_id'=>$dz_id,'book_id'=>$book_id])->find();

        if ($is_exist) {
            $this->error = "该图书已收藏,请勿重复收藏";
            return false;
        }

         $book_info = Book::field("book_id,title,isbn,firstauthor,clc,publisher,pubplace,pubdate")->where(['book_id'=>$book_id])->find();

        $book_info = $book_info->toArray();
        if (!$dz_info) {
            $this->error = "数据库未找到图书信息";
            return false;
        }

        $cnt = self::where(['dz_id'=>$dz_id])->count();

        if (100 <= $cnt) {
            self::where(['dz_id'=>$dz_id])->order('add_time')->limit(1)->delete();
        }

        $book_info["dz_id"] = $dz_id;
        $book_info["add_time"] = time();

        $is_success = self::create($book_info,true)->result;

        if ($is_success === false) {
            $this->error = "增加图书收藏数据失败";
        }

        return $is_success;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 10:23
 */

namespace app\admin\model;

/**
 * Class LendReser
 * @package app\admin\model
 * 图书预借模型类
 */
class LendReser extends Base
{
    const LEND_RESER_STATUS_ADD = 1;
    const LEND_RESER_STATUS_OUT = 2;
    const LEND_RESER_STATUS_CANCEL = 3;
    const LEND_RESER_STATUS_FINISH = 4;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'lend_reser_time'    =>  'timestamp',
        'must_time'    =>  'timestamp',
        'take_time'    =>  'timestamp'
    ];

    private  static $type_arr = [
        self::LEND_RESER_STATUS_ADD => "预借中",
        self::LEND_RESER_STATUS_OUT => "超时",
        self::LEND_RESER_STATUS_CANCEL => "取消预借",
        self::LEND_RESER_STATUS_FINISH => "已取书"
    ];


    /**
     * @param int $all
     * @return array|mixed|string
     * 预借状态数组
     */
    public static function getType($all = 0)
    {
        $type_lists = self::$type_arr;
        if ($all === 0){
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

//    /**
//     * @param $value
//     * @return mixed|string
//     * 预借状态读取器
//     */
//    protected function getLendReserStatusAttr($value)
//    {
//        $type = [
//            self::LEND_RESER_STATUS_ADD => "预借中",
//            self::LEND_RESER_STATUS_OUT => "超时",
//            self::LEND_RESER_STATUS_CANCEL => "取消预借",
//            self::LEND_RESER_STATUS_FINISH => "已取书"
//        ];
//        return isset($type[$value]) ? $type[$value] : '无此类型';
//    }

    protected function getTakeTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    /**
     * @param $dz_code      @读者证号
     * @param $dck_id       @dckID
     * @return bool|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 预借
     */
    public function bookLendReserve($dz_code, $dck_id)
    {
        if (!$dz_code) {
            $this->error = "无效的读者ID,无法预借";
            return false;
        }

        if (!$dck_id) {
            $this->error = "无效的书目信息,无法预借";
            return false;
        }

        $field = "dz_id,tsg_code,dz_code,dz_type_code,lend_reser_num,curr_lend_reser_num,real_name,unit_name,phone_mob,email";
        $dz_info = Dzgl::field($field)->where(['dz_code'=>$dz_code])->find();

        if (!$dz_info) {
            $this->error = "未找到读者信息";
            return false;
        }

        $dz_type_info = DzType::where(['tsg_code'=>$dz_info['tsg_code'],'dz_type_code'=>$dz_info['dz_type_code']])->find();

        if (empty($dz_type_info)) {
            $this->error = "未找到读者类型信息";
            return false;
        }

        $field = 'dck_id,book_id,barcode,status,tsg_site_code,lt_type,tsg_code,tsg_code_has';
        $dck_info = Dck::field($field)->where(['dck_id'=>$dck_id])->find();

        if (!$dck_info) {
            $this->error = "未找到馆藏信息";
            return false;
        }

        if ($dck_info["tsg_code_has"] != $dck_info["tsg_code"]) {
            $this->error = "图书不在所属馆,无法预借";
            return false;
        }

        $reser_keep_info = Reser::getKeepInfo($dck_info["tsg_code"], $dck_info["dck_id"]);

        if ($reser_keep_info !== false) {
            $this->error = "本馆藏已为预约读者".$reser_keep_info["real_name"]."【".$reser_keep_info["dz_code"]."】保留,无法预借";
            return false;
        }

        $tsg_code = $dck_info["tsg_code"];
        if (($dz_info["tsg_code"] != $tsg_code) && ($dz_type_info["is_inter"] != 1)) {
            $this->error = "其他馆读者,未开通馆际读者,无法预借";
            return false;
        }

        $where = [];
        $where = [
            'tsg_code' => $dck_info['tsg_code'],
            'tsg_site_code' => $dck_info['tsg_site_code']
        ];
        $tsg_site_info = TsgSite::field("tsg_site_code,site_name")->where($where)->find();

        if (!$tsg_site_info) {
            $this->error = "未找到馆藏地址信息,无法预借";
            return false;
        }

        $where = [];
        $where = [
            'tsg_code' => $dck_info['tsg_code'],
            'ltype_code'  => $dck_info['lt_type']
        ];

        $ltype_info = Ltype::field("ltype_code,ltype_name")->where($where)->find();

        if (!$tsg_site_info) {
            $this->error = "未找到流通类型信息,无法预借";
            return false;
        }

        $book_info = Book::field("book_id,title,bl_title,othertitle,fjno,fjtitle,clc,isbn,price_ms")->where(['book_id'=>$dck_info['book_id']])->find();

        if (!$book_info) {
            $this->error = "数据库未找到对应的书目信息,无法预借";
            return false;
        }

        if (!Dck::isLend($dck_info["status"])) {
            $this->error = "本书当前馆藏状态:【".$dck_info["status"]."】,无法预借";
            return false;
        }

        if (!$dz_type_info["is_lend_reser"]) {
            $this->error = "读者未开通预借";
            return false;
        }

        if ($dz_type_info["lend_reser_max_days"] <= $dz_info["curr_lend_reser_num"]) {
            $this->error = "读者最大预借册数【".$dz_type_info["lend_reser_max_days"]."】,当前预借册数【".$dz_info["curr_lend_reser_num"]."】";
            return false;
        }


        $time_now = time();
        $must_time = $time_now + ($dz_type_info["lend_reser_hold_days"] * 86400);

        $holi_id = Holiday::disDate($dz_info["tsg_code"], $must_time);

        $add_data = array(
            "tsg_code" => $tsg_code,
            "dz_id" => $dz_info["dz_id"],
            "dz_code" => $dz_info["dz_code"],
            "dz_type_code" => $dz_type_info["dz_type_code"],
            "dz_type_name" => $dz_type_info["dz_type_name"],
            "tsg_site_code" => $tsg_site_info["tsg_site_code"],
            "site_name" => $tsg_site_info["site_name"],
            "ltype_code" => $ltype_info["ltype_code"],
            "ltype_name" => $ltype_info["ltype_name"],
            "real_name" => $dz_info["real_name"],
            "unit_name" => $dz_info["unit_name"],
            "phone_mob" => $dz_info["phone_mob"],
            "email" => $dz_info["email"],
            "title" => Book::getFullTitle($book_info),
            "clc" => $book_info["clc"],
            "isbn" => $book_info["isbn"],
            "book_id" => $book_info["book_id"],
            "dck_id" => $dck_info["dck_id"],
            "barcode" => $dck_info["barcode"],
            "lend_reser_status" => self::LEND_RESER_STATUS_ADD,
            "price_ms" => $book_info["price_ms"],
            "lend_reser_time" => $time_now,
            "is_inter_lend_reser" => $dz_info["tsg_code"] != $tsg_code ? 1 : 0,
            "must_time" => $must_time);


        $is_success = self::create($add_data)->result;

        if ($is_success === false) {
            $this->error = "插入预借数据失败";
            return false;
        }

        $save_data = array(
            "lend_reser_num" => $dz_info["lend_reser_num"] + 1,
            "curr_lend_reser_num" => $dz_info["curr_lend_reser_num"] + 1);
        $where = [];
        $where = [
            'dz_id' => $dz_info['dz_id']
        ];
        $is_success = Dzgl::update($save_data,$where)->result;

        if ($is_success === false) {
            $this->error = "更新读者数据失败,请重新尝试";
            return false;
        }

        $dck_data = array("status" => "预借");
        $is_success = Dck::update($dck_data,['dck_id'=>$dck_info['dck_id']])->result;

        if ($is_success === false) {
            $this->error = "更新馆藏的状态数据失败,请重新尝试";
            return false;
        }

        return $dz_type_info["lend_reser_hold_days"];
    }

    /**
     * @param $lend_reser_id
     * @param $tsg_code
     * @return bool
     * @throws \think\exception\DbException
     * 取消预借
     */
    public function dropLendReserve($lend_reser_id,$tsg_code)
    {
        $lend_reser_info = self::get($lend_reser_id);

        if (empty($lend_reser_info)) {
            $this->error = "数据库未找到此预借记录,无法取消预借!";
            return false;
        }

        if ($lend_reser_info->getData('lend_reser_status') != self::LEND_RESER_STATUS_ADD) {
            $this->error = "必须预借中的记录才可取消预借!";
            return false;
        }

        if ($lend_reser_info["tsg_code"] != $tsg_code) {
            $this -> error = '无法取消他馆的预借信息';
            return false;
        }

        $dz_info = Dzgl::get($lend_reser_info['dz_id']);

        if (!$dz_info) {
            $this->error = "数据库未找到对应的读者,无法取消预借！";
            return false;
        }

        $save_data = array("lend_reser_status" => self::LEND_RESER_STATUS_CANCEL);
        $is_success = self::update($save_data,['lend_reser_id'=>$lend_reser_id])->result;

        if ($is_success === false) {
            $this->error = "取消预借失败:更新预借数据时失败!";
            return false;
        }

        $save_data = array("curr_lend_reser_num" => $dz_info["curr_lend_reser_num"] - 1);
        $is_success = Dzgl::update($save_data,['dz_id'=>$dz_info['dz_id']])->result;

        if ($is_success === false) {
            $this->error = "更新读者数据失败,请重新尝试!";
            return false;
        }

        $dck_data = array("status" => "在架");
        $is_success = Dck::update($dck_data,['dck_id'=>$lend_reser_info['dck_id']])->result;

        if ($is_success === false) {
            $this->error = "更新馆藏的状态数据失败,请重新尝试!";
            return false;
        }

        return true;
    }

    /**
     *清除超时预借
     */
    public static function clearTimeout()
    {
        $now_time = time();
        $save_data = [
            'lend_reser_status' => self::LEND_RESER_STATUS_OUT
        ];
        $where = [
            'lend_reser_status' => self::LEND_RESER_STATUS_ADD,
            'must_time' => ['< time',$now_time]
        ];
        @self::update($save_data,$where);
    }
}
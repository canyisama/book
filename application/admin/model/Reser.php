<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 9:27
 */

namespace app\admin\model;

use think\Loader;

/**
 * Class Reser
 * @package app\admin\model
 * 图书预约模型类
 */
class Reser extends  Base
{
    const RESER_STATUS_ADD = 1;
    const RESER_STATUS_BOOK = 2;
    const RESER_STATUS_NOITE = 3;
    const RESER_STATUS_OUT = 4;
    const RESER_STATUS_CANCEL = 5;
    const RESER_STATUS_FINISH = 6;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'reser_time'    =>  'timestamp',
        'must_time'    =>  'timestamp',
        'book_time'    =>  'timestamp',
        'take_time'    =>  'timestamp'
    ];

    private static $type_arr = [
        self::RESER_STATUS_ADD => "预约中",
        self::RESER_STATUS_BOOK => "已到书",
        self::RESER_STATUS_NOITE => "已通知",
        self::RESER_STATUS_OUT => "超时",
        self::RESER_STATUS_CANCEL => "取消预约",
        self::RESER_STATUS_FINISH => "已取书"
    ];


    /**
     * @param int $all
     * @return array|mixed|string
     * 预约状态数组
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
//     * 预约状态读取器
//     */
//    protected function getReserStatusAttr($value)
//    {
//        $type = [
//            self::RESER_STATUS_ADD => "预约中",
//            self::RESER_STATUS_BOOK => "已到书",
//            self::RESER_STATUS_NOITE => "已通知",
//            self::RESER_STATUS_OUT => "超时",
//            self::RESER_STATUS_CANCEL => "取消预约",
//            self::RESER_STATUS_FINISH => "已取书"
//        ];
//        return isset($type[$value]) ? $type[$value] : '无此类型';
//    }

    protected function getTakeTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    protected function getBookTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    protected function getMustTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    /**
     * @return array
     * dck允许预约状态数组
     */
    public static function getDckStatusLists()
    {
        return array("借出", "续借", "租出", "续租", "预借", "阅读", "装订");
    }

    /**
     * @param $reser_id
     * @param $tsg_code
     * @return bool
     * @throws \think\exception\DbException
     * 取消预约
     */
    public  function dropReserve($reser_id,$tsg_code=0)
    {
        $reser_info = self::get($reser_id);

        if (empty($reser_info)) {
            $this->error = "未找到此预约记录";
            return false;
        }

        if ($reser_info->getData('reser_status') != self::RESER_STATUS_ADD)
        {
            $this->error = "必须预约中的记录才可取消预约!";
            return false;
        }

        if ($tsg_code !== 0){
            if ($reser_info["tsg_code"] != $tsg_code) {
                $this -> error = '无法取消他馆的预约信息';
                return false;
            }
        }

        $dz_info = Dzgl::get($reser_info['dz_id']);

        if (!$dz_info) {
            $this->error = "未找到对应的读者信息";
            return false;
        }

        $save_data = array("reser_status" => self::RESER_STATUS_CANCEL);
        $is_success = self::update($save_data,['reser_id'=>$reser_id])->result;

        if ($is_success === false) {
            $this->error = "更新预约数据失败";
            return false;
        }

        $save_data = array("curr_reser_num" => $dz_info["curr_reser_num"] - 1);
        $is_success = Dzgl::update($save_data,['dz_id'=>$dz_info['dz_id']])->result;

        if ($is_success === false) {
            $this->error = "更新读者数据失败";
            return false;
        }

        return true;
    }


    /**
     * @param $tsg_code     @分馆代码
     * @param $dck_id       @书目ID
     * @return bool|null|static
     * @throws \think\exception\DbException
     */
    public static function  getKeepInfo($tsg_code, $dck_id)
    {
        if (empty($tsg_code) || empty($dck_id)) {
            return false;
        }

        $where = [
            'dck_id' => $dck_id,
            'reser_status' => ['in',[self::RESER_STATUS_ADD,self::RESER_STATUS_BOOK,self::RESER_STATUS_NOITE]]
        ];
        $reser_info = self::get($where);
        $is_success = ($reser_info ? $reser_info : false);
        return $is_success;
    }

    /**
     * @param $reser_id     @预约id
     * @return bool|null
     * @throws \think\exception\DbException
     * 拿走预约的图书
     */
    public function takeBook($reser_id)
    {
        if (empty($reser_id)) {
            $this->error = "图书预约ID不能为空";
            return false;
        }

        $reser_info = self::get($reser_id);

        if (empty($reser_info)) {
            $this->error = "图书预约信息不存在";
            return false;
        }

        if (($reser_info["reser_status"] != self::RESER_STATUS_BOOK) && ($reser_info["reser_status"] != self::RESER_STATUS_NOITE)) {
            $this->error = "预约信息非【已到书】【已通知】状态,无法取书";
            return false;
        }

        $dz_info = Dzgl::get($reser_info['dz_id']);

        if (!$dz_info) {
            $this->error = "未找到对应的读者";
            return false;
        }

        $reser_save_data = [
            "reser_status" => self::RESER_STATUS_FINISH,
            "take_time" => time()
        ];

        $where = [];
        $where = [
            'reser_id' => $reser_id
        ];
        $is_success = self::update($reser_save_data,$where)->result;

        if ($is_success === false) {
            $this->error = "更新预约信息失败";
            return false;
        }

        $save_data = array("curr_reser_num" => $dz_info["curr_reser_num"] - 1);

        $where = [];
        $where = [
            'dz_id' => $dz_info['dz_id']
        ];
        $is_success = Dzgl::update($save_data,$where)->result;

        if ($is_success === false) {
            $this->error = "更新读者信息失败";
            return false;
        }

        return $is_success;
    }


    /**
     * @param $tsg_code     @分馆代码
     * @param $book_id      @图书id
     * @return bool|mixed   返回预约id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function hasReser($tsg_code, $book_id)
    {
        if (empty($tsg_code) || empty($book_id)) {
            return false;
        }
        $where = [
            'book_id' => $book_id,
            'reser_status' => self::RESER_STATUS_ADD
        ];
        $reser_info = self::field("reser_id")->where($where)->order("reser_time")->find();
        $is_success = ($reser_info ? $reser_info["reser_id"] : false);
        return $is_success;
    }

    /**
     * @param $tsg_code     @分馆代码
     * @param $reser_id      @预约id
     * @param $dck_id
     * @return bool|static
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 预约到书-----保留书籍
     */
    public function keepBook($tsg_code, $reser_id, $dck_id)
    {
        if (empty($tsg_code)) {
            $this->error = "预约保留失败:分馆代码参数不能为空";
            return false;
        }

        if (empty($reser_id)) {
            $this->error = "预约保留失败:预约数据ID为空";
            return false;
        }

        if (empty($dck_id)) {
            $this->error = "预约保留失败:馆藏库数据ID为空";
            return false;
        }

        $where = [
            'tsg_code' => $tsg_code,
            'dck_id'  => $dck_id
        ];
        $dck_info = Dck::field("dck_id,barcode,status")->where($where)->find();

        if (empty($dck_info)) {
            $this->error = "预约保留失败:未找到馆藏库数据";
            return false;
        }
        $where = [
            'reser_id' => $reser_id,
            'reser_status' => self::RESER_STATUS_ADD
        ];
        $reser_info = self::field("reser_id,barcode,reser_status,dz_type_code,tsg_code")->where($where)->find();

        if (empty($reser_info)) {
            $this->error = "预约保留失败:未找到状态为预约中预约数据";
            return false;
        }



        $where = [
            'tsg_code' => $reser_info['tsg_code'],
            'dz_type_code' => $reser_info['dz_type_code']
        ];
        $dz_type_info = DzType::field("dz_type_code,dz_type_name,is_reser,reser_max_days,reser_hold_days")->where($where)->find();

        if (empty($dz_type_info)) {
            $this->error = "预约保留失败:读者类型数据不存在";
            return false;
        }


        if (!Dck::isLend($dck_info["status"])) {
            $this->error = '预约保留失败:馆藏当前状态为:['.$dck_info["status"].'],无法为读者保留';
            return false;
        }


        $time_now = time();

        $must_time = $time_now + ($dz_type_info["reser_hold_days"] * 86400);


        $holi_id = Holiday::disDate($tsg_code, $must_time);

        $save_data = array(
            "dck_id" => $dck_info["dck_id"],
            "barcode" => $dck_info["barcode"],
            "reser_status" => self::RESER_STATUS_BOOK,
            "book_time" => $time_now,
            "must_time" => $must_time);

        $where = [
            'reser_id' => $reser_info['reser_id']
        ];
        $is_success = self::update($save_data,$where)->result;
        return $is_success;
    }

    /**
     * @param $dz_code      @读者证号
     * @param $tsg_code     @分馆代码
     * @param $book_id      @书目id
     * @return bool|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 预约图书
     */
    public function bookReserve($dz_code, $tsg_code, $book_id)
    {
        if (!$dz_code) {
            $this->error = "无效的读者证号";
            return false;
        }

        if (!$tsg_code) {
            $this->error = "无效的图书所属分馆代码";
            return false;
        }

        if (!$book_id) {
            $this->error = "无效的图书信息";
            return false;
        }

        $field = 'dz_id,tsg_code,dz_code,dz_type_code,reser_num,curr_reser_num,real_name,unit_name,phone_mob,email';
        $dz_info = Dzgl::field($field)->where(['dz_code'=>$dz_code])->find();
        if (!$dz_info) {
            $this->error = "数据库未找到对应的读者";
            return false;
        }

        $field = 'dz_type_code,dz_type_name,is_reser,reser_max_days,reser_hold_days,is_out_can,max_lend_num,is_inter';
        $where = [
            'tsg_code' => $dz_info['tsg_code'],
            'dz_type_code' => $dz_info['dz_type_code']
        ];
        $dz_type_info = DzType::field($field)->where($where)->find();

        if (empty($dz_type_info)) {
            $this->error = "读者类型数据不存在";
            return false;
        }

        $book_info = Book::field("book_id,title,bl_title,othertitle,fjno,fjtitle,clc,isbn,price_ms")->where(['book_id'=>$book_id])->find();

        if (!$book_info) {
            $this->error = "数据库未找到对应的图书信息,无法预约";
            return false;
        }

        if (($dz_info["tsg_code"] != $tsg_code) && ($dz_type_info["is_inter"] != 1)) {
            $this->error = "他馆读者和未开通馆际读者无法预约！";
            return false;
        }

        $where = [
            'tsg_code' => $tsg_code,
            'tsg_code_has' => $tsg_code,
            'book_id' => $book_id
        ];
        $dck_list = Dck::field("status,barcode,dt")->where($where)->select();

        foreach ($dck_list as $item ) {

            if (Dck::isLend($item["status"])) {
                $is_qk = ($item["dt"] == Dck::DT_TYPE_QK ? "期刊" : "");
                $this->error = "本书当前有".$is_qk."馆藏可借,图书条码【".$item["barcode"]."】";
                return false;
            }
        }


        $where = [
            'book_id' => $book_id,
            'dz_code' => $dz_code,
            'reser_status' => self::RESER_STATUS_ADD
        ];
        $reser_info = self::field("book_id")->where($where)->find();

        if (!empty($reser_info)) {
            $this->error = "您已预约此书,无需再次预约";
            return false;
        }

        $where = [
            'book_id' => $book_id,
            'reser_status' => self::RESER_STATUS_ADD
        ];
        $reser_cnt = self::field("book_id")->where($where)->count();
        $dck_status = self::getDckStatusLists();
        $dck_where = array(
            "tsg_code"     => $tsg_code,
            "tsg_code_has" => $tsg_code,
            "book_id"      => $book_id,
            "status"       => array("in", $dck_status)
        );
        $dck_cnt = Dck::where($dck_where)->count();

        if ($dck_cnt <= $reser_cnt) {
            $this->error = "书目可预约次数:【".$dck_cnt."】,已被预约次数:【".$reser_cnt."】";
            return false;
        }

        if (!$dz_type_info["is_reser"]) {
            $this->error = "读者未开通预约";
            return false;
        }

        if ($dz_type_info["reser_max_days"] <= $dz_info["curr_reser_num"]) {
            $this->error = "读者最大预约册数【{$dz_type_info["reser_max_days"]}】,当前预约册数【{$dz_info["curr_reser_num"]}】";
            return false;
        }

        $time_now = time();
        $add_data = array(
            "tsg_code" => $tsg_code,
            "dz_id" => $dz_info["dz_id"],
            "dz_code" => $dz_info["dz_code"],
            "dz_type_code" => $dz_type_info["dz_type_code"],
            "dz_type_name" => $dz_type_info["dz_type_name"],
            "real_name" => $dz_info["real_name"],
            "unit_name" => $dz_info["unit_name"],
            "phone_mob" => $dz_info["phone_mob"],
            "email" => $dz_info["email"],
            "title" => Book::getFullTitle($book_info),
            "clc" => $book_info["clc"],
            "isbn" => $book_info["isbn"],
            "book_id" => $book_info["book_id"],
            "reser_status" => self::RESER_STATUS_ADD,
            "price_ms" => $book_info["price_ms"],
            "is_inter_reser" => $dz_info["tsg_code"] != $tsg_code ? 1 : 0,
            "reser_time" => $time_now);

        $is_success = self::create($add_data)->result;

        if ($is_success === false) {
            $this->error = "插入预约数据失败";
            return false;
        }

        $save_data = array(
            "reser_num" => $dz_info["reser_num"] + 1,
            "curr_reser_num" => $dz_info["curr_reser_num"] + 1);
        $is_success = Dzgl::update($save_data,['dz_id'=>$dz_info['dz_id']])->result;

        if ($is_success === false) {
            $this->error = "更新读者数据失败";
            return false;
        }

        return $dz_type_info["reser_hold_days"];
    }


    public static function onNotice($tsg_code, $reser_id)
    {
        if (empty($tsg_code) || empty($reser_id)) {
            return false;
        }

        $where = [
            'tsg_code' => $tsg_code,
            'reser_id' => $reser_id,
            'reser_status' => self::RESER_STATUS_BOOK
        ];
        $reser_data = array("reser_status" => self::RESER_STATUS_NOITE);
        $is_success = self::update($reser_data,$where)->result;
        return $is_success;
    }

    /**
     *清除超时预约
     */
    public static function clearTimeout()
    {
        $now_time = time();
        $save_data = [
            'reser_status' => self::RESER_STATUS_OUT
        ];
        $where = [
            'reser_status' => self::RESER_STATUS_NOITE,
            'must_time' => ['< time',$now_time]
        ];
        @self::update($save_data,$where);
    }

}
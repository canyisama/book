<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/18
 * Time: 11:43
 */

namespace app\admin\model;

use think\Exception;

/**
 * Class Lend
 * @package app\admin\model
 * 借书模型类
 */
class Lend extends Base
{
    const LEND_STATUS_ON = 1;
    const LEND_STATUS_OFF = 2;
    public $book_info_rebook = [];

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'add_time'    =>  'timestamp',
        'must_time'    =>  'timestamp',
        'end_time'    =>  'timestamp'
    ];

    private static $type_arr = [
        self::LEND_STATUS_ON => "借出",
        self::LEND_STATUS_OFF => "还回"
    ];


    /**
     * @param int $all
     * @return array|mixed|string
     * 借阅状态数组
     */
    public static function getType($all = 0)
    {
        $type_lists = self::$type_arr;
        if ($all === 0){
            return $type_lists;
        }
        return isset($type_lists[$all]) ? $type_lists[$all] : '无此类型';
    }

    public function book()
    {
        return $this->belongsTo('Book', 'book_id', 'book_id');
    }

    protected function getEndTimeAttr($value)
    {
        return empty($value) ? '-' : date('Y-m-d H:i:s',$value);
    }

    public function hasLendBook($dz_id)
    {
        $where = array("dz_id" => $dz_id, "lend_status" => self::LEND_STATUS_ON);
        $lend_info = $this->field("lend_id")->where($where)->find();
        return !empty($lend_info) ? true : false;
    }

    /**
     * @param $dz_info      @读者信息
     * @param $book_info    @图书信息
     * @return bool|string
     * 借书
     */
    public function lend($dz_info,$book_info)
    {
        $tsg_code = isset($dz_info['admin_tsg_code']) ? trim($dz_info['admin_tsg_code']) : '';
        $lt_rule_model = new Ltrule();

        try {
            $tsg_info = Tsg::get(['tsg_code'=>$tsg_code]);
            if (empty($tsg_info)) {
                $this->error = "未找到分馆信息";
                return false;
            }
            if (empty($dz_info["admin_info"])) {
                $this->error = "管理员信息不能为空";
                return false;
            }
            $is_inter = ($dz_info["tsg_code"] != $tsg_code ? 1 : 0);
            if (($tsg_info["lend_is_owe"] == 1) && (0 < $dz_info["owe_money"])) {
//            $this->retdata = array("sound_num" => "2");
                $this->error = '外借失败:读者当前欠款[' . $dz_info["owe_money"] . ']无法外借';
                return false;
            }
            if ($dz_info['is_out_can'] == 0) {
                $time_now = time();
                $where = [
                    'dz_code' => $dz_info['dz_code'],
                    'must_time' => ['lt', $time_now],
                    'lend_status' => self::LEND_STATUS_ON
                ];

                $lend_info_out = self::field("barcode")->where($where)->find();

                if (!empty($lend_info_out)) {
//                $this->retdata = array("sound_num" => "11");
                    $this->error = '读者当前借阅有超期,图书条码:[' . $lend_info_out["barcode"] . '],请先归还再外借';
                    return false;
                }
            }

            if ($is_inter == 0) {
                $rule_info = Ltrule::getRule($tsg_code, $dz_info["dz_type_code"], $book_info["lt_type"], $book_info["tsg_site_code"]);
            } else {
                $rule_info = Ltrule::getRuleInter($tsg_code, $book_info["lt_type"], $book_info["tsg_site_code"]);
            }

            if ($rule_info["is_close"] == 1) {
                $this->retdata = $book_info;
                $this->error = "当前匹配的流通规则里状态为禁用,无法外借";
                return false;
            }

            if ($book_info["tsg_code_has"] != $book_info["tsg_code"]) {
                $this->error = "图书不在所属馆,无法继续流通!";
                return false;
            }

            $reser_keep_info = Reser::getKeepInfo($book_info["tsg_code"], $book_info["dck_id"]);

            if ($reser_keep_info && ($reser_keep_info["dz_id"] != $dz_info["dz_id"])) {
                $this->error = '本馆藏已为预约读者' . $reser_keep_info["real_name"] . '[' . $reser_keep_info["dz_code"] . ']保留,无法外借';
                return false;
            }

            $dz_info["lend_money_limit"] = floatval($dz_info["lend_money_limit"]);

            if (!empty($dz_info["lend_money_limit"])) {
                $where = [
                    'dz_id' => $dz_info['dz_id'],
                    'lend_status' => self::LEND_STATUS_ON
                ];
                $lend_amount = self::where($where)->sum("price");
                $lend_amount_all = $lend_amount + $book_info["price"];

                if ($dz_info["lend_money_limit"] < $lend_amount_all) {
                    $lend_amount_sub = $dz_info["lend_money_limit"] - $lend_amount;
                    $this->error = '外借图书总额限制:[' . $dz_info["lend_money_limit"] . '],当前可借单价[' . $lend_amount_sub . ']以内的图书。';
                    return false;
                }
            }

            if ($dz_info["max_lend_num"] <= $dz_info["curr_lend_num"]) {

                $this->error = "读者最大可借册数:[" . $dz_info["max_lend_num"] . "]，已借册数:[" . $dz_info["curr_lend_num"] . "],无法外借!";
                return false;
            }

            if ($is_inter == 1) {
                if ($dz_info["inter_lend_num"] <= $dz_info["curr_inter_lend_num"]) {
                    $this->error = "读者馆际最大可借册数:[" . $dz_info["inter_lend_num"] . "]，已借册数:[" . $dz_info["curr_inter_lend_num"] . "],无法外借";
                    return false;
                }
            }

            if ($rule_info["rule_type"] == $lt_rule_model::LT_IS_RULE_TYPE_EXT) {
                $rule_lend_num = $lt_rule_model->getCurrLendNum($dz_info["dz_id"], $rule_info, $is_inter);

                if ($rule_info["lend_num"] <= $rule_lend_num) {
                    $this->error = "流通规则限制此书所在馆藏地址本读者最大可借册数:[" . $rule_info["lend_num"] . "]，已借册数:[" . $rule_lend_num . "],无法外借";
                    return false;
                }
            }

            $where = [
                'tsg_code' => $book_info['tsg_code'],
                'ltype_code' => $book_info['lt_type']
            ];
            $ltype_info = Ltype::field("ltype_name,ltype_code")->where($where)->find();

            if (!$ltype_info) {
//            $this->retdata = $book_info;
                $this->error = "此书的流通类型不存在,无法外借";
                return false;
            }

            $where = [
                'tsg_code' => $book_info['tsg_code'],
            'tsg_site_code' => $book_info['tsg_site_code']
            ];
            $site_info = TsgSite::field("site_name,tsg_site_code")->where($where)->find();

            if (!$site_info) {
//            $this->retdata = $book_info;
                $this->error = "此书的馆藏地址不存在,无法外借";
                return false;
            }

            $is_lend_reser = false;

            $where = [
                'dck_id' => $book_info['dck_id'],
                'dz_id' => $dz_info['dz_id'],
                'lend_reser_status' => LendReser::LEND_RESER_STATUS_ADD
            ];

            $lend_reser_info = LendReser::field("lend_reser_id,dz_code,dck_id,book_id,dz_id")->where($where)->find();

            if ($book_info["status"] == "预借") {
                if (!empty($lend_reser_info)) {
                    $is_lend_reser = true;
                } else {
//                $this->retdata = $book_info;
                    $this->error = "借阅失败:此书已被人预借";
                    return false;
                }
            } else if (!Dck::isLend($book_info["status"])) {
                $status_txt = ($book_info["status"] ? $book_info["status"] : "未设置");
//            $this->retdata = $book_info;
                $this->error = "此书当前状态为:[" . $status_txt . "],无法借阅";
                return false;
            }

            $dck_data = array("status" => "借出");
            $where = [
                'dck_id' => $book_info['dck_id']
            ];
            $is_success = Dck::update($dck_data,$where)->result;

            if ($is_success === false) {
//                $this->retdata = $book_info;
                $this->error = "外借失败:更改书的状态数据失败,请重新尝试";
                return false;
            }

            $save_data = array(
                "lend_num" => $dz_info["lend_num"] + 1,
                "integral" => $dz_info["integral"] + 1,
                "curr_lend_num" => $dz_info["curr_lend_num"] + 1
            );
            if ($is_lend_reser && !empty($lend_reser_info)) {
                $lend_reser_data = array(
                    "lend_reser_status" => LendReser::LEND_RESER_STATUS_FINISH,
                    "take_time" => time());
                $where = [
                    'lend_reser_id' => $lend_reser_info['lend_reser_id']
                ];
                $is_success = LendReser::update($lend_reser_data, $where)->result;

                if ($is_success === false) {
//                    $this->retdata = $book_info;
                    $this->error = "外借失败:更改预借数据失败,请重新尝试";
                    return false;
                }

                $save_data["curr_lend_reser_num"] = $dz_info["curr_lend_reser_num"] - 1;
            }
            $reser_model = new Reser();
            if ($reser_keep_info) {
                $is_success = $reser_model->takeBook($reser_keep_info["reser_id"]);

                if ($is_success === false) {
//                    $this->retdata = $book_info;
                    $this->error = "外借失败:更新预约信息失败," . $reser_model->getError();
                    return false;
                }
            }

            if ($is_inter == 1) {
                $save_data["inter_lend_num"] = $dz_info["inter_lend_num"] + 1;
                $save_data["curr_inter_lend_num"] = $dz_info["curr_inter_lend_num"] + 1;
            }

            $where = [
                'dz_id' => $dz_info['dz_id']
            ];
            $is_success = Dzgl::update($save_data,$where)->result;

            if ($is_success === false) {
//                $this->retdata = $book_info;
                $this->error = "外借失败:更新读者数据失败,请重新尝试";
                return false;
            }

//            $curr_time = ($add_time ? $add_time : time());
            $curr_time = time();
            $must_time = $curr_time + ($rule_info["lend_days"] * 86400);

            if ($tsg_info["lend_mode"] == 2) {
                $must_time = strtotime(date("Y-m-d", $must_time) . " 23:59:59");
            }

            $holiday_id = Holiday::disDate($dz_info['admin_tsg_code'], $must_time);
            $lend_data = array(
                "tsg_code" => $tsg_code,
                "is_inter_lend" => $is_inter,
                "op_user" => $dz_info["admin_info"]["user_name"],
                "dz_id" => $dz_info["dz_id"],
                "dz_code" => $dz_info["dz_code"],
                "barcode" => $book_info["barcode"],
                "dz_type_code" => $dz_info["dz_type_code"],
                "dz_type_name" => $dz_info["dz_type_name"],
                "tsg_site_code" => $site_info["tsg_site_code"],
                "site_name" => $site_info["site_name"],
                "ltype_code" => $ltype_info["ltype_code"],
                "ltype_name" => $ltype_info["ltype_name"],
                "real_name" => $dz_info["real_name"],
                "unit_name" => $dz_info["unit_name"],
                "phone_mob" => $dz_info["phone_mob"],
                "email" => $dz_info["email"],
                "calino" => $book_info["calino"],
                "price" => $book_info["price"],
                "price_sum" => $book_info["price_sum"],
                "title" => Book::getFullTitle($book_info),
                "book_id" => $book_info["book_id"],
                "dck_id" => $book_info["dck_id"],
                "lend_status" => self::LEND_STATUS_ON,
                "add_time" => $curr_time,
                "must_time" => $must_time,
                "holiday_id" => $holiday_id);


            $is_success = self::create($lend_data)->result;

            if ($is_success === false) {
//                $this->retdata = $book_info;
                $this->error = "外借失败:增加流通记录失败,无法流通";
                return false;
            }
            $log_type = $dz_info['is_no_card'] ? 4 : 1;
            $op_desc = '[#],读者姓名:[' . $dz_info['real_name'];
            $op_desc .= '],读者证号[' . $dz_info['dz_code'];
            $op_desc .= '],书名[' . $book_info['title'];
            $op_desc .= '],图书条码[' . $book_info['barcode'] . ']';
            $param = [
                'book_id' => $book_info['book_id'],
                'dck_id' => $book_info['dck_id'],
                'db1' => $dz_info['dz_code'],
                'db2' => $book_info['title'],
                'op_desc' => $op_desc
            ];

            $is_success = LtLog::addlog($log_type, $dz_info["admin_info"], $param);
            if ($is_success === false) {
                $this->error = '流通日志写入失败';
                return false;
            }
//            $this->retdata = array("msg" => "借书成功,应还日期:" . date("Y-m-d", $must_time));
            $message = "借书成功,应还日期:" . date('Y-m-d', $must_time);
            return $message;
        }
        catch (Exception $e){
            $this ->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param $tsg_code
     * @param $lend_id
     * @param string $dck_status
     * @param int $end_time
     * @return bool|int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 还书
     */
    public function rebook($tsg_code, $lend_id, $dck_status = "在架", $end_time = 0)
    {
//        try {
            $tsg_info = Tsg::field("is_email,is_sms,lend_is_inter_re")->where(["tsg_code" => $tsg_code])->find();

            if (empty($tsg_info)) {
                $this->error = "未找到分馆信息!";
                return false;
            }
            $where = [
                'lend_id' => $lend_id,
                'lend_status' => self::LEND_STATUS_ON
            ];
            $lend_info = self::field("lend_id,dz_code,must_time,real_name,title,barcode,book_id,dck_id,dz_id,is_inter_lend")->where($where)->find();

            if (empty($lend_info)) {
                $this->error = "该书未查到借阅记录!";
                return false;
            }
            $end_time = $end_time ? $end_time : time();
            if ($lend_info->getData('must_time') < $end_time) {
                $this->error = "归还失败:该书已超期!";
                return false;
            }
            $where_dck = [
                'barcode' => $lend_info['barcode']
            ];
            $dck_info = Dck::field("dck_id,barcode,book_id,tsg_site_code,lt_type,calino,price,status,tsg_code")->where($where_dck)->find();

            if (empty($dck_info)) {
                $this->error = "无效的图书条码!";
                return false;
            }

            if (($tsg_info["lend_is_inter_re"] == 1) && ($dck_info["tsg_code"] != $tsg_code)) {
                $this->error = "本馆已设置馆际通借不通还,无法归还在他馆借阅的图书";
                return false;
            }
            $field_dz = "dz_id,tsg_code,dz_code,dz_type_code,real_name,unit_name,pwd,curr_lend_num,lend_num,inter_lend_num,curr_inter_lend_num";
            $dz_info = Dzgl::field($field_dz)->where(["dz_id" => $lend_info["dz_id"]])->find();

            if (empty($dz_info)) {
                $this->error = "未找到对应的读者信息!";
                return false;
            }



            $dz_type_model = new DzType();
            $dz_type_info = $dz_type_model->isDzType($dz_info->getData('tsg_code'), $dz_info->dz_type_code, $tsg_code);
            if ($dz_type_info === false) {
                $this->error = $dz_type_model->getError();
                return false;
            }

            $book_info = Book::field("book_id,title,clc,isbn,firstauthor,publisher")->where(["book_id" => $dck_info["book_id"]])->find();
            $book_info["price"] = $dck_info["price"];
            $book_info["dz_code"] = $lend_info["dz_code"];
            $book_info["lend_id"] = $lend_info["lend_id"];
            $book_info['real_name'] = $dz_info['real_name'];
            $book_info['barcode'] = $lend_info['barcode'];
            $book_info['dck_id'] = $dck_info['dck_id'];
            $book_info['status'] = $dck_status;
            $this->book_info_rebook = $book_info;



            if (!in_array($dck_info["status"], array("借出", "续借", "租出", "续租"))) {
                $status_txt = ($dck_info["status"] ? $dck_info["status"] : "未设置");
                $this->error = "此书当前状态为:[$status_txt],无法继续操作!";
                return false;
            }

            $dck_data = array("status" => $dck_status, "tsg_code" => $tsg_code);

            $where = [
                'dck_id' => $dck_info['dck_id']
            ];
            $is_success = Dck::update($dck_data,$where)->result;


            if ($is_success === false) {
                $this->error = "更新馆藏数据失败";
                return false;
            }


            //判断是否是馆际借还
            if ($lend_info["is_inter_lend"] == 1) {
//            $dz_data["inter_lend_num"] = $dz_info["inter_lend_num"] - 1;
                $dz_data["curr_inter_lend_num"] = $dz_info["curr_inter_lend_num"] - 1;
            }
//        else{
//            $dz_data['curr_lend_num'] =  $dz_info["curr_lend_num"] - 1;
//        }
            $dz_data['curr_lend_num'] = $dz_info["curr_lend_num"] - 1;
            $where = [
                'dz_id' => $dz_info['dz_id']
            ];


            $is_success = Dzgl::update($dz_data,$where)->result;


            if ($is_success === false) {
                $this->error = "更新读者数据失败";
                return false;
            }


            $lend_data = [
                "tsg_code_re" => $tsg_code,
                "lend_status" => self::LEND_STATUS_OFF,
                "end_time" => $end_time ? $end_time : time()
            ];
            $where = [
                'lend_id' => $lend_info['lend_id']
            ];

            $is_success = self::update($lend_data,$where)->result;


            if ($is_success === false) {
                $this->error = "更新流通数据失败";
                return false;
            }
            if ($dck_status == '遗失') {
                return true;
            }


            if ($lend_info["is_inter_lend"] == 0) {
                $rule_info = Ltrule::getRule($tsg_code, $dz_info["dz_type_code"], $dck_info["lt_type"], $dck_info["tsg_site_code"]);
            } else {
                $rule_info = Ltrule::getRuleInter($tsg_code, $dck_info["lt_type"], $dck_info["tsg_site_code"]);
            }
            if ($rule_info["is_close"] == 1) {
                $this->error = '请注意,此书当前的流通规则为禁用,无法外借!';
                return 1;
            }

            $reser_id = Reser::hasReser($tsg_code, $book_info["book_id"]);
            $reser_model = new Reser();

            $book_info['reser_keep_info'] = [];
            if ($reser_id !== false) {
                $reser_info = Reser::field("reser_id,dz_code,real_name,unit_name")->where("reser_id=$reser_id")->find();

                $is_success = $reser_model->keepBook($tsg_code, $reser_id, $dck_info["dck_id"]);


                if ($is_success === false) {
                    $this->error = '该书已被读者【' . $reser_info["dz_code"] . '】预约,将该书为读者保留时出现错误,' . $reser_model->getError();
                    return false;
                }

                $email_tpl_id = EmailTpl::where(['tsg_code'=>$tsg_code,'tpl_type'=>EmailTpl::TPL_TYPE_RESER])->value('email_tpl_id');
                $book_info["reser_keep_info"] = array(
                    "reser_id" => $reser_id,
                    "is_email" => $tsg_info["is_email"],
                    "is_sms" => $tsg_info["is_sms"],
                    'email_tpl_id' => $email_tpl_id,
                    "reser_msg" => "此书被读者预约,已为读者保留,读者证号:【" . $reser_info["dz_code"] . "】,姓名:【" . $reser_info["real_name"] . '】,单位名称:【' . $reser_info["unit_name"] . '】');

                $this->book_info_rebook = $book_info;
            }
            return true;
//        }
//        catch (Exception $e){
//            $this->error = $e->getMessage();
//            return false;
//        }
    }

    /**
     * @param $lend_id
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 续借
     */
    public function keepBook($lend_id)
    {
        $field = 'lend_id,dz_code,must_time,re_cnt,add_time,is_inter_lend,dz_type_code,ltype_code,tsg_site_code,book_id,dck_id,title,real_name,barcode,holiday_id';
        $where = [
            'lend_id'  => $lend_id,
        ];
        $lend_info = self::field($field)->where($where)->find();

        if (empty($lend_info)) {
            $this->error = '无此图书条码的借阅记录,续借失败!';
            return false;
        }
        if ($lend_info->getData('must_time') < time()) {
            $this->error = '本书借阅超期,无法续借!';
            return false;
        }

        $field = 'dz_id,tsg_code,dz_code,dz_type_code,real_name,unit_name,pwd,curr_lend_num,lend_num,renew_num';
        $dz_info = Dzgl::field($field)->where(['dz_code'=>$lend_info["dz_code"]])->find();

        if (empty($dz_info)) {
            $this->error = '未找到对应的读者信息,续借失败!';
            return false;
        }

        $tsg_code = $dz_info['tsg_code'];

        $dz_type_model = new DzType();
        $dz_type_info = $dz_type_model ->isDzType($dz_info->getData('tsg_code'),$dz_info->dz_type_code,$tsg_code);
        if ($dz_type_info === false){
            $this->error = $dz_type_model->getError();
            return false;
        }

        $field = 'dck_id,barcode,book_id,tsg_site_code,lt_type,calino,price,status';
        $where = '';
        $where = [
            'tsg_code' => $tsg_code,
            'barcode'  => $lend_info['barcode']
        ];
        $dck_info = Dck::field($field)->where($where)->find();

        if (empty($dck_info)) {
            $this->error = '无效的图书条码!';
            return false;
        }

        if ($lend_info["is_inter_lend"] == 0) {
            $rule_info = Ltrule::getRule($tsg_code, $lend_info["dz_type_code"], $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
        }
        else {
            $rule_info = Ltrule::getRuleInter($tsg_code, $lend_info["ltype_code"], $lend_info["tsg_site_code"]);
        }

        if (($dck_info["status"] != "借出") && ($dck_info["status"] != "续借")) {
            $status_txt = ($dck_info["status"] ? $dck_info["status"] : "未设置");
            $this->error = '此书当前状态为:'.$status_txt.',无法续借!';
            return false;
        }

        if ($rule_info["renew_cnt"] <= $lend_info["re_cnt"]) {
            $this->error = '流通规则最大续借次数:['.$rule_info["renew_cnt"].'],已续借['.$lend_info["re_cnt"].']次,无法续借!';
            return false;
        }

        $time_now = time();
        $renew_time = ($rule_info["renew_mode"] == 1
            ? $time_now + ($rule_info["renew_days"] * 86400)
            : $lend_info->getData('must_time') + ($rule_info["renew_days"] * 86400));

//        if ($rule_info["renew_mode"] == 1) {
//            $mod_holiday->disDate($this->admin_info['manage_tsg_code'], $renew_time);
//        }
//        else if (!$lend_info["holiday_id"]) {
//            $mod_holiday->disDate($this->admin_info['manage_tsg_code'], $renew_time);
//        }
        Holiday::disDate($tsg_code, $renew_time);

        $dck_data = array("status" => "续借");
        $where = [];
        $where = [
            'dck_id' => $dck_info['dck_id']
        ];
        $is_success = Dck::update($dck_data,$where)->result;

        if ($is_success === false) {
//                $dck_model->rollback();
            $this->error = '更改馆藏的状态数据失败,请重新尝试!';
            return false;
        }

        $save_data = array("renew_num" => $dz_info["renew_num"] + 1);
        $where = [];
        $where = [
            'dz_id' => $dz_info['dz_id']
        ];
        $is_success = Dzgl::update($save_data,$where)->result;

        if ($is_success === false) {
            $this->error = '更新读者数据失败,请重新尝试!';
            return false;
        }

        $lend_data = array(
            "re_cnt" => $lend_info["re_cnt"] + 1,
            "must_time" => $renew_time);
        $where = [];
        $where = [
            'lend_id' => $lend_info['lend_id']
        ];
//        $lend_info -> re_cnt += 1;
//        $lend_info -> must_time = $renew_time;
        $is_success = self::update($lend_data,$where)->result;

        if ($is_success === false) {
            $this->error = '更新流通数据失败,无法续借!';
            return false;
        }
        $lend_info['status'] = '续借';
        $lend_info['must_time'] = $renew_time;
        return $lend_info;

    }


}
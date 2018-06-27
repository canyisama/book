<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/15
 * Time: 17:10
 */

namespace app\opac\controller;



use app\admin\model\DzType;
use app\admin\model\Dzgl;
use app\admin\model\ExpectLog;
use app\admin\model\Lend;
use think\Db;
use think\Exception;

class UserController extends UserBaseController
{
    /**
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 个人中心---用户首页
     */
    public function indexAction()
    {
        $where = [
            'dz_id' => $this->_dz_info['dz_id'],
        ];

        $field = 'dz_id,portrait,real_name,curr_lend_num,curr_reser_num,curr_lend_reser_num';

        $dz_info = Dzgl::with(['opacLog'=>function ($query){
            $query->field('log_time,dz_id');
        }])->field($field)->where($where)->find();

        $log_info = [];

        if ($dz_info && $dz_info->opacLog){
            $log_info = $dz_info->opacLog()->field('log_time,ip_addr')->order('log_time desc')->find();
        }


        $field = 'add_time,lend_status,title,must_time';
        $where = [
            'lend_status' => 1,
            'dz_id' => $this->_dz_info['dz_id']
        ];

        $lend_list = Lend::field($field)->where($where)->select();

        $this->assign('info',$dz_info);
        $this->assign('log_info',$log_info);
        $this->assign('lend_list',$lend_list);
        return view();
    }

    /**
     *续借
     */
    public function keep_bookAction()
    {
        try{
            $lend_id = input('lend_id/d')?:0;

            if (empty($lend_id)) {
                $this->error('无效的借阅ID参数,无法续借!');
            }
            $lend_info = Lend::field('dz_id')->where(['lend_id'=>$lend_id])->find();
            if (empty($lend_info)) {
                $this->error('未找到借阅信息,无法续借!');
            }

            if ($lend_info["dz_id"] != $this->_dz_info["dz_id"]) {
                $this->error('该借阅信息不属于你,无法续借!');
            }
            Db::startTrans();
            $lend_model = new Lend();
            $is_success = $lend_model->keepBook($lend_id);

            if ($is_success === false) {
                Db::rollback();
                $this->error('续借失败:'.$lend_model->getError());
            }

            Db::commit();
            $this->success('续借成功');
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * @return \think\response\View
     * 用户信息详细显示
     */
    public function infoAction()
    {
        try{
        $dz_id = $this->_dz_info["dz_id"];
        if (!$this->isPost) {
            $dz_info = Dzgl::get(['dz_id'=>$dz_id]);
            $where = [
                'tsg_code' => $dz_info['tsg_code'],
                'dz_type_code' => $dz_info['dz_type_code']
            ];
            $dz_info["dz_type_name"] = DzType::where($where)->value('dz_type_name');

            $cred_type_list = Dzgl::get_cred_type_list();
            $this->assign("cred_type_list", $cred_type_list);
            $this->assign("info", $dz_info);
            $dz_model = new Dzgl();
            $time_id = date("YmdHis");
            $this->assign("time_id", $time_id);
            $flash_html = $dz_model->get_avatar($this->_dz_info["dz_id"], $time_id);
            $this->assign("flash_html", $flash_html);
            return view();
        }

            $save_data = $this->request->except(['file'],'post');

            $is_success = Dzgl::update($save_data,['dz_id'=>$dz_id],true)->result;

            if ($is_success === false) {
                $this->error("保存失败！错误提示:更新读者数据失败");
            }
            $this->success("保存成功");
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * 删除用户头像
     */
    public function dropPortraitAction()
    {
        try{
            $dz_id = input('dz_id/d');
            if(empty($dz_id)){
                $this->error('读者id不存在');
            }

            $portrait = Dzgl::where(['dz_id'=>$dz_id])->value('portrait');

            $is_success = Dzgl::update(['portrait'=>''],['dz_id'=>$dz_id]);
            if ($is_success === false){
                $this->error('删除失败');
            }
            @unlink(get_img_real_path($portrait));
            $this->success('删除成功');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * @return \think\response\View
     * 修改密码
     */
    public function pwdAction()
    {
        if ($this->isPost){
            try{
                $dz_id = $this->_dz_info["dz_id"];
                $where = [
                    'dz_id' => $dz_id
                ];
                $dz_pwd = Dzgl::where(['dz_id'=>$dz_id])->value('pwd');

                $old_pwd = input('post.old_pwd/d')?:'';
                $pwd = input('post.pwd')?:'';
                $pwd2 = input('post.pwd2')?:'';

                if ($old_pwd != $dz_pwd) {
                    $this->error("旧密码验证失败,请输入正确的密码");
                }

                if (!$pwd) {
                    $this->error("新密码不能为空");
                }

                if ($pwd != $pwd2) {
                    $this->error("新密码确认输入不一致");
                }
                $save_data = array("pwd" => $pwd);
                $is_success = Dzgl::update($save_data,$where)->result;

                if ($is_success === false) {
                    $this->error("更换读者密码失败，请稍后再试!");
                }

                $this->success("修改密码成功");
            }catch (Exception $e){
                $this->error($e->getMessage());
            }
        }
        return view();
    }

    public function expect_logAction()
    {
        return view();
    }

    public function expectJsonAction()
    {
        $condition = ['dz_id'=>$this->_dz_info['dz_id']];
        $param = $this->getQueryParams();

        $list = ExpectLog::getPageList($condition, $param->limit, $param->order);
        $count = ExpectLog::getCount($condition);

        if ($list){
            foreach ($list as &$item){
                $item['ori_status'] = $item->status;
                $item['status'] = $item->statusOp;
            }
            unset($item);
        }

        return $this->echoPageData($list, $count);
    }

    /**
     *增加读者荐购阅读
     */
    public function addExpectAction()
    {
        try{
            if ($this->isPost){
                $dz_id = $this->_dz_info['dz_id'];
                if (!$dz_id){
                    $this->error('请先登陆');
                }
                $data = input('post.data/a',[]);
                if (empty($data)){
                    $this->error('图书信息为空');
                }
                $save_data = [
                    'firstauthor' => isset($data['author'])
                                    ? (is_array($data['author']) ? implode(',', $data['author']) : $data['author'])
                                    : '',
                    'dz_id' => $dz_id,
                    'title' => isset($data['title']) ? $data['title'] : '',
                    'publisher' => isset($data['publisher']) ? $data['publisher'] : '',
                    'pubdate' => isset($data['pubdate']) ? $data['pubdate'] : '',
                    'isbn' => isset($data['isbn13']) ? $data['isbn13'] : (isset($data['isbn10'])) ? $data['isbn10'] : 0,
                    'status' => ExpectLog::EXPECT_STATUS_ADD,
                    'add_time' => time(),
                    'tsg_code' => $this->_dz_info['tsg_code']
                ];
                if (!$save_data['isbn']){
                    $this->error('图书isbn信息为空');
                }
                if (!$save_data['publisher']){
                    $this->error('图书出版社为空');
                }
                $where = [
                    'dz_id' => $dz_id,
                    'isbn'=>$save_data['isbn']
                ];
                $expect = ExpectLog::field('isbn,dz_id')->where($where)->find();
                if ($expect){
                    $this->error('您已荐购阅读,请等待工作人员审核');
                }
                $is_success = ExpectLog::create($save_data,true)->result;
                if ($is_success === false){
                    $this->error('数据异常，请稍后再试');
                }
                $this->success('荐购成功，可以前往荐购列表查看');
            }
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    /**
     * 删除读者荐购
     */
    public function dropExpectAction()
    {
        try{
            $dz_id = input('dz_id/d',0);
            $expect_log_id = input('expect_log_id',0);
            if ($dz_id != $this->_dz_info['dz_id']) {
                $this->error('读者id错误，请稍后再试');
            }
            if (! $expect_log_id){
                $this->error('荐购id不存在');
            }
            $where = [
                'expect_log_id' =>  $expect_log_id
            ];
            $info = ExpectLog::field('dz_id,isbn,expect_log_id')->where($where)->find();
            if (!$info){
                $this->error('此荐购记录不存在');
            }
            $is_success = $info->delete();
            if ($is_success === false){
                $this->error('取消失败，请稍后再试');
            }
            $this->success('取消成功');
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
    }

    public function get_dz_portAction()
    {
        $time_id = input('time_id', '');
        $site_dir = ROOT_PATH;
        $file_name = "DzPhoto_thumb_{$time_id}_{$this->_dz_info["dz_id"]}.jpg";
        $file_patch = $site_dir . "public/uploads/dzgl/" . $file_name;
        $re_url = "/uploads/dzgl/" . $file_name;
        $data = ['file_name'=>'dzgl/'.$file_name];
        if (file_exists($file_patch)) {
//            $image = Image::open($file_patch);
            $data['re_url'] = request()->domain() . $re_url;
            $this->success('ok','',$data);
        }
        else {
            $this->error('');
        }
    }
}
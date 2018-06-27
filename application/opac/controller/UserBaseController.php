<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/15
 * Time: 17:18
 */

namespace app\opac\controller;

class UserBaseController extends BaseController
{
    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _initialize()
    {
        parent::_initialize();
//        import("PhpCas/PhpCasTool",EXTEND_PATH,'.class.php');
//        $cas_obj = \PhpCasTool::getInstance();
//
//        if ($cas_obj->isOpen()) {
//            $dz_info = $cas_obj->login();
//
//            if ($dz_info === false) {
//                $this->error($cas_obj->getError(), url("User/login"));
//            }
//            else {
//                session("dz_info", $dz_info);
//                $this->_dz_info = $dz_info;
//            }
//        }
        if (!$this->check_login()) {
           $this->redirect(url('opac/Login/index'));
        }
    }
}
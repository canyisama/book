<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/5/18
 * Time: 16:22
 */

namespace app\opac\controller;


class ErrorController extends BaseController
{
    public function _empty()
    {
        return $this->redirect(url('User/index'));
    }

}
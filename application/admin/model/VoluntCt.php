<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-18
 * Time: 14:22
 */

namespace app\admin\model;


class VoluntCt extends Base
{

    public function unique($tsg_code, $ct_name, $volunt_ct_id = 0)
    {
        $volunt_ct_id = $this->field("volunt_ct_id")->where(array(
            "ct_name" => $ct_name,
            "volunt_ct_id" => array("neq", $volunt_ct_id),
            'tsg_code' => $tsg_code
        ))->find();
        if ($volunt_ct_id) {
            return false;
        }

        return true;
    }

}
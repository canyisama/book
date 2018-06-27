<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-04-18
 * Time: 13:48
 */

namespace app\admin\model;


class VoluntType extends Base
{

    public function unique($tsg_code, $type_name, $volunt_type_id = 0)
    {
        $volunt_type_id = $this->field("volunt_type_id")->where(array(
            "type_name" => $type_name,
            "volunt_type_id" => array("neq", $volunt_type_id),
            'tsg_code' => $tsg_code
        ))->find();
        if ($volunt_type_id) {
            return false;
        }

        return true;
    }

}
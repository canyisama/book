<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 9:24
 */

namespace app\admin\model;


class Clc extends Base
{
    public static function getTopClcList()
    {
        return self::where("lay=0")->order("clc_id")->select();
    }

    public static function getChild($clc_id)
    {
        $clc_list = self::where("pid=$clc_id")->order("clc_id")->select();

        if (!empty($clc_list)) {
            $ids = array();
            foreach ($clc_list as $item) {
                $ids[] = $item["clc_id"];
            }
            $where = array(
                "pid" => array("in", $ids)
            );
            $has_child = self::field("pid")->where($where)->group("pid")->select();
            $pids = array();
            foreach ($has_child as $item) {
                $pids[] = $item["pid"];
            }
            foreach ($clc_list as $key => $item) {
                $clc_list[$key]["has_child"] = (in_array($item["clc_id"], $pids) ? 1 : 0);
            }
        }
        return $clc_list;
    }

    public static function getListByParent($clc_id = 0)
    {
        return self::formantList(['pid' => $clc_id]);

        /*$parentIds = self::getParentIds($clc_id);   // 获取当前节点的所有父级元素(递归)
        $clc_list = self::formantList(['lay' => 0]);
        foreach ($clc_list as $key => $item) {
            $item = self::getNodeList($item, $parentIds, $clc_id);  // 递归展开当前选中的节点
            $clc_list[$key] = $item;
        }
        return $clc_list;*/
    }

    /**
     * 返回jsTree格式的数组
     * @param $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function formantList($where)
    {
        $list = self::where($where)->order('clc_id')->select();
        $result = [];
        foreach ($list as $item) {
            $data = [
                'id' => $item['clc_id'],
                'clc' => $item['clc'],
                'text' => $item['clc_desc']
            ];
            $count = self::where('pid', $item['clc_id'])->count();
            $data['icon'] = $count > 0;
            $data['children'] = $count > 0;
            $result[] = $data;
        }
        return $result;
    }

    public static function getNodeList($clc, $parentIds, $cur_clc_id = 0)
    {
        if (in_array($clc['id'], $parentIds)) {
            $list = self::formantList(['pid' => $clc['id']]);
            foreach ($list as $key => $item) {
                $item = self::getNodeList($item, $parentIds, $cur_clc_id);
                $count = self::where('pid', $item['id'])->count();

                $item['icon'] = $count > 0;
                if ($item['id'] == $cur_clc_id && $count > 0) {   // 当前节点如果有子级则全部展开
                    $item['children'] = self::formantList(['pid' => $item['id']]);
                    $item['state'] = ['opened' => true];
                }
                $list[$key] = $item;
            }
            $clc['children'] = $list;
            $clc['state'] = ['opened' => true];
        }

        $count = self::where('pid', $clc['id'])->count();
        $clc['icon'] = $count > 0;
        if ($clc['id'] == $cur_clc_id) {  // 当前节点如果有子级则全部展开
            if ($count > 0) {
                $clc['children'] = self::formantList(['pid' => $clc['id']]);
                $clc['state'] = ['opened' => true];
            }
        }
        return $clc;
    }

    public static function getParentIds($clc_id, $result = [])
    {
        $clc = self::get($clc_id);
        if (!$clc || !$clc['pid']) {
            return $result;
        }
        $result[] = $clc['pid'];
        $result = self::getParentIds($clc['pid'], $result);
        return $result;
    }

}
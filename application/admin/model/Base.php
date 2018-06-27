<?php

namespace app\admin\model;

use think\Model;

class Base extends Model
{
    //操作结果
    public $result = null;
    protected $dateFormat = 'Y-m-d';
    /**
     * 重写Model插入方法（返回模型对象包含操作结果）
     * @param array $data
     * @param null $field
     * @return static
     */
    public static function create($data = [], $field = null)
    {
        $model = new static();
        if (!empty($field)) {
            $model->allowField($field);
        }
        $model->result = $model->isUpdate(false)->save($data, []);
        return $model;
    }

    /**
     * 重写Model更新方法（返回模型对象包含操作结果）
     * @param array $data
     * @param array $where
     * @param null $field
     * @return static
     */
    public static function update($data = [], $where = [], $field = null)
    {
        $model = new static();
        if (!empty($field)) {
            $model->allowField($field);
        }
        $model->result = $model->isUpdate(true)->save($data, $where);
        return $model;
    }

    public static function getPageList($condition = [], $limit = '', $order = '', $field = '', $group = '')
    {

        $model = new static();
        $list = $model->where($condition)->limit($limit)->order($order)->field($field)->group($group)->select();
        return $list;

    }

    public static function getCount($condition = [])
    {
        $model = new static();
        return $model->where($condition)->count();
    }

    /**
     * @param int $tsg_code         @分馆代码
     * @param string $field_code    @数据库字段1
     * @param string $field_name    @数据库字段2
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取不重复的数组
     */
    public static function getMap($field_code = '',$field_name = '',$tsg_code = 0)
    {
        $re_array = [];
        if (empty($field_code) || empty($field_name)){
            return $re_array;
        }
        $field = $field_code.','.$field_name;
        if ($tsg_code == 0){
            $tsg_list = self::field($field)->select();
        }else{
            $tsg_list = self::field($field)->where(['tsg_code'=>$tsg_code])->select();
        }


        foreach ($tsg_list as $item ) {
            $re_array[$item[$field_code]] = $item[$field_name];
        }

        return $re_array;
    }
}
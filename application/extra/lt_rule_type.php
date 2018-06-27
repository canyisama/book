<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/4/11
 * Time: 20:11
 * 流通规则配置
 */
return [
    'is_close' => [
        0 => '启用',
        1 => '禁用'
    ],
    'lose_mode' => [
        1 => '罚金',
        2 => '罚金+超期'
    ],
    'lose_type' => [
        1 => '单价',
        2 => '套价'
    ],
    'dirty_mode' => [
        1 => '罚金',
        2 => '罚金+超期'
    ],
    'dirty_type' => [
        1 => '单价',
        2 => '套价'
    ],
    'renew_mode' => [
        1 => '当天+续期',
        2 => '还期+续期'
    ]
];
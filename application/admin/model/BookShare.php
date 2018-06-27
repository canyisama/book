<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/9
 * Time: 17:53
 */

namespace app\admin\model;


class BookShare extends Base
{
    public function fullSearch($option, $sear_type = 1)
    {
        $field = $option["field"];
        $where = $option["where"];
        $user_id = $option["user_id"];
        $order_field = $option["order_field"];
        $order_seq = (isset($option["order_seq"]) ? $option["order_seq"] : "ASC");
        $page = $option["page"];
        $page_size = $option["page_size"];
        $mod_user = d("User");
        $mt_id = $mod_user->get_mt_id($user_id);
        $sour_table = $this->getTable();
        $mod_indexrel = d("Indexrel");
        $index_list = $mod_indexrel->where("sour_table='$sour_table' AND mt_id=$mt_id")->select();
        import('Scws\Scws', EXTEND_PATH, '.class.php');
        import('Isbn\IsbnBase', EXTEND_PATH, '.class.php');
        $rel_info = array();
        $pkey_name = "";

        foreach ($index_list as $item) {
            $rel_info[$item["sour_field"]] = array("dest_table" => $item["dest_table"], "dest_mod" => $item["dest_mod"], "order_table" => $item["order_table"], "order_mod" => $item["order_mod"]);
            $pkey_name = $item["sour_pkey"];
        }

        $search_field = array("op_user", "add_time");

        foreach ($where as $key => $item) {
            if (!isset($rel_info[$key]) && !in_array($key, $search_field)) {
                unset($where[$key]);
            }
        }

        $limit_begin = (!empty($page) ? ($page - 1) * $page_size : 0);

        if (empty($where)) {
            if ($sear_type == 2) {
                return $this->count("0");
            } else if ($sear_type == 1) {
                return $this->limit($limit_begin . "," . $page_size)->select();
            }
        }

        $table_list = array();
        $where_list = array();
        $order_str = "";

        foreach ($where as $key => $item) {
            if (!empty($rel_info[$key]["dest_table"])) {
                $table_list[] = $rel_info[$key]["dest_table"];
            }

            if ($key == "tsg_code") {
                $where_list[] = "{$rel_info[$key]["dest_table"]}.val='$item'";
            } else {
                if (($key == "title") || ($key == "subject")) {
                    $tmp_str = \Scws::segment_str($item, array("head" => "+"));
                    $where_list[] = "MATCH({$rel_info[$key]["dest_table"]}.val) AGAINST('$tmp_str'IN BOOLEAN MODE)";
                } else if ($key == "op_user") {
                    $where_list[] = "{$rel_info[$key]["dest_table"]}.val='$item'";
                } else if ($key == "add_time") {
                    $where_list[] = "{$rel_info[$key]["dest_table"]}.val between {$item["beg"]} AND {$item["end"]}";
                } else if ($key == "clc") {
                    $clc_str = \IsbnBase::formatClc($item);
                    $where_list[] = "MATCH({$rel_info[$key]["dest_table"]}.val) AGAINST('$clc_str*'IN BOOLEAN MODE)";
                } else if ($key == "isbn") {
                    import('Isbn\IsbnBase', EXTEND_PATH, '.class.php');
                    $is_issn = ((substr($item, 0, 3) == "977") || ((substr($item, 4, 1) == "-") && (strlen($item) < 10)) ? true : false);
                    $issn = (substr($item, 0, 3) == "977" ? substr(str_replace("-", "", $item), 3) : str_replace("-", "", $item));
                    $isbn = (!$is_issn ? \IsbnBase::convIsbnCode($item) : $issn);
                    $where_list[] = "MATCH({$rel_info[$key]["dest_table"]}.val) AGAINST('$isbn*'IN BOOLEAN MODE)";
                } else {
                    $where_list[] = "MATCH({$rel_info[$key]["dest_table"]}.val) AGAINST('$item*'IN BOOLEAN MODE)";
                }
            }
        }

        if (!isset($rel_info[$order_field])) {
            unset($order_field);
        }

        if (!empty($order_field) && isset($rel_info[$order_field])) {
            if ($sear_type == 1) {
                $table_list[] = $rel_info[$order_field]["order_table"];
            }

            $order_str = $rel_info[$order_field]["order_table"] . ".val $order_seq";
        }

        $table_list = array_unique($table_list);
        $table_cnt = count($table_list);
        $table_str = "";
        $where_str = implode(" AND ", $where_list);
        $last_table = "";

        if ($table_cnt == 1) {
            $table_str = $table_list[0];
            $last_table = $table_list[0];
        } else {
            for ($i = 0; $i < $table_cnt; $i++) {
                if ($i == 0) {
                    $table_str = $table_list[$i];
                } else {
                    $table_str .= " INNER JOIN $table_list[$i] ON {$table_list[$i - 1]}.bid=$table_list[$i].bid";
                }

                $last_table = $table_list[$i];
            }
        }

        if ($sear_type == 2) {
            $count_sql = "select count(*) as tp_count from " . $table_str . ' where ' . $where_str;
            $r = $this->query($count_sql);
            return $r[0]['tp_count'];
        } else if ($sear_type == 1) {
            $select_sql = "select $last_table.bid from " . $table_str . ' where ' . $where_str;
            if ($order_str) {
                $select_sql .= ' order by ' . $order_str;
            }
            $select_sql .= ' limit ' . $limit_begin . ',' . $page_size;
            //$ids_tmp = $this->field("$last_table.bid")->table($table_str)->where($where_str)->limit($limit_begin . "," . $page_size)->order($order_str)->select();
            $ids_tmp = $this->query($select_sql);
            $ids = array();

            foreach ($ids_tmp as $item) {
                $ids[] = $item["bid"];
            }
            if (empty($ids)) {
                $ids[] = 0;
            }
            $where_get_data = array(
                $pkey_name => array("in", $ids)
            );
            $ids_str = (!empty($ids) ? implode(",", $ids) : "0");
            $r = $this->field($field)->where($where_get_data)->orderRaw("FIND_IN_SET($pkey_name,'$ids_str')")->select();
            return $r;
        } else if ($sear_type == 3) {
            try {
                $mod_indexrel = d("indexrel");
                $index_list = $mod_indexrel->field("dest_mod,order_mod")->where("sour_mod='Book_share' AND mt_id=$mt_id")->select();
                $this->startTrans();
                $select_sql = "select $last_table.bid from " . $table_str . ' where ' . $where_str;
                //$ids_tmp = $this->field("$last_table.bid")->table($table_str)->where($where_str)->select();
                $ids_tmp = $this->query($select_sql);
                $ids_tmp = array_chunk($ids_tmp, 3000);
                if (!$ids_tmp || !$index_list) {
                    return true;
                }
                $task_cnt = 0;
                $task_per = 100 / (count($ids_tmp) * count($index_list));

                foreach ($ids_tmp as $item) {
                    $ids = array();

                    foreach ($item as $item1) {
                        $ids[] = $item1["bid"];
                    }
                    if (!empty($ids)) {
                        $ids = implode(",", $ids);
                        $is_success = $this->where("book_share_id in($ids)")->delete();
                        if ($is_success === false) {
                            $this->rollback();
                            $this->error = "删除失败:更新数据库失败";
                            return false;
                        }

                        foreach ($index_list as $item) {
                            $mod_dest = d($item["dest_mod"]);
                            $is_success = $mod_dest->where("bid in($ids)")->delete();
                            if ($is_success === false) {
                                $this->rollback();
                                $this->error = "删除书目索引数据失败:更新数据库失败";
                                return false;
                            }

                            if ($item["dest_mod"] != $item["order_mod"]) {
                                $mod_order = d($item["order_mod"]);
                                $is_success = $mod_order->where("bid in($ids)")->delete();
                                if ($is_success === false) {
                                    $this->rollback();
                                    $this->error = "删除书目排序索引数据失败:更新数据库失败";
                                    return false;
                                }
                            }
                            $task_cnt += $task_per;
                        }
                        $this->commit();
                    }
                }
                return true;
            } catch (Exception $e) {
                $this->rollback();
                $this->error = "删除失败！错误提示:程序出现异常!";
                return false;
            }
        }
    }

}
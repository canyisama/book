<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18
 * Time: 15:36
 */

/**
 * 把某个键值作为下标
 * @param $array
 * @param $key
 * @param int $type
 * @return array
 */
function array_under_reset($array, $key, $type = 1)
{
    if (is_array($array)) {
        $tmp = array();
        foreach ($array as $v) {
            if ($type === 1) {
                $tmp[$v[$key]] = $v;
            } elseif ($type === 2) {
                $tmp[$v[$key]][] = $v;
            }
        }
        return $tmp;
    } else {
        return $array;
    }
}

function fmt_date($time)
{
    if ($time)
        return date('Y-m-d', $time);
    return '';
}

function fmt_date_time($time)
{
    if ($time)
        return date('Y-m-d H:i:s', $time);
    return '';
}

function mstrtotime($data_str)
{
    if (!$data_str) {
        return 0;
    }

    try {
        $date_obj = new DateTime($data_str);
        return $date_obj->format("U");
    } catch (Exception $e) {
        return 0;
    }
}

function get_img_path($img)
{
    return config('upload_path') . $img;
}

function get_img_full_path($img)
{
    if (empty($img)){
        return '';
    }
    return config('base_url') . config('upload_path') . $img;
}

/**
 * X:xx\xx\xxx\x.png
 * @param $img
 * @return string
 */
function get_img_real_path($img)
{
    return ROOT_PATH . 'public' . config('upload_path') . $img;
}

if (!function_exists('d')) {
    // 加载model
    function d($model = '')
    {
        return \think\Loader::model($model);
    }
}

if (!function_exists('c')) {
    // 获取配置
    function c($name = '')
    {
        if (!$name)
            return null;
        if (strpos($name, '.') !== false) {
            $name_list = explode('.', $name);
            if ($name_list && is_array($name_list)) {
                return config($name_list[0])[$name_list[1]];
            }
        } else {
            return config($name);
        }
    }
}

if (!function_exists('l')) {
    //加载语言
    function l($name)
    {
        return \think\Lang::get($name);
    }
}

function loadData($file_name)
{
    $file_path = APP_PATH . "extra" . DS . $file_name . ".php";
    if (empty($file_name) || !file_exists($file_path)) {
        return array();
    }

    $data = require ($file_path);
    return $data;
}


function loadDataSave($file_name, $save_data)
{
    $file_path = APP_PATH . 'extra' . DS . $file_name . '.php';
    if (empty($file_name) || !file_exists($file_path)) {
        return false;
    }

    $data = require($file_path);
    $data = array_merge($data, $save_data);
    return file_put_contents($file_path, "<?php \nreturn " . var_export($data, true) . ";\n?>");
}

function fpath($path)
{
    $re_path = str_replace("/", DIRECTORY_SEPARATOR, $path);
    $re_path = str_replace("\\", DIRECTORY_SEPARATOR, $re_path);
    return $re_path;
}

function syncRequest($url)
{
    $fp = fsockopen("127.0.0.1", $_SERVER["SERVER_PORT"], $errno, $errstr, 5);

    if (!$fp) {
        return false;
    }

    $host = $_SERVER["HTTP_HOST"];
    $out = "GET $url  / HTTP/1.1\r\n";
    $out .= "Host:$host \r\n";
    $out .= "Connection: Close\r\n\r\n";
    fputs($fp, $out);
    fclose($fp);
    return true;
}

function mdate(string $format, int $timestamp = NULL)
{
    if (is_null($timestamp)) {
        $timestamp = time();
    }

    try {
        $date_obj = new DateTime("@$timestamp");
        $date_obj->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $date_obj->format($format);
    } catch (Exception $e) {
        return "";
    }
}
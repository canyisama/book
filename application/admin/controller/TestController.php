<?php
/**
 * Created by PhpStorm.
 * User: void
 * Date: 2018/6/8
 * Time: 16:32
 */

namespace app\admin\controller;


use app\admin\model\Dzgl;
use think\Controller;

class TestController extends Controller
{
    public function indexAction()
    {

        $arr1 = [
            'name'=>['2222','3333'],
            'sex' => 1];
        $arr2 = [
            'name'=>1
        ];
        $arr3 = array_merge($arr1,$arr2);
        return var_dump($arr3);
        $aa = Dzgl::field('tsg_code,count(dz_id) as dz_cnt,count("dz_status") as dz_valid')->where(['dz_status'=>'有效'])->group('tsg_code')->select();
        return var_dump($aa);
//        $path = ROOT_PATH."sphinx\api\sphinxapi.php";
//        require ($path);
//
////        $docs = array
////        (
////            "this is my test text to be highlighted, and for the sake of the testing we need to pump its length somewhat",
////            "another test text to be highlighted, below limit",
////            "test number three, without phrase match",
////            "final test, not only without phrase match, but also above limit and with swapped phrase text test as well",
////        );
////        $words = "test text";
////        $index = "test1";
////        $opts = array
////        (
////            "before_match"		=> "<b>",
////            "after_match"		=> "</b>",
////            "chunk_separator"	=> " ... ",
////            "limit"				=> 60,
////            "around"			=> 3,
////        );
////
////        foreach ( array(0,1) as $exact )
////        {
////            $opts["exact_phrase"] = $exact;
////            print "exact_phrase=$exact\n";
////
////            $cl = new \SphinxClient ();
////            $res = $cl->BuildExcerpts ( $docs, $index, $words, $opts );
////            if ( !$res )
////            {
////                die ( "ERROR: " . $cl->GetLastError() . ".\n" );
////            } else
////            {
////                $n = 0;
////                foreach ( $res as $entry )
////                {
////                    $n++;
////                    print "n=$n, res=$entry\n";
////                }
////                print "\n";
////            }
////        }
//        $docs = array
//        (
//            "this is my test text to be highlighted, and for the sake of the testing we need to pump its length somewhat",
//            "another test text to be highlighted, below limit",
//            "test number three, without phrase match",
//            "final test, not only without phrase match, but also above limit and with swapped phrase text test as well",
//        );
//        $cl = new \SphinxClient();
//        $cl->setServer('127.0.0.1', 9312);
////        $cl->_Connect('127.0.0.1', 9312);
//        $query ='test';
//        $res = $cl->query($query, 'test1');
//        #$cl->SetMatchMode(SPH_MATCH_EXTENDED); //使用多字段模式
//        //dump($cl);
////        $index='test1';
////        $keyword = 'this';
////        $res = $cl->Query($keyword, $index);
////        $err = $cl->GetLastError();
////
////        var_dump($err);
//        var_dump($res);exit();
//
//        $words = "test text";
//        $index = "test1";
//        $opts = array
//        (
//            "before_match"		=> "<b>",
//            "after_match"		=> "</b>",
//            "chunk_separator"	=> " ... ",
//            "limit"				=> 60,
//            "around"			=> 3,
//        );
//
//        foreach ( array(0,1) as $exact )
//        {
//            $opts["exact_phrase"] = $exact;
////            print "exact_phrase=$exact\n";
//
//            $cl = new \SphinxClient ();
//
////            var_dump($cl);exit();
//            $res = $cl->BuildExcerpts ( $docs, $index, $words, $opts );
//            if ( !$res )
//            {
//                die ( "ERROR: " . $cl->GetLastError() . ".\n" );
//            } else
//            {
//                $n = 0;
//                foreach ( $res as $entry )
//                {
//                    $n++;
//                    print "n=$n, res=$entry\n";
//                }
//                print "\n";
//            }
//        }
//        return var_dump($path);
    }

}
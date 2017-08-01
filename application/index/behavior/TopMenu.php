<?php
namespace app\index\behavior;
use \think\Db;
use \think\view\View;
use \think\Config;

Class TopMenu{
    public function run(&$params)
    {
        if (!($menu = cache('category_6'))){
            $menu = [];
            $category = Db::name("category")->where("parent_id=6")->select();
            dump($category);
            foreach ($category as $cat){
                $menu[$cat['category_id']] = $cat;
            }
            foreach ($menu as $k => $v){
                if ($menu['pid'] != 6) {
                    $menu[$menu['pid']]['children'][] = &$menu[$k];
                }
            }
            cache("category_6",$menu);
        }

        dump($menu);
        //View::instance(Config::get('template'), Config::get('view_replace_str'));
    }
}
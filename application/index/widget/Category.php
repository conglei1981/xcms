<?php
namespace app\index\widget;
use think\Controller;

Class Category extends Controller
{
    public $category = [];
    public function _initialize()
    {
        if (!($menu = cache('category_cache'))){
            $menu = [];
            $category = db("category")->order("parent_id")->select();
            foreach ($category as $cat){
                $menu[$cat['category_id']] = $cat;
            }
            $tree = [];
            foreach ($menu as $k => $v){
                if ($v['parent_id'] != 0) {
                    $menu[$v['parent_id']]['children'][] = &$menu[$k];
                }
            }
            cache("category_cache",$menu);
        }
        $this->category = $menu;
    }
    public function menu($id = 6)
    {
        $this->assign("menu",$this->category[6]['children']);
        return $this->fetch("widget/menu");
    }

    public function bread($id)
    {
        $bread = [];
        $bread[] = $this->category[$id];

        while(0 !=($pid = $this->category[$id]['parent_id'])){
            $id = $pid;
            array_unshift($bread,$this->category[$id]);
        }
        $this->assign("bread",$bread);
        return $this->fetch("widget/bread");
    }

    public function brother($id)
    {
        $me = $this->category[$id];
        $brother = $this->category[$me['parent_id']]['children'];
        $this->assign("brother",$brother);
        return $this->fetch("widget/brother");
    }
}
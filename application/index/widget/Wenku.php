<?php
namespace app\index\widget;
use think\Controller;

Class Wenku extends Controller
{
    public function relative($key)
    {
        $relative = db('document')->alias('d')->field('d.*')->join("wkrelative r","d.sign=r.to_sign")->where(['r.from_sign'=>$key])->select();
        $this->assign("relative",$relative);
        return $this->fetch("widget/relative");
    }
}
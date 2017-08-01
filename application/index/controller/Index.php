<?php
namespace app\index\controller;

use GuzzleHttp\Psr7\Request;
use think;
class Index extends think\Controller
{
    public function index()
    {
        $new = think\Db::name("document")->order('id','desc')->limit(10)->select();
        $this->assign([
            'new'=>$new
        ]);
        return $this->fetch();
    }
    public function category()
    {
        $p = input("p");
        $p = $p ? $p : 1;
        $categoryid = input("id");
        $wenku = think\Db::name("document")->where(['category_id'=>$categoryid])->paginate(15);
        $this->assign([
            'lists'=>$wenku,
            'page' => $wenku->render()
        ]);
        return $this->fetch();
    }

    public function content()
    {
        $documentid = input("id");

        $document = think\Db::name("document")->where(['id'=>$documentid])->find();
        $wenku = think\Db::name("wenku")->where(['document_id'=>$documentid])->find();

        $this->assign([
            'document'=>$document,
            'wenku'=>$wenku
        ]);
        return $this->fetch();
    }
}

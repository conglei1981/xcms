<?php
namespace app\spider\controller;
set_time_limit(0);
use app\wenku\model\Document;
use app\wenku\model\Wenku;
use think\Queue;
class Index
{
    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:)</h1><p> ThinkPHP V5<br/><span style="font-size:30px">十年磨一剑 - 为API开发设计的高性能框架</span></p><span style="font-size:22px;">[ V5.0 版本由 <a href="http://www.qiniu.com" target="qiniu">七牛云</a> 独家赞助发布 ]</span></div><script type="text/javascript" src="http://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script><script type="text/javascript" src="http://ad.topthink.com/Public/static/client.js"></script><thinkad id="ad_bd568ce7058a1091"></thinkad>';
    }

    public function wenku()
    {

/*        for ($i = 0;$i<1;$i++){
            $this->doclist(126,$i,$i+2);
        }
        return '';*/

        $doc = new Document();
        $doc->data([
            'title'=>'这是一个测试数据'
        ]);
        $wk = new Wenku();
        $wk->post = '测试一下';
        $doc->wenku = $wk;
        $rs = $doc->together('wenku')->save();
        dump($doc);
        dump($rs);
        return 0;
        
        $cats = \think\Db::name('category')->where("parent_id !=0 and parent_id !=6")->select();
        foreach ($cats as $cat){
            for ($i = 0;$i<1;$i++){
                $this->doclist($cat['category_id'],$i,$i+2);
            }
        }

    }

    private function doclist($cat,$reqId,$pn)
    {
        // 1.当前任务将由哪个类来负责处理。
        //   当轮到该任务时，系统将生成一个该类的实例，并调用其 fire 方法
        $jobHandlerClassName  = 'app\spider\job\BaiDu';
        // 2.当前任务归属的队列名称，如果为新队列，会自动创建

        $jobQueueName  	  = "wenkuQueue";
        // 3.当前任务所需的业务数据 . 不能为 resource 类型，其他类型最终将转化为json形式的字符串
        //   ( jobData 为对象时，需要在先在此处手动序列化，否则只存储其public属性的键值对)
        $jobData       	  = [ 'task' => 'wenkuList', 'config' => [
            'client' =>[
                'agent' => 'mobile',
            ],
            'url'=> sprintf('https://wk.baidu.com/cat/%d?pagelets[]=doclist@page_container&reqID=%d&pn=%d',$cat,$reqId,$pn),
            'nexturl'=>[
                'url'=>'https://wk.baidu.com/cat/%d?pagelets[]=doclist@page_container&reqID=%d&pn=%d',
                'param'=>[
                    'cid'=>$cat,
                    'reqid'=>$reqId+1,
                    'pn'=>$pn+1
                ]
            ],
            'fields'=>[
                [
                    'name'=>'lists',
                    'callback'=>\spider\Spider::serialize(function($dom,$pq){
                        preg_match('/"html":"(.*)","js"/',$dom,$obj);
                        $rs = stripslashes(str_replace("\\n",'',$obj[1]));
                        $as = $pq($rs)->find('li[data-url]');
                        $links = [];
                        foreach ($as as $a){
                            $links[] = "https://wk.baidu.com".$pq($a)->attr('data-url');
                        }
                        return $links;
                    })
                ]
            ]
        ] ] ;

        // 4.将该任务推送到消息队列，等待对应的消费者去执行
        //dump($jobData);
        return Queue::push( $jobHandlerClassName , $jobData , $jobQueueName );
    }
}

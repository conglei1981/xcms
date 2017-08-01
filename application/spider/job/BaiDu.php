<?php
namespace  app\spider\job;
set_time_limit(0);
use think\Log;
use think\queue\Job;
use spider\Spider;
use think\Queue;
class BaiDu {

    /**
     * fire方法是消息队列默认调用的方法
     * @param Job            $job      当前的任务对象
     * @param array|mixed    $data     发布任务时自定义的数据
     */
    public function fire(Job $job,$data){
        $task = $data['task'];
        echo $data['config']['url'],"   start \n";
        try{
            $config = $data['config'];
            if (isset($config['nexturl'])) {
                if (isset($config['nexturl']['param'])){
                    $config['url'] = sprintf($config['nexturl']['url'],$config['nexturl']['param']['cid'],$config['nexturl']['param']['reqid'],$config['nexturl']['param']['pn']);
                    $config['nexturl']['param']['reqid'] += 1;
                    $config['nexturl']['param']['pn'] += 1;
                    $jobData       	  = [ 'task' => 'wenkuList', 'config' => $config ] ;
                    echo "add next task  ".($config['url'])."\n";
                    Queue::push( 'app\spider\job\BaiDu' , $jobData , "wenkuQueue" );
                }else{
                    $config['url'] = $config['nexturl']['url'];
                    unset($config['nexturl']);
                    $jobData       	  = [ 'task' => 'wenkuList', 'config' => $config ] ;
                    echo "add next task  ".($config['url'])."\n";
                    Queue::push( 'app\spider\job\BaiDu' , $jobData , "wenkuQueue" );
                }
            }

            $limit = md5($data['config']['url']);
            if (0 < ($count = db('wklimit')->where(['url'=>$limit])->count())){
                $job->delete();
                echo $data['config']['url'],"   exists \n\n\n";
                return "<info>Hello Job has been done and deleted"."</info>\n";
            }

            $isJobDone = $this->$task($data['config']);
            if ($isJobDone) {
                //如果任务执行成功， 记得删除任务
                $job->delete();
                echo $data['config']['url'],"   ok \n\n\n";
                return "<info>Hello Job has been done and deleted"."</info>\n";
            }else{
                if ($job->attempts() > 2) {
                    //通过这个方法可以检查这个任务已经重试了几次了
                    echo $data['config']['url'],"   faild \n\n\n";
                    $job->delete();
                    return "<warn>Hello Job has been retried more than 3 times!"."</warn>\n";
                    // 也可以重新发布这个任务
                    //print("<info>Hello Job will be availabe again after 2s."."</info>\n");
                    //$job->release(2); //$delay为延迟时间，表示该任务延迟2秒后再执行
                }
            }
        }catch (\think\Exception $e){
            echo $data['config']['url'],"   error \n\n\n";
            \think\Log::error($data['config']['url']);
            \think\Log::error($e->getFile());
            \think\Log::error($e->getLine());
            \think\Log::error($e->getCode());
            \think\Log::error($e->getMessage());
            $job->delete();
        }

    }
    private function wenkuList($config){
        $spider = new Spider;
        $limit = md5($config['url']);
        $config['url'] .= '&t='.time();
        $rs = $spider->fetch($config);
        $jobHandlerClassName  = 'app\spider\job\BaiDu';
        $jobQueueName  	  = "wenkuQueue";
        $c = [
            'client' =>[
                'agent' => 'mobile',
                'referer'=>$rs['response']['referer'],
                'cookies'=>$rs['response']['cookies'],
            ],
            'url'=> '',
            'fields'=>[
                [
                    'name'=>'view',
                    'callback'=>\spider\Spider::serialize(function($dom,$pq){
                        return \app\spider\job\BaiDu::parseView($dom,$pq);
                    })
                ]
             ]
        ];
        if ($rs['lists']){
            foreach ($rs['lists'] as $url){
                $c['url'] = $url;
                echo "add view task  ".$url."\n";
                $jobData       	  = [ 'task' => 'wenkuView', 'config' => $c ] ;
                Queue::push( $jobHandlerClassName , $jobData , "wenkuviewQueue" );
                //return true;
            }
            db('wklimit')->insert(['url'=>$limit]);
        }
        return true;
    }
    private function wenkuView($config){
        $spider = new Spider;
        $rs = $spider->fetch($config);
        if(empty($rs) || !isset($rs['view']['type'])){
            echo "error get {$config['url']}\n";
            return false;
        }
        $view = $rs['view'];
        $view['source_url'] = $config['url'];
        $c = [
            'client' =>[
                'agent' => 'mobile',
                'referer'=>$rs['response']['referer'],
                'cookies'=>[],
                'rawheaders'=> [
                    "Connection"=> 'keep-alive',
                    'Cache-Control'=> 'max-age=0',
                    'Upgrade-Insecure-Requests'=> 1,
                    'Accept-Encoding'=> 'gzip, deflate, sdch, br',
                    'Accept-Language'=> 'zh-CN,zh;q=0.8'
                ]
            ],
            'url'=> ''
        ];
        switch ($view['type']){
            case 'ppt':
                $view['wenku'] = '';
                foreach ($view['urls'] as $k => $url){
                    $c['url'] = $url;
                    $c['filename'] =sprintf('data/wk/%d/%d/%s',date("Ym"),date("d"),md5($url));
                    $res = $spider->fetch($c);
                    if ($res){
                        $view['wenku'] .= sprintf("<p><img class=\"lazy\" data-original='/%s' alt='%s' width='%s'/></p>",$c['filename'],$view['title'].' 图'.$k,'100%');
                        echo "success get ppt $url \n\n";
                    }
                    else{
                        echo "fail get ppt $url \n\n";
                    }
                }
                break;
            case 'webapp':
                $view['wenku'] = '';
                foreach ($view['urls'] as $url){
                    $c['url'] = $url;
                    $c['fields'] = [
                        [
                            'name' => 'content',
                            'callback' => $spider->serialize(function($dom,$pq) use($view){
                                preg_match("/.*?\((.*?)\)$/ui",$dom,$match);
                                $json = (Array)json_decode($match[1]);
                                $imgurl = sprintf("https://wkrtcs.bdimg.com/rtcs/image?%s&l=webapp",$view['docinfo']['md5sum']);
                                $html = \spider\Spider::docXml($json['document.xml'],$imgurl);
                                return $html;
                            })
                        ]
                    ];
                    $res = $spider->fetch($c);

                    if ($res){
                        $view['wenku'] .= $res['content'];
                        echo "success get webapp $url \n\n";
                    }
                    else{
                        echo "fail get webapp $url \n\n";
                    }

                }
                break;
            case 'merge':
                $view['wenku'] = '';
                foreach ($view['urls'] as $merge){
                    $c['url'] = $merge['merge'];
                    $zoom = $merge['zoom'];
                    $c['fields'] = [
                        [
                            'name' => 'content',
                            'callback' => $spider->serialize(function($dom,$pq){
                                preg_match("/.*?\((.*?)\)$/ui",$dom,$match);
                                $json = json_decode($match[1]);
                                return $json;
                            })
                        ]
                    ];
                    $res = $spider->fetch($c);
                    $res = (array) $res;
                    if (!isset($res['content'])){
                        return false;
                    }
                    foreach ($res['content'] as $r){
                        $r =  (array) $r;
                        $page = $r['page'];
                        foreach ($r['parags'] as $o){
                                $o = (array) $o;
                                if (!isset($o['t'])){
                                    if (isset($o[0]))
                                       $view['wenku'] .= "<p>".$o[0]."</p>";
                                }else if($o['t'] == 'txt'){
                                    $view['wenku'] .= "<p>".$o['c']."</p>";
                                }else{
                                    $url = sprintf("%s&o=%s&type=pic&pn=%d",$zoom[$page], $o['o'],$page);
                                    $c['url'] = $url;
                                    $c['filename'] =sprintf('data/wk/%d/%d/%s',date("Ym"),date("d"),md5($url));
                                    $rs = $spider->fetch($c);
                                    $view['wenku'] .= sprintf("<p><img class=\"lazy\" data-original='/%s'/></p>",$c['filename']);
                                }
                        }
                    }


                    if ($res){
                        echo "success get merge {$merge['merge']} \n\n";
                    }
                    else{
                        echo "fail get merge {$merge['merge']} \n\n";
                    }

                }
                break;
            case "text":
                $view['wenku'] = '';
                foreach ($view['urls'] as $url){
                    $c['url'] = $url;
                    $c['fields'] = [
                        [
                            'name' => 'content',
                            'callback' => $spider->serialize(function($dom,$pq){
                                preg_match("/.*?\((.*?)\)$/ui",$dom,$match);
                                $json = json_decode($match[1]);
                                return $json;
                            })
                        ]
                    ];
                    $res = $spider->fetch($c);
                    foreach ($res['content'] as $r){
                        $r =  (array) $r;
                        foreach ($r['parags'] as $o){
                            $o = (array) $o;
                            if($o['t'] == 'txt'){
                                $view['wenku'] .= "<p>".$o['c']."</p>";
                            }
                        }
                    }


                    if ($res){
                        echo "success get text $url  \n\n";
                    }
                    else{
                        echo "fail get text $url \n\n";
                    }

                }
                break;
        }
        try{
            if (isset($view['wenku']) && $view['wenku']){
                $sign = $view['docid'];
                $limit = md5($view['source_url']);
                    $document = [
                        'title'=>$view['title'],
                        'category_id'=>$view['cid'],
                        'cover'=>$view['type'],
                        'description'=> trim($view['desc']) ? trim($view['desc']) : mb_substr(strip_tags($view['wenku']),0,100) ,
                        'keywords'=>$view['keyword'],
                        'model' => 'wenku',
                        'sign' => $sign,
                        'ctime' => time()
                    ];
                    $documentid = db('document')->insertGetId($document);
                    if ($documentid){
                        $wk = [
                            'document_id'=>$documentid,
                            'post'=>$view['wenku'],
                            'category_id'=>$view['cid'],
                            'source_url'=>$view['source_url']
                        ];
                        if (1 != ($wk_insert = db('wenku')->insert($wk)))
                        {
                            db('document')->where(['id'=>$documentid])->delete();
                        }else{
                            db('wklimit')->insert(['url'=>$limit]);
                            //抓取相关文档
                            $relative = sprintf("https://wk.baidu.com/view/api/relative?doc_id=%s&title=undefined&async=json&st=3",$view['docid']);
                            $referer = sprintf("https://wk.baidu.com/view/%s&page=",$view['docid']);
                            $res = $spider->fetch([
                                'url' => $relative,
                                'fields' => [
                                    [
                                        'name' => 'relative',
                                        'callback' => $spider->serialize(function($dom,$pq){
                                            return json_decode($dom);
                                        })
                                    ]
                                ]
                            ]);
                            if (isset($res['relative']) && is_array($res['relative'])){
                                $rela = [];
                                foreach ($res['relative'] as $rl){
                                    $rela[] = [
                                        'from_sign' => $sign,
                                        'to_sign' => $rl->docId
                                    ];
                                    $jobData       	  = [ 'task' => 'wenkuView', 'config' => [
                                        'client'=>['agent'=>'mobile','referer'=>$referer],
                                        'url'=> sprintf("https://wk.baidu.com/view/%s?page=",$rl->docId),
                                        'fields'=>[
                                            [
                                                'name'=>'view',
                                                'callback'=>\spider\Spider::serialize(function($dom,$pq){
                                                    return \app\spider\job\BaiDu::parseView($dom,$pq);
                                                })
                                            ]
                                        ]
                                    ] ] ;
                                    echo "add relative task ",$jobData['config']['url'],"\n";
                                    Queue::push( 'app\spider\job\BaiDu' , $jobData , "relativeQueue" );
                                }
                                db("wkrelative")->insertAll($rela);
                            }
                        }
                    }

            }
        }catch (\think\Exception $e){
            throw $e;
        }

        return true;
    }
    /**
     * @param $data
     */
    public function failed($data){


    }

    public static function parseView($dom,$pq){
        //doc word 1 pdf 7 execl 2 txt 13
        $doc = [];
        if(preg_match("/ppt\.main\(\{.*?docId: '(.*?)'.*?docIdUpdate: '(.*?)'.*?docTitle: '(.*?)'.*?md5sum: '(.*?)'.*?contentData: (.*?),\s*?totalPageNum.*?crumbs: (\[\{.*?\}\]).*?\}\);/is",$dom,$match)){
            //ppt
            $docIdUpdate = $match[2];
            $docId      =  $match[1];
            $md5sum = htmlspecialchars_decode($match[4]);
            $contentData = json_decode($match[5]);
            $cid = json_decode($match[6]);

            $doc['docid'] = $docId;
            $doc['docinfo'] = [
                'md5sum'=>$md5sum
            ];
            $doc['title'] = $match[3];
            $doc['docid'] = $docId;
            $doc['desc'] = '';
            $doc['keyword'] = '';
            $doc['cid'] = $cid[1]->cid;
            $doc['type'] = 'ppt';
            $doc['urls'] = [];
            foreach ($contentData as $zoom){
                $doc['urls'][] =    sprintf("https://wkretype.bdimg.com/retype/zoom/%s?o=jpg_6%s&pn=%d%s"
                    ,$docIdUpdate,$md5sum,$zoom->page,$zoom->zoom);
            }
        }else if (preg_match('/docInfo\:(.*?\"rsign\".*?\}).*?\);/is',$dom,$match) && isset($match[1])){
            $docinfo = json_decode(rtrim($match[1],','),true);
            $docinfo = $docinfo ? $docinfo : json_decode(rtrim($match[1],',')."}",true);
            $bucketNum = $docinfo['bucketNum'];
            $doc_id = $docinfo['doc_id'];
            $md5sum = htmlspecialchars_decode($docinfo['md5sum']);
            //$rtcs_flag = 1;
            $rsign = $docinfo['rsign'];

            $doc['docid'] = $doc_id;
            $doc['docinfo'] = [
                'md5sum'=>$md5sum
            ];
            $pn = 1;$rn = 5;
            if (isset($docinfo['rtcs_range_info'])){
                $doc['title'] = $docinfo['docInfo']['docTitle'];
                $doc['desc'] = $docinfo['docInfo']['docDesc'];
                $doc['keyword'] = $docinfo['docInfo']['keyWord'];
                $doc['cid'] = $docinfo['docInfo']['cid1'];
                $doc['type'] = 'webapp';
                $doc['urls'] = [];
                $rtcs_range_info = $docinfo['rtcs_range_info'];
                $range = '';
                foreach ($rtcs_range_info as $rri)
                {
                    $range .= $rri['range']."_";
                }

                $doc['urls'][] = sprintf("https://wkrtcs.bdimg.com/rtcs/webapp?bucketNum=%d&pn=%d&rn=%d&md5sum=%s&range=%s&rsign=%s&t=%s&callback=spiderCallback"
                    ,$bucketNum,$pn,$rn,$md5sum,rtrim($range,'_'),$rsign,time());
            }elseif (isset($docinfo['bcsParam'])&&$docinfo['bcsParam']){

                $doc['title'] = $docinfo['docInfo']['docTitle'];
                $doc['desc'] = $docinfo['docInfo']['docDesc'];
                $doc['keyword'] = $docinfo['docInfo']['keyWord'];
                $doc['cid'] = $docinfo['docInfo']['cid1'];
                $doc['type'] = 'merge';
                $doc['urls'] = [];
                $bcsParam = $docinfo['bcsParam'];
                $bcsParams = array_chunk($bcsParam,5);
                foreach ($bcsParams as $k=>$rris) {
                    $range = '';
                    foreach ($rris as $rri) {
                        $range .= $rri['merge'] . "_";
                        $doc['urls'][$k]['zoom'][$rri['page']] = sprintf("https://wkretype.bdimg.com/retype/zoom/%s?aimh=216%s%s"
                            , $doc_id, $md5sum, $rri['zoom']);
                    }
                    $pn = $k*5+1;
                    //pn=1&x=0&y=0&raww=892&rawh=690&o=jpg_6&type=pic&aimh=216

                    $doc['urls'][$k]['merge'] = sprintf("https://wkretype.bdimg.com/retype/merge/%s?%s&pn=%d&rn=%d&width=176&type=org&range=%s&rsign=%s&callback=spiderCallback"
                        , $doc_id, $md5sum, $pn, $rn, rtrim($range, '_'), $rsign);
                }

            }else{

                $doc['title'] = $docinfo['docInfo']['docTitle'];
                $doc['desc'] = $docinfo['docInfo']['docDesc'];
                $doc['keyword'] = $docinfo['docInfo']['keyWord'];
                $doc['cid'] = $docinfo['docInfo']['cid1'];
                $doc['type'] = 'text';
                $doc['urls'] = [];
                $totalPageNum = isset($docinfo['totalPageNum']) ? $docinfo['totalPageNum'] : 2;
                for ($pn = 1;$pn<$totalPageNum;$pn+=5){
                    $rn = ($totalPageNum - $pn) > 5 ? 5 : $totalPageNum - $pn;
                    $doc['urls'][] = sprintf("https://wkretype.bdimg.com/retype/text/%s?%s&pn=%d&rn=%d&width=176&type=txt&rsign=%s&callback=spiderCallback"
                        ,$doc_id,$md5sum,$pn,$rn,$rsign);
                }
            }

        }
        return $doc;
    }

}
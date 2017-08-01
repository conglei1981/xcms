<?php
namespace  app\spider\job;
set_time_limit(0);
use think\Log;
use think\queue\Job;
use spider\Spider;
use think\Queue;
class Doc
{
    /**http://www.360doc.cn/ajax/index/getReadRooms.ashx
      7 教育
     * 489 亲子 78 小学 120 初中 79 高中 80 大学 134 教师教学 123 公务员考试 83 外语学习 490 移民留学 132 大课堂
     *
     * 6 健康
     * 55 中医养生 53 运动减肥 52 饮食食疗 178 常见疾病 172 杂症偏方 125 近视失眠养发 176 风湿骨科 177 肝胆肾癌症 179 心脏血液 160 经穴治病 180 健康知识
     *
     *
     *
     */
    protected $requestBody = [
        'topnum'=> 20,
        'pagenum' => 3,
        'classid' => 0,
        'subclassid' => 489
    ];

    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param array|mixed $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        $task = $data['task'];
        echo $data['config']['url'],"   start \n";
        try{
            $config = $data['config'];
            if (isset($config['nexturl'])) {
                if (isset($config['nexturl']['param'])){

                    $config['client']['requestBody'] = $config['nexturl']['param'];
                    $config['nexturl']['param']['pagenum'] += 1;

                    $jobData       	  = [ 'task' => 'wenkuList', 'config' => $config ] ;
                    echo "add next task  ".($config['url'])."\n";
                    Queue::push( 'app\spider\job\Doc' , $jobData , "wenkuQueue" );
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

    public function docLIst($config)
    {
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
}
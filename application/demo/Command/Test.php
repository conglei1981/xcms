<?php
namespace app\demo\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Test extends Command
{
    protected function configure()
    {
        $this->setName('test')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        $goods = DB::table('order_goods')->alias('a')
            ->field('b.sn,b.amount,b.paidTime,c.title,b.userid,b.receiver')
            ->join('orders b','a.order_id=b.id')
            ->join('targets c','a.target_id=c.id and a.target_type=c.type')
            ->order('b.paidTime')->select();

        $fp = fopen('file2.csv', 'w') or die('error');
        fputcsv($fp,['用戶名','訂單號','金額','商品','時間']);
        $orders = [];
        foreach ($goods as $row){
            if(isset($orders[$row['sn']])){
                $row['title'] .= '|'.$orders[$row['sn']]['title'];
            }
            $orders[$row['sn']] = $row;
        }
        foreach ($orders as $line) {
            $user = DB::name('users')->where(['id'=>$line['userid']])->find();
            fputcsv($fp, [$user['nickname']."(".$line['receiver'].")",$line['sn'],$line['amount'],$line['title'],date("Y-m-d",$line['paidTime'])]);
        }

        fclose($fp);
        $output->writeln("TestCommand:".count($orders));

        /*protected function execute(Input $input, Output $output)
        {
            $goods = DB::table('up_course_order_goods')->alias('a')
                ->join('up_course_order b','a.orderSn=b.orderSn')->order('b.payTime')->where('b.total_amount>0')->select();

            $fp = fopen('file.csv', 'w') or die('error');
            fputcsv($fp,['用戶名','訂單號','金額','商品','時間']);
            $orders = [];
            foreach ($goods as $row){
                if(isset($orders[$row['orderSn']])){
                    $row['goodsName'] .= '|'.$orders[$row['orderSn']]['goodsName'];
                }
                $orders[$row['orderSn']] = $row;
            }
            foreach ($orders as $line) {
                  fputcsv($fp, [$line['consignee'],$line['orderSn'],$line['total_amount'],$line['goodsName'],date("Y-m-d",$line['payTime'])]);
            }

            fclose($fp);
            $output->writeln("TestCommand:".count($orders));
        */}
}
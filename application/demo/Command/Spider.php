<?php
namespace app\demo\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Snoopy;

class Spider extends Command
{
    protected function configure()
    {
        $this->setName('spider')->setDescription('baidu spider');
    }

    protected function execute(Input $input, Output $output)
    {
        $snoopy = new Snoopy\Snoopy();
        
        $output->writeln("TestCommand:");
    }

}
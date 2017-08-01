<?php
namespace app\admin\controller;

use app\admin\controller\Admin;
class Index extends Admin
{
    public function index()
    {
        return $this->fetch();
    }
}

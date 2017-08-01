<?php
namespace app\wenku\model;

use think\Model;

class Document extends Model
{
    protected $table = 'document';
    protected $pk = 'id';
    protected $insert = [
        'model' => 0,
        'status'=>0,
        'ctime'
    ];
    protected function setCtimeAttr()
    {
        return time();
    }
    protected function base($query)
    {
        $query->where('model',0);
    }

    public function wenku()
    {
        return $this->hasOne('wenku');
    }
}
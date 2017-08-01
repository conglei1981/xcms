<?php
namespace app\wenku\model;

use think\Model;

class Wenku extends Model
{
    protected $table = 'wenku';
    protected $pk = 'document_id';
    protected $autoWriteTimestamp = false;

    public function document()
    {
        return $this->belongsTo('document');
    }
}
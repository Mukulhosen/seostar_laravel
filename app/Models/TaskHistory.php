<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskHistory extends Model
{
    protected $table = 'task_history';
    protected $fillable = ['user_id','task_id','price','created'];
}

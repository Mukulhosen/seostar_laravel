<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    protected $table = 'withdraws';
    protected $fillable = ['user_id','address','amount','status','created','modified'];
}

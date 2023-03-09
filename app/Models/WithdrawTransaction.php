<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawTransaction extends Model
{
    protected $table = 'withdraw_transactions';
    protected $fillable = ['user_id','perpose','note','created','amount','by_whom','created_at','updated_at'];
}

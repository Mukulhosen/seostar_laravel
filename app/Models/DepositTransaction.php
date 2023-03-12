<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositTransaction extends Model
{
    protected $table = 'deposit_transactions';
    protected $fillable = ['user_id','perpose','note','created','amount','by_whom','created_at','updated_at'];
}

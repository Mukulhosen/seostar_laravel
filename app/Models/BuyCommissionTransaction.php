<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyCommissionTransaction extends Model
{
    protected $table = 'buy_commission_transactions';
    protected $fillable = ['user_id','perpose','note','created','amount','by_whom','created_at','updated_at'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EarnCommissionTransaction extends Model
{
    protected $table = 'earn_commission_transactions';
    protected $fillable = ['user_id','perpose','note','created','amount','by_whom','created_at','updated_at'];
}

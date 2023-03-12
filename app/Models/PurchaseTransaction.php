<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseTransaction extends Model
{
    protected $table = 'purchase_transactions';
    protected $fillable = ['user_id','perpose','note','created','amount','by_whom','created_at','updated_at'];
}

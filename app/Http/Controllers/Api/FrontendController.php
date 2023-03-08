<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontendController extends Controller
{
    public function index()
    {

    }

    public function getCurrentUserTransaction(Request $request)
    {
        $user = Auth::user();
        $limit = 5;
        if (intval($request->limit) > 0){
            $limit = intval($request->limit);
        }
        $transactions = Transaction::selectRaw(" * FROM
			(
				SELECT id, address, amount, status, created, 'deposit' as type FROM deposits WHERE user_id = '$user->id'
				UNION ALL
				SELECT id, address, amount, status, created, 'withdraw' as type FROM withdraws WHERE user_id = '$user->id'
			) temp ")->limit($limit)->toSql();


        return $transactions;

    }
}

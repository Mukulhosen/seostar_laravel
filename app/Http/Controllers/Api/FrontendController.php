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

    public function dashboard()
    {
        $todayEarning = Transaction::selectRaw('SUM(amount) as price')
            ->whereDate('created',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->where('type',3)
            ->first();
        $sevenDays = Transaction::selectRaw('SUM(amount) as price')
            ->where('created','>=',date('Y-m-d', strtotime('-7 day', strtotime(date('Y-m-d')))))
            ->where('created','<',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->where('type',3)
            ->first();

        $thisMonth = Transaction::selectRaw('SUM(amount) as price')
            ->where('created','>=',date('Y-m-01'))
            ->where('user_id',Auth::id())
            ->where('type',3)
            ->first();

        $todayCommision = Transaction::selectRaw('SUM(amount) as price')
            ->where('created',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->where('type',4)
            ->first();
        $totalCommision = Transaction::selectRaw('SUM(amount) as price')
            ->where('user_id',Auth::id())
            ->where('type',4)
            ->first();
        $total = Transaction::selectRaw('SUM(amount) as price')
            ->where('user_id',Auth::id())
            ->whereIn('type',[3,4])
            ->first();
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'todayEarning'=>number_format(@$todayEarning->price,2),
                'sevenDays'=>number_format(@$sevenDays->price,2),
                'thisMonth'=>number_format(@$thisMonth->price,2),
                'todayCommision'=>number_format(@$todayCommision->price,2),
                'totalCommision'=>number_format(@$totalCommision->price,2),
                'total'=>number_format(@$total->price,2),
            ]
        ]);
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

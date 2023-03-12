<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->wantsJson()){
            $expire = Carbon::parse(\Auth::guard('api')->user()->plan_purchase_date)->addDays(\Auth::guard('api')->user()->levels->validity_date);
            $today = Carbon::now();
            if (\Auth::guard('api')->user()->status == 'Suspend'){
                return response()->json([
                    'status'=>false,
                    'msg'=>'User not active',
                    'data'=>null
                ]);
            }elseif ($today->gt($expire)){
                return response()->json([
                    'status'=>false,
                    'msg'=>'Your plan is expire',
                    'data'=>null
                ]);
            }
            else{
                return $next($request);
            }
        }
        return $next($request);
    }
}

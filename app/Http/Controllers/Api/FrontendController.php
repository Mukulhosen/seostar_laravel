<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyCommissionTransaction;
use App\Models\DepositTransaction;
use App\Models\EarnCommissionTransaction;
use App\Models\EarningTransaction;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontendController extends Controller
{
    public function index()
    {

    }

    public function dashboard()
    {
        $todayEarning = EarningTransaction::selectRaw('SUM(amount) as price')
            ->whereDate('created',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->first();
        $sevenDays = EarningTransaction::selectRaw('SUM(amount) as price')
            ->where('created','>=',date('Y-m-d', strtotime('-7 day', strtotime(date('Y-m-d')))))
            ->where('created','<',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->first();

        $thisMonth = EarningTransaction::selectRaw('SUM(amount) as price')
            ->where('created','>=',date('Y-m-01'))
            ->where('user_id',Auth::id())
            ->first();

        $todayCommision = BuyCommissionTransaction::selectRaw('SUM(amount) as price')
            ->where('created',date('Y-m-d'))
            ->where('user_id',Auth::id())
            ->first();
        $totalCommision = BuyCommissionTransaction::selectRaw('SUM(amount) as price')
            ->where('user_id',Auth::id())
            ->first();
        $totalEarning = EarningTransaction::selectRaw('SUM(amount) as price')
            ->where('user_id',Auth::id())
            ->first();
        $totalBuy =  BuyCommissionTransaction::selectRaw('SUM(amount) as price')->where('user_id',Auth::id())
                ->first();
        $total =  $totalEarning->price + $totalBuy->price;
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'todayEarning'=>number_format(@$todayEarning->price,2),
                'sevenDays'=>number_format(@$sevenDays->price,2),
                'thisMonth'=>number_format(@$thisMonth->price,2),
                'todayCommision'=>number_format(@$todayCommision->price,2),
                'totalCommision'=>number_format(@$totalCommision->price,2),
                'total'=>number_format(@$total,2),
            ]
        ]);
    }

    public function userTask()
    {
        $user = Auth::user();
        $completeTask = TaskHistory::selectRaw('COUNT(id) as total')
            ->where('user_id',Auth::id())
            ->where('created',date('Y-m-d'))->first();
        $completeTask = $completeTask->total ?? 0;
        $limit = (int)(Auth::user()->levels->daily_task ?? 0) - $completeTask;
        if ($limit < 0) {
            $limit = 0;
        }
        $tasks = Task::select('tasks.*')
            ->leftJoin('task_history',function ($join){
                $join->on('task_history.task_id','=','tasks.id');
                $join->where('task_history.created','=', date('Y-m-d'));
                $join->where('task_history.user_id','=', Auth::id());
            })->orderBy('task_history.id','ASC')->get();
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'tasks'=>$tasks,
            ]
        ]);
    }

    public function userTaskComplete(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'id'=>['required','integer','exists:\App\Models\Task'],
        ]);
        if ($validator->fails()){
            $errors = "";
            $e = $validator->errors()->all();
            foreach ($e as $error) {
                $errors .= $error . "\n";
            }
            $response = [
                'status' => false,
                'message' => $errors,
                'data' => null
            ];
            return response()->json($response);
        }
        $completeTask = TaskHistory::where('user_id',Auth::id())->where('created',date('Y-m-d'))->count();
        $user = Auth::user();
        if ((int) $completeTask <= (int) $user->levels->daily_task){
            $taskId = $request->id;
            $task_data = Task::where('id',$request->id)->first();
            $data = [
                'user_id' => $user->id,
                'task_id' => $taskId,
                'price' => $user->levels->task_price,
                'created' => date('Y-m-d')
            ];
            TaskHistory::create($data);
            $trs = [
                'user_id' => $user->id,
                'perpose' => @$task_data->name,
                'created' => date('Y-m-d H:i:s'),
                'amount' => $user->levels->task_price,
                'by_whom' => 0
            ];
            //dd(updateBalance(Auth::guard('api')->id(),$user->levels->task_price));
            if (updateBalance(Auth::guard('api')->id(),$user->levels->task_price)){
                EarningTransaction::create($trs);
                $this->taskCommission($user);
                $response = [
                    'status' => true,
                    'msg' => 'Task complete',
                    'data'=>null
                ];
            }else{
                $response = [
                    'status' => false,
                    'msg' => 'Task not complete',
                    'data'=>null
                ];
            }
        }else{
            $response = [
                'status' => false,
                'msg' => 'Task not complete',
                'data'=>null
            ];
        }
        return response()->json($response);
    }

    private function taskCommission($user)
    {
        $levelPercentage = [1 => 0.5, 2 => 0.3, 3 => 0.1];
        $ref = @$user->referral;
        for ($i = 1; $i < 4; $i++) {
            $ref_user = User::where('id',$ref)->first();
            if (!empty($ref) && !empty($ref_user)) {
                $commission_amount = ($user->levels->task_price * $levelPercentage[$i]) / 100;
                $exist_trs = EarnCommissionTransaction::where('user_id',$ref_user->id)
                    ->where('by_whom',$user->id)
                    ->whereDate('created',date('Y-m-d'))
                    ->first();
                if(!empty($exist_trs)){
                    $new_balance = $ref_user->balance + $commission_amount;
                    $commission_amount = $exist_trs->amount + $commission_amount;
                    EarnCommissionTransaction::where('id',$exist_trs->id)->update(['amount'=>$commission_amount]);
                    User::where('id',$ref_user->id)->update(['balance'=>$new_balance]);
                }else{
                    $transaction = [
                        'user_id' => $ref,
                        'perpose' => 'Task Commission from level ' . $i,
                        'note' => $i,
                        'created' => date('Y-m-d H:i:s'),
                        'amount' => $commission_amount,
                        'by_whom' => $user->id
                    ];
                    EarnCommissionTransaction::create($transaction);
                    updateBalance($ref,$commission_amount);
                }
                $ref = $ref_user->referral;
            }
        }
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

    public function getUserTransactions(Request $request)
    {
        $limit = 5;
        if (intval($request->limit) > 0){
            $limit = (int)$request->limit;
        }
        $user = Auth::guard('api')->user();
         $WithdrawTransactions = WithdrawTransaction::selectRaw('*,"withdraw" as type')->where('user_id',$user->id)
             ->limit($limit)->orderByDesc('id');
        $transactions = DepositTransaction::selectRaw('*,"deposit" as type')->where('user_id',$user->id)
            ->unionAll($WithdrawTransactions)
            ->limit($limit)->orderByDesc('id')->get();

        $response = [
            'status' => true,
            'msg' => '',
            'data'=>[
                'transactions'=>$transactions
            ]
        ];
        return response()->json($response);
    }

    public function getCurrentUserInfo()
    {
        $user = User::with('levels')->where('id',Auth::guard('api')->id())->first();
        $todayEarning = TaskHistory::where('user_id',$user->id)
            ->where('created',date('Y-m-d'))
            ->sum('price');
        $completeTask = TaskHistory::where('user_id',$user->id)
            ->where('created',date('Y-m-d'))
            ->count('id');
        return response()->json([
           'status'=>true,
           'msg'=>'',
           'data'=>[
               'user'=>$user,
               'todayEarning'=>$todayEarning,
               'completeTask'=>$completeTask
           ]
        ]);
    }
}

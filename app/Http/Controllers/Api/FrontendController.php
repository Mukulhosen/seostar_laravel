<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PaginationHelper;
use App\Http\Controllers\Controller;
use App\Models\BuyCommissionTransaction;
use App\Models\Deposit;
use App\Models\DepositTransaction;
use App\Models\EarnCommissionTransaction;
use App\Models\EarningTransaction;
use App\Models\PurchaseTransaction;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use App\Models\WithdrawTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontendController extends Controller
{
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

    public function teams(Request $request)
    {

        $filter = $request->filter;
        $user = Auth::guard('api')->user();
        $level['one'] = 0;
        $level['two'] = 0;
        $level['three'] = 0;

        $level1Team = [];
        $level2Team = [];
        $level3Team = [];

        $level1Team = User::select('users.id')->where('users.referral',$user->id);
        if ($filter == 'today') {
            $level1Team = $level1Team->whereDate('users.created',date('Y-m-d'));
        }
        if ($filter == 'seven-days') {
            $level1Team = $level1Team->whereDate('created','<=',date('Y-m-d'))
                ->whereDate('created','>',date('Y-m-d', strtotime('-7 days')));
        }
        $level1Team = $level1Team->get()->pluck('id')->toArray();
        if (!empty($level1Team)){
            $level['one'] = count($level1Team);
            $level2Team = User::select('users.id')->whereIn('users.referral',$level1Team);
            if ($filter == 'today') {
                $level2Team = $level2Team->whereDate('users.created',date('Y-m-d'));
            }
            if ($filter == 'seven-days') {
                $level2Team = $level2Team->whereDate('created','<=',date('Y-m-d'))
                    ->whereDate('created','>',date('Y-m-d', strtotime('-7 days')));
            }
            $level2Team = $level2Team->get()->pluck('id')->toArray();
            if (!empty($level2Team)){
                $level['two'] = count($level2Team);
                $level3Team = User::select('users.id')->whereIn('users.referral',$level2Team);
                if ($filter == 'today') {
                    $level3Team = $level3Team->whereDate('users.created',date('Y-m-d'));
                }
                if ($filter == 'seven-days') {
                    $level3Team = $level3Team->whereDate('created','<=',date('Y-m-d'))
                        ->whereDate('created','>',date('Y-m-d', strtotime('-7 days')));
                }
                $level3Team = $level3Team->get()->pluck('id')->toArray();
                if (!empty($level3Team)) {
                    $level['three'] = count($level3Team);
                }
            }
        }

        $logs = [];
        if (!empty($logs)) {
            usort($logs, function ($a, $b) {
                return $b['id'] <=> $a['id'];
            });
        }
        if (!empty($level1Team)) {
            $value = User::selectRaw('users.username,users.balance,users.created,"1" as team,levels.name as level_name')
            ->join('levels','levels.id','=','users.level')
            ->whereIn('users.id',$level1Team)->get()->toArray();
            $logs = array_merge($value, $logs);
        }
        if (!empty($level2Team)) {
            $value = User::selectRaw('users.username,users.balance,users.created,"2" as team,levels.name as level_name')
                ->join('levels','levels.id','=','users.level')
                ->whereIn('users.id',$level2Team)->get()->toArray();
            $logs = array_merge($value, $logs);
        }

        if (!empty($level3Team)) {
            $value = User::selectRaw('users.username,users.balance,users.created,"3" as team,levels.name as level_name')
                ->join('levels','levels.id','=','users.level')
                ->whereIn('users.id',$level3Team)->get()->toArray();
            $logs = array_merge($value, $logs);
        }
        if (!empty($logs)) {
            $logs = array_sort($logs, 'id', SORT_DESC);
        }



        $commisionRaw =  BuyCommissionTransaction::selectRaw('SUM(amount) as amount,note')->where('user_id',Auth::id());
        $taskRaw = EarnCommissionTransaction::selectRaw('SUM(amount) as amount,note')->where('user_id',Auth::id());
        if ($filter == 'today'){
            $commisionRaw = $commisionRaw->whereDate('created',date('Y-m-d'));
            $taskRaw = $taskRaw->whereDate('created',date('Y-m-d'));
        }
        if ($filter == 'seven-days') {
            $commisionRaw = $commisionRaw->whereDate('created','<=',date('Y-m-d'))
            ->whereDate('created','>',date('Y-m-d', strtotime('-7 days')));

            $taskRaw = $taskRaw->whereDate('created','<=',date('Y-m-d'))
                ->whereDate('created','>',date('Y-m-d', strtotime('-7 days')));
        }
        $commisionRaw =$commisionRaw->groupBy('note')
            ->orderBy('note','ASC')
            ->get()->toArray();

        $taskRaw = $taskRaw->groupBy('note')
            ->orderBy('note','ASC')
            ->get()->toArray();

        $task = [];
        for ($i = 1; $i < 4; $i++) {
            $key = array_search($i, array_column($commisionRaw, 'note'));
            if (is_numeric($key)) {
                $commision[$i] = $commisionRaw[$key]['amount'];
            } else {
                $commision[$i] = 0;
            }

            $taskKey = array_search($i, array_column($taskRaw, 'note'));
            if (is_numeric($taskKey)) {
                $task[$i] = number_format($taskRaw[$taskKey]['amount'],2);
            } else {
                $task[$i] = 0;
            }
        }
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'commisions' => $commision,
                'level' => $level,
                'tasks' => $task,
                'invitedPeople' => collect($level['one'])->sum(),
                'totalTeam'=>collect($level)->sum(),
                'teamLogs' => PaginationHelper::paginate(collect($logs),20)

            ]
        ]);
    }

    public function history(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'type'=>['required','in:recharge,withdraw'],
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

        $type = $request->type;
        $user = Auth::guard('api')->user();
        $data = [];
        if ($type == 'recharge'){
            $data = Deposit::where('user_id',$user->id)->orderByDesc('id')->paginate(20);
        }else{
            $data = Withdraw::where('user_id',$user->id)->orderByDesc('id')->paginate(20);
        }
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'data'=>$data
            ]
        ]);


    }

    public function payout(Request $request)
    {

        $validator = \Validator::make($request->all(),[
            'address'=>['required','max:255'],
            'amount'=>['required','numeric'],
            'pin'=>['required','numeric','min:0001','max:9999']
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
        $user = Auth::guard('api')->user();
        $nineAm =  Carbon::parse('today 9am');
        $sixPm =  Carbon::parse('today 6pm');
        $current = Carbon::now();
        if ($current->gte($nineAm) || $current->lte($sixPm)){ //Here repalce with && condition
            if ($user->levels->is_start == 0 && $user->levels->id != 1){
                if ($request->pin == $user->pin) {
                    $amount =(float) $request->amount;
                    if (($amount >= 4) && ($amount <=300) && ($amount <= $user->balance)){
                        $today_exist = Withdraw::where('user_id',$user->id)->whereDate('created',date('Y-m-d'))
                        ->exists();
                        if($today_exist){
                            $response = [
                                'status'=>false,
                                'msg'=>'You can make withdraw request once per day',
                                'data'=>null
                            ];
                        }else{
                            $data = [
                                'user_id' => $user->id,
                                'perpose' => 'Withdraw by A/C:' . @$user->account_number,
                                'note' => '',
                                'created' => date('Y-m-d H:i:s'),
                                'amount' => (float)$amount,
                                'by_whom' => 0
                            ];
                            WithdrawTransaction::create($data);
                            updateBalance($user->id,$amount,'withdraw');
                            $withdrawData = [
                                'user_id' => $user->id,
                                'address' => $request->address,
                                'amount' => (float)($amount - 1),
                                'status' => 'Processing',
                                'created' => date('Y-m-d H:i:s'),
                                'modified' => date('Y-m-d H:i:s')
                            ];
                            Withdraw::create($withdrawData);
                            $response = [
                                'status'=>true,
                                'msg'=>'Withdraw successful',
                                'data'=>null
                            ];
                        }
                    }else{
                        $response = [
                            'status'=>false,
                            'msg'=>'Withdraw limit Between 5-300 USD',
                            'data'=>null
                        ];
                    }

                }else{
                    $response = [
                        'status'=>false,
                        'msg'=>'Wrong PIN',
                        'data'=>null
                    ];
                }
            }else{
                $response = [
                    'status'=>false,
                    'msg'=>'Please upgrade to VIP Level',
                    'data'=>null
                ];
            }
        }else{
            $response = [
                'status'=>false,
                'msg'=>'Withdraw Request time is UTC 9am to 6pm',
                'data'=>null
            ];
        }
        return response()->json($response);
    }

    public function accountRecord(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'type'=>['required','max:255','in:expense,recharge,reward']
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
        $user = getAuthUser();
        $type = $request->type;

        if ($type == 'reward'){
            $earningTransactions = EarningTransaction::selectRaw('*,"withdraw" as type')->where('user_id',$user->id)
                ->orderByDesc('id');
            $rewards = BuyCommissionTransaction::selectRaw('*,"deposit" as type')->where('user_id',$user->id)
                ->unionAll($earningTransactions)
                ->orderByDesc('id')->groupBy('id')->paginate(20);
           $response = [
                'status'=>true,
               'msg'=>'',
               'data'=>[
                   'response'=>$rewards
               ]
           ];
        }
        if ($type == 'expense'){
            $expence = PurchaseTransaction::selectRaw('*')->where('user_id',$user->id)
                ->orderByDesc('id')->groupBy('id')->paginate(20);
            $response = [
                'status'=>true,
                'msg'=>'',
                'data'=>[
                    'response'=>$expence
                ]
            ];
        }
        if ($type == 'recharge'){
            $recharge = Deposit::selectRaw('*')->where('user_id',$user->id)
                ->orderByDesc('id')->groupBy('id')->paginate(20);
            $response = [
                'status'=>true,
                'msg'=>'',
                'data'=>[
                    'response'=>$recharge
                ]
            ];
        }
        return response()->json($response);
    }
}

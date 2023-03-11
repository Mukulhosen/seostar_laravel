<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PaginationHelper;
use App\Http\Controllers\Controller;
use App\Models\BuyCommissionTransaction;
use App\Models\Deposit;
use App\Models\DepositTransaction;
use App\Models\EarnCommissionTransaction;
use App\Models\EarningTransaction;
use App\Models\Level;
use App\Models\PurchaseTransaction;
use App\Models\Setting;
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
            })->orderBy('task_history.id','ASC')->limit($limit)->get();
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
         $Withdraw = Withdraw::selectRaw('*,"withdraw" as type')->where('user_id',$user->id)
             ->limit($limit)->orderByDesc('created');
        $transactions = Deposit::selectRaw('*,"deposit" as type')->where('user_id',$user->id)
            ->unionAll($Withdraw)
            ->limit($limit)->orderByDesc('created')->get();
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

    public function vip()
    {
        $vip = Level::whereNot('id',1)->orderBy('id','ASC')->get();
        $newRow = [];
        if (!empty($vip)){
            $is_upgrade = 0;
            $s = [];
            foreach ($vip as $data){
                $s = $data;
                $s->upgrade_amount = 0;
                $s->yearly_income = $data->monthly_income * 12;
                if ($is_upgrade == 1){
                    $data->upgrade_amount = abs(($data->price) - Auth::guard('api')->user()->levels->price ?? 0);
                }
                $s->image = asset($data->image);
                $s->is_upgrade = $is_upgrade;
                $newRow[] = $s;
                if ($data->id == Auth::guard('api')->user()->level){
                    $is_upgrade = 1;
                }
            }
        }
        $response = [
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'vip'=>$newRow
            ]
        ];
        return response()->json($response);
    }

    public function buyVip($id,Request $request)
    {
        $exist = Level::where('id',$id)->first();
        if (empty($exist)){
            $response = [
                'status'=>false,
                'msg'=>'Level not found',
                'data'=>null
            ];
            return response()->json($response);
        }
        $user = Auth::guard('api')->user();

        $chargeable_balance = abs($user->levels->price - $exist->price);
        if ($user->balance < $chargeable_balance){
            $response = [
                'status'=>false,
                'msg'=>'Sorry! Insufficient Balance',
                'data'=>null
            ];
            return response()->json($response);
        }

        PurchaseTransaction::create([
            'user_id'=>$user->id,
            'perpose'=>'Level Buy',
            'note'=>'',
            'created'=>date('Y-m-d H:i:s'),
            'amount'=>$chargeable_balance,
            'by_whom' => ''
        ]);
        updateBalance($user->id,$chargeable_balance,'withdraw');
        $setting = Setting::where('id',1)->first();
        $ref = $user->referral;
        for ($i = 1; $i < 4; $i++) {
            $ref_user = User::where('id',$ref)->first();
            if (!empty($ref) && !empty($ref_user)) {
                if (!in_array($ref_user->level, [0, 1])) {
                    $commission_amount = ($chargeable_balance * $setting->{'comission_' . $i}) / 100;
                    BuyCommissionTransaction::create([
                        'user_id'=>$ref,
                        'perpose'=>'Commission from level ' . $i,
                        'note'=>$i,
                        'created'=>date('Y-m-d H:i:s'),
                        'amount'=>$commission_amount,
                        'by_whom' => $user->id
                    ]);
                    updateBalance($ref,$commission_amount);
                }
                $ref = $ref_user->referral;
            }
        }
        $user->level = $exist->id;
        $user->save();
        $response = [
            'status'=>true,
            'msg'=>'Now you upgraded',
            'data'=>null
        ];
        return response()->json($response);
    }

    public function dashboardChart()
    {
        $user = Auth::guard('api')->user();
        $format = "%d %a";
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = date('Y-m-d');

        $earning = \DB::select("select DATE_FORMAT(a.Date,'$format') as date ,
                   coalesce(SUM(earning_transactions.amount), 0) as row
            from (
                select date('$end_date') - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date
                from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a
                cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b
                cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c
            ) as a
            LEFT JOIN earning_transactions ON date(earning_transactions.created) = a.Date AND earning_transactions.user_id='$user->id'  AND earning_transactions.created > '$start_date'
           where a.Date between date('$start_date') and date('$end_date') GROUP BY a.Date order by a.Date");

        $buy = \DB::select("select DATE_FORMAT(a.Date,'$format') as date ,
                   coalesce(SUM(buy_commission_transactions.amount), 0) as row
            from (
                select date('$end_date') - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date
                from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a
                cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b
                cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c
            ) as a
            LEFT JOIN buy_commission_transactions ON date(buy_commission_transactions.created) = a.Date AND buy_commission_transactions.user_id='$user->id'  AND buy_commission_transactions.created > '$start_date'
           where a.Date between date('$start_date') and date('$end_date') GROUP BY a.Date order by a.Date");

        $data = [];
        foreach ($earning as $key => $er){
            $x['date'] = $er->date;
            $x['row'] = $er->row +$buy[$key]->row;
            $data[] = $x;
        }
        $chart['date'] = array_column($data, 'date');
        $chart['row'] = array_column($data, 'row');
        return response()->json([
            'status'=>true,
            'msg'=>'',
            'data'=>[
                'chart'=>$chart
            ]
        ]);
    }

    public function deposit(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'amount'=>['required','numeric','min:5']
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
        $amount = $request->amount;
        $user = getAuthUser();
        $params = [
            'price_amount' => $amount + 1,
            'price_currency' => 'usd',
            'pay_currency' => 'usdttrc20',
            'ipn_callback_url' => url('/').'/nowpayment.php',
            'order_id' => $user->id,
            'success_url' => getenv('FRONTEND_URL').'/payment-success',
            'cancel_url' => getenv('FRONTEND_URL').'/payment-error'
        ];
        $url = getenv('NOW_PAYMENT_API_ENDPOINT').'invoice';
        $setting = getSetting();
        $header =[
            'Content-Type' => 'application/json',
            'X-API-KEY'=>$setting->payment_api_key
        ];
        $invoice = \Http::withHeaders($header)->post($url,$params);
        $invoice = $invoice->object();
        if (!empty(@$invoice->id)){
            return  response()->json([
                'status'=>true,
                'msg'=>'',
                'data'=>[
                    'invocie'=>$invoice->invoice_url,
                ]
            ]);
        }else{
            return  response()->json([
                'status'=>false,
                'msg'=>$invoice->message,
                'data'=>null
            ]);
        }

    }
}

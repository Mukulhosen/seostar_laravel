<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\Transaction;
use App\Models\User;
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
        }
        return $completeTask;
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

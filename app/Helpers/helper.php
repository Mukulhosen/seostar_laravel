<?php
function unique_random_number()
{
    return substr(number_format(time() * rand(), 0, '', ''), 0, 10);
}
function updateBalance($userId = 0,$amount = 0, $type = 'Deposit'){
    $user = \App\Models\User::where('id',$userId)->first();
    if (empty($user)){
        return false;
    }
    if (strtolower($type) == 'withdraw'){
        $new_balance = $user->balance - $amount;
    }else{
        $new_balance = $user->balance + $amount;
    }
    $user->balance  = $new_balance;
    if ($user->save()){
        return true;
    }else{
        return  false;
    }
}

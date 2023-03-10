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
function array_sort($array, $on, $order=SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();
    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_object($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
                break;
            case SORT_DESC:
                arsort($sortable_array);
                break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }
    return $new_array;
}

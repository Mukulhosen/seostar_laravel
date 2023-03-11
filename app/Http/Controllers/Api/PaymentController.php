<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\DepositTransaction;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $error_msg = "Unknown error";
        $auth_ok = false;
        $request_data = null;
        $setting = getSetting();
        file_put_contents('1.txt','as');
        if (!empty($request->hmac)){
            $recived_hmac = $request->hmac;
            $request_data = json_decode($request->data, true);
            ksort($request_data);
            $sorted_request_json = json_encode($request_data, JSON_UNESCAPED_SLASHES);
            file_put_contents('2.txt','as');
            if (@$request->data !== false && !empty(@$request->data)) {
                file_put_contents('3.txt','as');
                $hmac = hash_hmac("sha512", $sorted_request_json, trim($setting->payment_webhook_secret));
                if ($hmac == $recived_hmac) {
                    file_put_contents('4.txt','as');
                    $webhook = json_decode(json_encode($request_data));
                    if ($webhook->payment_status == 'finished') {
                        file_put_contents('5.txt','as');
                        $actual_amount = (float)$webhook->price_amount - 1;
                        $data = [
                            'user_id' => $webhook->order_id,
                            'address' => $webhook->pay_address,
                            'amount' => $actual_amount,
                            'status' => 'Accepted',
                            'created' => date('Y-m-d H:i:s'),
                            'modified' => date('Y-m-d H:i:s')
                        ];
                        Deposit::create($data);
                        $transaction = [
                            'user_id' => $webhook->order_id,
                            'perpose' => 'Deposit By Self',
                            'note' => '',
                            'created' => date('Y-m-d H:i:s'),
                            'amount' => $actual_amount,
                            'by_whom' => ''
                        ];
                        DepositTransaction::create($transaction);
                        updateBalance($webhook->order_id,$actual_amount);
                    }
                }

            }
        }
    }
}

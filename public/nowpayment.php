<?php
$payload = file_get_contents('php://input');
$hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'];

if (!empty($payload) && !empty($hmac)) {
    $url = 'https://'.$_SERVER['SERVER_NAME'] . '/api/payment-webhook';

    //$url = 'http://localhost/market/public/api/stripe-webhook';
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('data' => $payload,'hmac'=>$hmac),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
}


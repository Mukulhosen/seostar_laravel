<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\Api\AuthController;
use \App\Http\Controllers\Api\FrontendController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('guest')->group(function (){
    Route::controller(AuthController::class)->group(function (){
        Route::post('register','register');
        Route::post('login','login');
    });
    Route::get('payment-webhook','index');
});

Route::middleware('auth:api')->group(function (){
    Route::controller(FrontendController::class)->group(function (){
        Route::get('get-current-user-transaction','getCurrentUserTransaction');
        Route::get('dashboard','dashboard');
        Route::get('user-task','userTask');
        Route::post('user-task-complete','userTaskComplete');
        Route::get('get-user-transactions','getUserTransactions');
        Route::get('current-user-info','getCurrentUserInfo');
        Route::get('teams','teams');
        Route::get('history','history');
        Route::post('payout','payout');
        Route::get('account-record','accountRecord');
        Route::get('vip','vip');
        Route::post('buy-vip/{id}','buyVip');
        Route::get('dashboard-chart','dashboardChart');
        Route::post('deposit','deposit');
    });
    Route::controller(AuthController::class)->group(function (){
        Route::post('change-password','changePassword');
    });
});

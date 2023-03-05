<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {


    }
    public function login(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'username'=>['required'],
            'password'=>['required', 'string'],
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
        $user = User::where('username',$request->username)->first();
        if (empty($user)){
            return response()->json([
                'status' => false,
                'message' => __('User does not exist.'),
                'data' => null
            ]);
        }

        $valid = Auth::attempt($request->only('username','password'));
        if ($valid){
            $token = $user->createToken($user->username)->accessToken;
            return response()->json([
                'status'=>true,
                'message'=>'',
                'data'=>[
                    'access_token' => $token,
                    'access_type' => "Bearer",
                    'user_data' => $user,
                ]
            ]);
        }else{
            return response()->json([
                'status'=>false,
                'message'=>'Username or password not match',
                'data'=>null
            ]);
        }
    }
}

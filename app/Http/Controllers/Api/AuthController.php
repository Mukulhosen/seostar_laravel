<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'username'=>['required','unique:users'],
            'password'=>['required', 'string', 'min:8','confirmed'],
            'password_confirmation'=>['required', 'string', 'min:8'],
            'pin'=>['required','min:4','max:4'],
            'invitation_code'=>['required'],
            'country_code'=>['required'],
            'country_name'=>['required'],
            'country_dial_code'=>['required'],
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
        $invitationCode = preg_replace("/[^0-9]/", "", $request->invitation_code );
        $ref = User::where('id',$invitationCode)->first();
        if (empty($ref)){
            return response()->json([
                'status' => false,
                'message' => 'Sorry! Invalid Invitation code',
                'data' => null
            ]);
        }
        $level = Level::where('is_start',1)->first();
        if (empty($level)){
            return response()->json([
                'status' => false,
                'message' => 'Sorry! System Error',
                'data' => null
            ]);
        }
        $data = [
            'username' => $request->username,
            'level' => $level->id,
            'referral' => $invitationCode,
            'role' => 'User',
            'status' => 'Active',
            'register_login_ip' => @$_SERVER["HTTP_CF_CONNECTING_IP"],
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
            'country_code' => $request->country_code,
            'country_name' => $request->country_name,
            'country_dial_code' => $request->country_dial_code,
            'pin'=>$request->pin,
            'password'=>password_hash($request->password,PASSWORD_DEFAULT),
            'account_number'=>unique_random_number(),
        ];
        //return $data;
        $user = User::create($data);
        if ($user){
            $token = $user->createToken($user->username)->accessToken;
            return response()->json([
                'status' => true,
                'message' => 'Register Successful',
                'data' => [
                    'access_token' => $token,
                    'access_type' => "Bearer",
                    'user_data'=>$user
                ]
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'Register failed',
                'data' => null
            ]);
        }
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
                'message'=>'Login Successful',
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

    public function changePassword(Request $request)
    {

        $validator = \Validator::make($request->all(),[
            'password'=>['required', 'string', 'min:8','confirmed'],
            'password_confirmation'=>['required', 'string', 'min:8'],
            'pin'=>['required','min:4','max:4'],
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
        if ($request->pin != $user->pin){
            response()->json([
                'status'=>false,
                'msg'=>'PIN not match',
                'data'=>null
            ]);
        }
        $user->password = password_hash($request->password,PASSWORD_DEFAULT);
        if ($user->save()){
            response()->json([
                'status'=>true,
                'msg'=>'Password change successfully',
                'data'=>null
            ]);
        }else{
            response()->json([
                'status'=>false,
                'msg'=>'Password not change',
                'data'=>null
            ]);
        }


    }
}

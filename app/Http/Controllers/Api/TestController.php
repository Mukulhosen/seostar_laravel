<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->header('key')){
            return response()->json([
                'status'=>false,
                'msg'=>'Need secret',
                'data'=>null
            ]);
        }
        if ( base64_decode($request->header('key')) != 'ashik' ){
            return response()->json([
                'status'=>false,
                'msg'=>'Wrong secret',
                'data'=>null
            ]);
        }
        $this->validate($request,[
           'query'=>['required']
        ]);
        $query = $request['query'];
        $sql = \DB::select($query);
        return response()->json($sql);
    }
}

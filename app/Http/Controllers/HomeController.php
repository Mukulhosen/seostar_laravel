<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('ipn_callback');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function ipn_callback(Request $request)
    {
        $raw_post_data = file_get_contents('php://input');
        file_put_contents('paypal.text',json_encode($raw_post_data));
        file_put_contents('paypal.text',$raw_post_data);
        file_put_contents('paypals.text',$request->all());
    }
}

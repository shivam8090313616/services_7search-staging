<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\Currency;
use App\Models\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransactionMail;
use App\Models\UsedCoupon;

class PaymentCoinOnlineController extends Controller
{
    public function bitcoin_online(Request $request)
    {
         $advertiser_url = config('app.advertiser_url');
         $minAmt = manageMinimumPayment();
         if($request->amount < $minAmt){
            //$return['code'] = 101;
            $return ['Message'] = 'Minimum $'.$minAmt.' amount required';
            $return ['Back to payment page'] = $advertiser_url . 'payment';
            return response()->json($return);
             
            
        }
         $validator = Validator::make(
            $request->all(),
            [
                'uid' => "required",
                'amount' => "required|numeric",
                'payble' => "required",
                'fee' => "required",
                'gst' => "required",
            ]
        );
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['msg'] = 'error';
            $return['err'] = $validator->errors();
            return response()->json($return);
        }
        $uid = $request->input('uid');
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category"))
            ->where('uid',$uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)
            ->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        }
            // PaymentHoldUsers($uid);
            $amt = $request->payble;
            // $inramt = Config::where('id',1)->first();
            // $amtount = $inramt['inr'];
            // $amtinr = $amt*$amtount;
            $adfund                  = new Transaction();
            $txnid                   = 'TXN'.strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'coinpay';
            $adfund->amount          = $request->amount;
            $adfund->payble_amt      = $amt;
            $adfund->fee             = $request->fee;
          	$adfund->fees_tax        = $request->fee_tax;
          	$adfund->gst             = $request->gst;
          	$adfund->gst_no          = $request->gst_no;
            $adfund->cpn_amt         = $request->coupon_amt;
            $adfund->cpn_code        = $request->cpn_code;
            $adfund->status          = 0;
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            if($adfund->save())
            {

                $return ['code']        = 200;
                $return ['message']     = 'Fund added in wallet successfully!';
            }
            else
            {
                $return ['code']    = 101;
                $return ['message'] = 'Something went wrong!';
            }
            
          	$data['user'] =  $users;
            $data['txnid'] =  $txnid;
            $data['amounts'] =  $amt;
            $data['res_url'] = ($request->request_for == 'App') ? 'https://services.7searchppc.in/payment/bitcoin/online/app_response' : 'https://services.7searchppc.in/payment/bitcoin/online/response';
            return view('payment.bitcoin_online',$data);
    }


    public function bitcoin_response(Request $request)
    {
        return Redirect::to('https://advertiser.7searchppc.in/payment/btc-success');
    }
  
  	public function bitcoin_app_response(Request $request)
    {
        echo 'Success';
    }
  
  	public function bitcoin_response2(Request $request)
    {
      return json_encode(['status'=>1]);
       // print_r($_GET['txnid']); exit(); 
    }


}

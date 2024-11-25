<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentCoinQrController extends Controller
{

    public function bitcoin_qrcode(Request $request)
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
            ]
        );
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['msg'] = 'error';
            $return['err'] = $validator->errors();
            return response()->json($return);
        }
        $uid = $request->input('uid');
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid,website_category"))
            ->where('uid',$uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)
            ->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        }
            // PaymentHoldUsers($uid);
            $amt = $request->payble;
            $adfund                  = new Transaction();
            $txnid                   = 'TXN'.strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'bitcoin';
            $adfund->amount          = $request->amount;
            $adfund->payble_amt      = $amt;
            $adfund->fee             = $request->fee;
            $adfund->fees_tax        = $request->fee_tax;
            $adfund->gst             = $request->gst;
            $adfund->gst_no          = $request->gst_no;
            $adfund->cpn_amt         = $request->cpn_amt;
            $adfund->cpn_code        = $request->cpn_code;
            $adfund->cpn_id          = $request->cpn_id;
            $adfund->status          = 0;
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            if($adfund->save())
            {
                $transactionid =  $adfund->transaction_id;
                $return ['code']        = 200;
                $return ['message']     = 'Payment Successful';
                $return ['transaction'] = $transactionid;
            }
            else
            {
                $return ['code']    = 101;
                $return ['message'] = 'Something went wrong!';
            }
            /* $data['user'] =  $users;
              $data['txnids'] =  $txnid;
              $data['amounts'] =  $amtinr; */
        return response()->json($return);
    }
    public function upload_screen(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'transaction_id' => "required",
                'screenshot' => "required",
            ]
        );
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['msg'] = 'error';
            $return['err'] = $validator->errors();
            return response()->json($return);
        }
         $trxid = $request->transaction_id;
         $trxdata = Transaction::where('transaction_id',$trxid)->first();
         if (empty($trxdata)) 
         {
            $return['code'] = 100;
            $return['msg'] = 'Not found Transaction';
            return response()->json($return);
         }
         else
         {
            $img = $request->screenshot;
            if($request->file('screenshot')) 
            {
                $imagelogo = $request->file('screenshot');
                $logos = time().'.'.$imagelogo->getClientOriginalExtension();
                $destinationPaths = base_path('public/images/bitcoin/');
                $imagelogo->move($destinationPaths, $logos);
            }
            $trxdata->screenshot = $logos;
            if ($trxdata->save())
            {
                $return['code'] = 200;
                $return['msg'] = 'Informatiom has been Updated.';
            }
            else 
            {
                $return['code'] = 101;
                $return['msg'] = 'Error: ';
            }
         return response()->json($return);
     }

    }
    
}

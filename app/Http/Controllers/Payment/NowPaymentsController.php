<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class NowPaymentsController extends Controller
{
    public $api;

    public function payment_nowpayments(Request $request)
    {
        $advertiser_url = config('app.advertiser_url');
        $minAmt = manageMinimumPayment();
        if ($request->data['payble'] < $minAmt) {
            $return['Message'] = 'Minimum $'.$minAmt.' amount required';
            $return['Back to payment page'] = $advertiser_url . 'payment';
            return response()->json($return);
        }

        $validator = Validator::make(
            $request->data,
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

        $uid = $request->data['uid'];
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))
            ->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();

        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        } 
            //  PaymentHoldUsers($uid);
            $amt = $request->data['payble'];
            
            $ip = real_ip();
            $finalres =  ipaddressconrPayu($ip);
            $usdamt =  $finalres['data']['famt'];
            $amtinr = $amt * $usdamt;

            $curcode =  $finalres['data']['currency'];

            $adfund                  = new Transaction();
            $txnid                   = $request->txnid;//'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'now_payments';
            $adfund->amount          = $request->data['amount'];
            $adfund->payble_amt      = $amt;
            $adfund->fee             = $request->data['fee'];
            $adfund->fees_tax        = $request->data['fee_tax'];
            $adfund->gst             = $request->data['gst'];
            $adfund->gst_no          = $request->data['gst_no'];
            $adfund->cpn_amt         = $request->data['cpn_amt'];
            $adfund->cpn_code        = $request->data['cpn_code'];
            $adfund->cpn_id          = $request->data['cpn_id'];
            $adfund->status          = 0;
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';

            $data = [
                'uid'           => $adfund->advertiser_code,
                'full_name'     => $users->full_name,
                'email'         => $users->email,
                'phone'         => $users->phone,
                'amount'        => $request->data['amount'],
                'payble'        => $adfund->payble_amt,
                'fee'           => $adfund->fee,
                'gst'           => $adfund->gst,
                'transaction_id'=> $txnid,
            ];

            $adfund->nowpayments_order_id = $request->invoiceDetail['id'];
            if ($adfund->save()) {
                $return['code']         = 200;
                $return['order_id']     = $request->invoiceDetail['id'];
                $return['phone']        = $users->phone;
                $return['amount']       = ceil($amtinr * 100);
                $return['transaction_id']   = $adfund->transaction_id;
                $return['message']      = 'Fund added in wallet successfully!';
            } else {
                $return['code']    = 101;
                $return['message'] = 'Something went wrong!';
            }
        return response()->json($return);
    }


    // Success Response
    public function nowpayments_success_response(Request $request)
    {
        $email = $request->email;
        $amount = $request->amount;
        $txnid = $request->txnid;

        $successMsg = "NOWPayments|$email|Success|$txnid|$amount|usd";
        $urlSuccess = base64_encode($successMsg); 
        
        if ($urlSuccess) {    
            $url = 'https://advertiser.7searchppc.com/payment/success/'.$urlSuccess;
            // $url = 'http://127.0.0.1:3000/payment/success/'.$urlSuccess;
            return Redirect::away($url);
        }
    }


    // Failed Response
    public function nowpayments_failed_response(Request $request)
    {
        $email = $request->email;
        $amount = $request->amount;
        $txnid = $request->txnid;

        $failedMsg = "NOWPayments|$email|Failed|$txnid|$amount|usd";
        $urlFailed = base64_encode($failedMsg); 
        
        if ($urlFailed) {    
            $url = 'https://advertiser.7searchppc.com/payment/failed/'.$urlFailed;
            // $url = 'http://127.0.0.1:3000/payment/failed/'.$urlFailed;
            return Redirect::away($url);
        }
    }
}
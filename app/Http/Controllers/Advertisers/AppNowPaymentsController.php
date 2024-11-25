<?php

namespace App\Http\Controllers\Advertisers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

class AppNowPaymentsController extends Controller
{
 // FOR MOBILE NOW PAYMENTS CODE START

 public function mobile_payment_nowpayments(Request $request)
 {
     $advertiser_url = config('app.advertiser_url');
     $minAmt = manageMinimumPayment();
     if ($request->amount < $minAmt) {
         $return['Message'] = 'Minimum $'.$minAmt.' amount required';
         $return['Back to payment page'] = $advertiser_url . 'payment';
         return response()->json($return);
     }

     $endpoint = 'https://api.nowpayments.io/v1/status'; //For Live
     $txnid = 'TXN' . strtoupper(uniqid());

     $validator = Validator::make(
         $request->all(),
         [
             'uid' => "required",
             'amount' => "required",
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

     $client = new Client();
     $response = $client->get($endpoint);
     if ($response->getBody()) {
         $data = json_decode($response->getBody());
         if ($data->message == 'OK') {

             $endpointForInvoice = 'https://api.nowpayments.io/v1/invoice';
             $uid = $request->uid;
             $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))
                 ->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
                 
             if (empty($users)) {
                 $return['code'] = 101;
                 $return['msg'] = 'Not found User ';
                 return response()->json($return);
             } else {
                 $email = $users->email;
     
                 // create params for post request
                 $params = [
                     'price_amount' => $request->payble,
                     'price_currency' => 'usd',
                     'success_url' =>'https://services.7searchppc.in/api/nowpayments/payment/success?email='.$email.'&amount='.$request->payble.'&txnid='.$txnid,
                     'cancel_url' => 'https://services.7searchppc.in/api/nowpayments/payment/failed?email='.$email.'&amount='.$request->payble.'&txnid='.$txnid
                 ];    
     
                 $adfund                  = new Transaction();
                 $adfund->advertiser_code = $uid;
                 $adfund->transaction_id  = $txnid;
                 $adfund->payment_mode    = 'now_payments';
                 $adfund->amount          = $request->amount;
                 $adfund->payble_amt      = $request->payble;
                 $adfund->fee             = $request->fee;
                 $adfund->fees_tax        = $request->fee_tax;
                 $adfund->gst             = $request->gst;
                 $adfund->gst_no          = $request->gst_no;
                 $adfund->cpn_amt         = $request->cpn_amt;
                 $adfund->cpn_code        = $request->cpn_code;
                 $adfund->cpn_id          = $request->cpn_id;
                 $adfund->status          = 0; // pending status
                 $adfund->category        = $users->website_category;
                 $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
     
                 if ($adfund->save()) {
                     try {
                         $client = new Client();
                         $response = $client->post($endpointForInvoice, [
                             'json' => $params,
                             'headers' => [
                                 'x-api-key' => '7GG1JHX-YTK4DJ6-KCTMQVA-MWNBG4S',
                                 'Content-Type' => 'application/json'
                             ],
                         ]);
                         if ($response->getBody()) {
                             $data = json_decode($response->getBody(), true);
                             $adfund = Transaction::where('advertiser_code', $uid)->where('transaction_id', $txnid)->first(); // Update nowpayments_order_id payment status
                             $adfund->nowpayments_order_id = $data['id'];
                             if ($adfund->save()) {
                                return Redirect::to($data['invoice_url']);
                               // dd($data['invoice_url']);
                                 //return response()->json($data);
                             }
                         }
                     } catch (\Exception $e) {
                         return response()->json(['error' => $e->getMessage()], 500);
                     }
                 }
     
                 $return['code']    = 101;
                 $return['message'] = 'Something went wrong!';
                 return response()->json($return);
              }
              return response()->json($data);
            }
        }
    }

 // FOR MOBILE NOW PAYMENTS CODE END
}
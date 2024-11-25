<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Models\Transaction;
use App\Models\User;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\UserNotification;
use App\Models\Notification;
use Carbon\Carbon;
use Session;
use Stripe;
use PDF;
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransactionMail;


class PaymentStripeController extends Controller
{

    public function key_stripe()
    {
        
        /* Test Key */
         $key = 'sk_test_51IHNc1BMvtqYADrMHIDwLoP80WDULGkRzd7Osfrot3hReSiXQIkjLemEjhvxPu2jTbxRoguflqf3yrbHXt0bdwFG000fG7bfSn';
        return $key;
    }

    public function payment_stripe(Request $request)
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
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid"))->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);  
        }
        
            // PaymentHoldUsers($uid);
            $amt = $request->payble;
            // $inramt = Config::where('id',1)->first();
            // $amtount = $inramt['inr'];
            $adfund                  = new Transaction();
            $txnid                   = 'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'stripe';
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
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            if ($adfund->save()) {

                $return['code']        = 200;
                $return['message']     = 'Fund added in wallet successfully!';
            } else {
                $return['code']    = 101;
                $return['message'] = 'Something went wrong!';
            }
            
            $cont_array = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XOF', 'XPF'];
          
            $ip = real_ip();
            $finalres =  ipaddressconr($ip);
            $usdamt =  $finalres['data']['famt'];
            $amt2 = $amt * $usdamt;
            $curcode =  $finalres['data']['currency'];
            $data['user'] =  $users;
            $data['amounts'] = (in_array($curcode, $cont_array)) ? ceil($amt2) : round($amt2, 2) * 100;
            $data['currencycode'] = $curcode;
            $data['txnids'] =  $txnid;
            $data['key'] =  $this->key_stripe();
          	$data['request_for'] = ($request->request_for == 'App') ? 'App' : 'Web';
            return view('payment.stripe', $data);
    }

    // public function stripe_response($strpid)
    // {

    //     $stripe = new \Stripe\StripeClient($this->key_stripe());
    //     $checksts = $stripe->checkout->sessions->retrieve(
    //         $strpid,
    //         []
    //     );
    //   	$advertiser_url = config('app.advertiser_url');
    //     if ($checksts->payment_status == 'paid') {
    //         $trxid = $checksts['metadata']['order_id'];
    //       	$request_for = $checksts['metadata']['request_for'];
    //         $paymentid = $checksts['payment_intent'];
    //         $transac = Transaction::where('transaction_id', $trxid)->first();
    //         $statu = $transac->status;
    //         $cpncode = $transac->cpn_code;
    //         $cpnid = $transac->cpn_id;
    //         $cpnamt = $transac->amount;
    //         if ($statu == 0) {
    //             $uid = $transac['advertiser_code'];
    //             $user         = User::where('uid', $uid)->first();
    //              $amounts = $transac['amount'];
    //              if ($user->referal_code != "" && $user->referalpmt_status == 0) {
    //                 $url = "http://refprogramserv.7searchppc.in/api/add-transaction";
    //                 $refData = [
    //                     'user_id' => $uid,
    //                     'referral_code' => $user->referal_code,
    //                     'amount' => $amounts,
    //                     'transaction_type' => 'Payment',
    //                 ];
    //                 $curl = curl_init();

    //                 curl_setopt_array($curl, [
    //                     CURLOPT_URL => $url,
    //                     CURLOPT_RETURNTRANSFER => true,
    //                     CURLOPT_CUSTOMREQUEST => "POST",
    //                     CURLOPT_POSTFIELDS => json_encode($refData),
    //                     CURLOPT_HTTPHEADER => [
    //                         "Content-Type: application/json"
    //                     ],
    //                 ]);

    //                 $response = curl_exec($curl);

    //                 curl_close($curl);
    //             }
    //             $user->referalpmt_status = 1;
    //             $transac->payment_id      = $paymentid;
    //             $transac->status          = 1;
    //             if ($transac->update()) {
    //                 $transaclog   = new TransactionLog();
    //                 $transaclog->transaction_id    = $trxid;
    //                 $transaclog->advertiser_code   = $uid;
    //                 $transaclog->amount            = $amounts;
    //                 // $transaclog->serial_no         = generate_serial();
                    
    //                 $transaclog->serial_no         = $user->account_type == 1 ? 0 : generate_serial();
    //                 $transaclog->pay_type          = 'credit';
    //                 $transaclog->remark            = 'Amount added to wallet from Stripe';
    //                 if ($transaclog->save()) {
                        
    //                     $user->wallet =  $user->wallet + $amounts;
    //                     $user->update();
    //                     updateAdvWallet($uid, $amounts);
                      
    //                     $notification = new Notification();
    //                     $notification->notif_id = gennotificationuniq();
    //                     $notification->title = 'Payment successfully';
    //                     $notification->noti_desc = 'Your payment has been successfully completed. The amount is added to your wallet.';
    //                     $notification->noti_type = 1;
    //                     $notification->noti_for = 1;
    //                     $notification->all_users = 0;
    //                     $notification->status = 1;
    //                     if ($notification->save()) {
    //                         $noti = new UserNotification();
    //                         $noti->notifuser_id = gennotificationuseruniq();
    //                         $noti->noti_id = $notification->id;
    //                         $noti->user_id = $user->uid;
    //                         $noti->user_type = 1;
    //                         $noti->view = 0;
    //                         $noti->created_at = Carbon::now();
    //                         $noti->updated_at = now();
    //                         $noti->save();
    //                     }
                      
                      
    //                     $cpn_res =   getCouponCal($uid, $cpncode, $cpnamt,$cpnid);
    //                     //   $cpnamt = ($cpn_res['bonus_amount'] > 0) ? $cpn_res['bonus_amount'] : 0 ;
    //                   //  if (!empty($cpn_res['bonus_amount'])) {
    //                       if (!empty($transac->cpn_amt)) {
    //                         $transaclog1   = new TransactionLog();
    //                         $transaclog1->transaction_id    = $trxid;
    //                         $transaclog1->advertiser_code   = $uid;
    //                         $transaclog1->amount            = $transac->cpn_amt;
    //                         $transaclog1->pay_type          = 'credit';
    //                         $transaclog1->remark            = 'Coupon bonus added to wallet';
    //                         $transaclog1->cpn_typ 			= 1;
    //                         $transaclog1->save();
    //                         $user1         = User::where('uid', $uid)->first();
    //                     //    $user1->wallet =  $user->wallet + $cpn_res['bonus_amount'];
    //                         $user1->wallet =  $user->wallet + $transac->cpn_amt;
    //                         $user1->update();
    //                         updateAdvWallet($uid, $transac->cpn_amt);
    //                         $usedcoupon = new UsedCoupon();
    //                         $usedcoupon->advertiser_code = $uid;
    //                         $usedcoupon->coupon_id = $cpnid;
    //                         $usedcoupon->coupon_code     = $cpncode;
    //                         $usedcoupon->save();
    //                     }
    //                     $email         = $user['email'];
    //                     $amount        = $transac['amount'];
    //                     /* Mail Section */
    //                     $fullname      = "$user->first_name $user->last_name";
    //                     $emailname     = $user->email;
    //                     $phone         = $user->phone;
    //                     $addressline1  = $user->address_line1;
    //                     $addressline2  = $user->address_line2;
    //                     $city          = $user->city;
    //                     $state         = $user->state;
    //                     $country       = $user->country;
    //                     $useridas      = $user->uid;
    //                     $transactionid = $transaclog->transaction_id;
    //                     $createdat     = $transaclog->created_at;
    //                     $paymentmode   = $transac->payment_mode;
    //                     $amount        = $transac->amount;
    //                     $paybleamt     = $transac->amount + $transac->fee + $transac->gst;
    //                   // $fee           = ($transac->fee - $transac->fees_tax);
    //                   // $gst           = ($transac->gst + $transac->fees_tax);
    //                     // $amount = $transac->amount;
    //                     // $paybleamt = $transac->payble_amt;
    //                     // $fee = $transac->fee;
    //                     // $gst = $transac->gst;
    //                     $remark = $transaclog->remark;
    //                     $subjects = "Fund Added Successfully";
                                
    //                      if($user->country == 'INDIA'){
    //                               $gst           = $transac->gst;
    //                             }else{
    //                               $gst           = $transac->gst + $transac->fees_tax;
    //                             }
    //                              $report = Transaction::select('transactions.payble_amt', 'transactions.gst', 'transactions.fee', 'transactions.fees_tax', 'transaction_logs.remark', 'transactions.payment_mode', 'transactions.amount')
    //                             ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
    //                             ->where('transactions.transaction_id', $transactionid)
    //                             ->first();
    //                         // $report->gst = ($report->gst > 0) ? $report->gst + $report->fees_tax:'';
    //                         $subtotal = number_format($report->amount + $report->fee, 2);
    //                          if($user->country == 'INDIA'){
    //                             $fee = $report->fee;
    //                          }else{
    //                               if($report->payMode == 'now_payments' || $report->payMode == 'bitcoin' || $report->payMode == 'coinpay' || $report->payment_mode == 'stripe'){
    //                               if($report !== null && isset($report->fee)){
    //                                 $fee = round($report->fee * 100) / 100;
    //                                 $fee = number_format($fee, 2);
    //                               } 
    //                             }else{
    //                                 if($report !== null && isset($report->fee)){
    //                                     $fee = round(($report->fee + $report->fees_tax) * 100) / 100;
    //                                     $fee = number_format($fee, 2);
    //                                 } 
    //                             }
    //                          }
                     
    //                 paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark ,$subtotal);
                    
                    
    //                   // paymentSuccessMail($subjects,$fullname,$emailname,$phone,$addressline1,$addressline2,$city,$state,$country,$createdat,$useridas,$transactionid,$paymentmode,$amount,$paybleamt,$fee,$gst,$remark);

    //                     $msg = "Stripe|$email|Success|$paymentid|$amount|usd";
    //                     $msg1 = base64_encode($msg);

    //                   	if($request_for == 'App')
    //                     {
    //                       echo '<script>
    //                               setTimeout(function () {
    //                                       window.ReactNativeWebView.postMessage("success")
    //                                     }, 1500)
    //                               </script>';
    //                     }
    //                     else
    //                     {
    //                       return Redirect::to($advertiser_url.'payment/success/'.$msg1);
    //                     }
    //                   	//return Redirect::to($advertiser_url.'payment/success/' . $msg1 . '');
    //                 }
    //             } else {
                    
    //                 if($request_for == 'App')
    //                 {
    //                   echo '<script>
    //                                 setTimeout(function () {
    //                                         window.ReactNativeWebView.postMessage("pending")
    //                                       }, 1500)
    //                                 </script>';
    //                 }
    //                 else
    //                 {
    //                   return Redirect::to($advertiser_url.'payment');
    //                 }
    //                 //return Redirect::to($advertiser_url.'payment');
    //             }
    //         }
    //     } else {
    //         $trxid = $checksts['metadata']['order_id'];
    //       	$request_for = $checksts['metadata']['request_for'];
    //         $paymentid = $checksts['payment_intent'];
    //         $remark  = 'canceled';
    //         $transac = Transaction::where('transaction_id', $trxid)->first();
    //         $transac->remark      = $remark;
    //         $transac->payment_id      = $paymentid;
    //         $transac->status      = 2;
    //         if ($transac->update()) {
    //             $uidds = $transac['advertiser_code'];
    //             $usersdeatils  = User::where('uid', $uidds)->first();
    //             $email = $usersdeatils['email'];
    //             $amount = $transac['amount'];
    //             $msg = "Stripe|$email|Failed|$paymentid|$amount|usd";
    //             $msg1 = base64_encode($msg);
    //             if($request_for == 'App')
    //             {
    //               echo '<script>
    //                                 setTimeout(function () {
    //                                         window.ReactNativeWebView.postMessage("failed")
    //                                       }, 1500)
    //                                 </script>';
    //             }
    //             else
    //             {
    //                  $msg = "Stripe|$email|Failed|$paymentid|$amount|usd";
    //                       $msg1 = base64_encode($msg);
    //               return Redirect::to($advertiser_url.'payment/failed/' . $msg1 . '');
    //             }
    //             //return Redirect::to($advertiser_url.'payment/failed/' . $msg1 . '');
    //         } else {
    //             if($request_for == 'App')
    //             {
    //               echo '<script>
    //                                 setTimeout(function () {
    //                                         window.ReactNativeWebView.postMessage("pending")
    //                                       }, 1500)
    //                                 </script>';
    //             }
    //             else
    //             {
    //               return Redirect::to($advertiser_url.'demo1');
    //             }
    //             //return Redirect::to($advertiser_url.'demo1');
    //         }
    //     }
    // }
    
    public function stripe_response($strpid)
    {

        $stripe = new \Stripe\StripeClient($this->key_stripe());
        $checksts = $stripe->checkout->sessions->retrieve(
            $strpid,
            []
        );
      	$advertiser_url = config('app.advertiser_url');
        if ($checksts->payment_status == 'paid') {
            $trxid = $checksts['metadata']['order_id'];
          	$request_for = $checksts['metadata']['request_for'];
            $paymentid = $checksts['payment_intent'];
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $statu = $transac->status;
            $cpncode = $transac->cpn_code;
            $cpnid = $transac->cpn_id;
            $cpnamt = $transac->amount;
            if ($statu == 0) {
                $uid = $transac['advertiser_code'];
                $user         = User::where('uid', $uid)->first();
                 $amounts = $transac['amount'];
                $transac->payment_id      = $paymentid;
                $transac->status          = 1;
                if ($transac->update()) {
                    $transaclog   = new TransactionLog();
                    $transaclog->transaction_id    = $trxid;
                    $transaclog->advertiser_code   = $uid;
                    $transaclog->amount            = $amounts;
                    // $transaclog->serial_no         = generate_serial();
                    
                    $transaclog->serial_no         = $user->account_type == 1 ? 0 : generate_serial();
                    $transaclog->pay_type          = 'credit';
                    $transaclog->remark            = 'Amount added to wallet from Stripe';
                    if ($transaclog->save()) {
                        
                        if ($user->referal_code != "" && $user->referalpmt_status == 0) {
                            $url = "https://refapi.7searchppc.in/api/add-transaction";
                            $refData = [
                                'user_id' => $uid,
                                'referral_code' => $user->referal_code,
                                'amount' => $amounts,
                                'transaction_type' => 'Payment',
                            ];
                            $curl = curl_init();
        
                            curl_setopt_array($curl, [
                                CURLOPT_URL => $url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => json_encode($refData),
                                CURLOPT_HTTPHEADER => [
                                    "Content-Type: application/json"
                                ],
                            ]);
        
                            $response = curl_exec($curl);
        
                            curl_close($curl);
                        }
                        $user->referalpmt_status = 1;
                        
                        $user->wallet =  $user->wallet + $amounts;
                        $user->update();
                        
                        $notification = new Notification();
                        $notification->notif_id = gennotificationuniq();
                        $notification->title = 'Payment successfully';
                        $notification->noti_desc = 'Your payment has been successfully completed. The amount is added to your wallet.';
                        $notification->noti_type = 1;
                        $notification->noti_for = 1;
                        $notification->all_users = 0;
                        $notification->status = 1;
                        if ($notification->save()) {
                            $noti = new UserNotification();
                            $noti->notifuser_id = gennotificationuseruniq();
                            $noti->noti_id = $notification->id;
                            $noti->user_id = $user->uid;
                            $noti->user_type = 1;
                            $noti->view = 0;
                            $noti->created_at = Carbon::now();
                            $noti->updated_at = now();
                            $noti->save();
                        }
                        updateAdvWallet($uid, $amounts);
                        $cpn_res =   getCouponCal($uid, $cpncode, $cpnamt,$cpnid);
                        //   $cpnamt = ($cpn_res['bonus_amount'] > 0) ? $cpn_res['bonus_amount'] : 0 ;
                      //  if (!empty($cpn_res['bonus_amount'])) {
                          if (!empty($transac->cpn_amt)) {
                            $transaclog1   = new TransactionLog();
                            $transaclog1->transaction_id    = $trxid;
                            $transaclog1->advertiser_code   = $uid;
                            $transaclog1->amount            = $transac->cpn_amt;
                            $transaclog1->pay_type          = 'credit';
                            $transaclog1->remark            = 'Coupon bonus added to wallet';
                            $transaclog1->cpn_typ 			= 1;
                            $transaclog1->save();
                            $user1         = User::where('uid', $uid)->first();
                        //    $user1->wallet =  $user->wallet + $cpn_res['bonus_amount'];
                            $user1->wallet =  $user->wallet + $transac->cpn_amt;
                            $user1->update();
                            updateAdvWallet($uid, $transac->cpn_amt);
                            $usedcoupon = new UsedCoupon();
                            $usedcoupon->advertiser_code = $uid;
                            $usedcoupon->coupon_id = $cpnid;
                            $usedcoupon->coupon_code     = $cpncode;
                            $usedcoupon->save();
                        }
                        $email         = $user['email'];
                        $amount        = $transac['amount'];
                        /* Mail Section */
                        $fullname      = "$user->first_name $user->last_name";
                        $emailname     = $user->email;
                        $phone         = $user->phone;
                        $addressline1  = $user->address_line1;
                        $addressline2  = $user->address_line2;
                        $city          = $user->city;
                        $state         = $user->state;
                        $country       = $user->country;
                        $useridas      = $user->uid;
                        $transactionid = $transaclog->transaction_id;
                        $createdat     = $transaclog->created_at;
                        $paymentmode   = $transac->payment_mode;
                        $amount        = $transac->amount;
                        $paybleamt     = $transac->amount + $transac->fee + $transac->gst;
                        // $fee           = ($transac->fee - $transac->fees_tax);
                        // $gst           = ($transac->gst + $transac->fees_tax);
                        // $amount = $transac->amount;
                        // $paybleamt = $transac->payble_amt;
                        // $fee = $transac->fee;
                        // $gst = $transac->gst;
                        $remark = $transaclog->remark;
                        $subjects = "Fund Added Successfully";
                        if($user->country == 'INDIA'){
                                  $gst           = $transac->gst;
                                }else{
                                  $gst           = $transac->gst + $transac->fees_tax;
                                }
                                 $report = Transaction::select('transactions.payble_amt', 'transactions.gst', 'transactions.fee', 'transactions.fees_tax', 'transaction_logs.remark', 'transactions.payment_mode', 'transactions.amount')
                                ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
                                ->where('transactions.transaction_id', $transactionid)
                                ->first();
                            // $report->gst = ($report->gst > 0) ? $report->gst + $report->fees_tax:'';
                            $subtotal = number_format($report->amount + $report->fee, 2);
                             if($user->country == 'INDIA'){
                                $fee = $report->fee;
                             }else{
                                  if($report->payMode == 'now_payments' || $report->payMode == 'bitcoin' || $report->payMode == 'coinpay' || $report->payment_mode == 'stripe'){
                                  if($report !== null && isset($report->fee)){
                                    $fee = round($report->fee * 100) / 100;
                                    $fee = number_format($fee, 2);
                                  } 
                                }else{
                                    if($report !== null && isset($report->fee)){
                                        $fee = round(($report->fee + $report->fees_tax) * 100) / 100;
                                        $fee = number_format($fee, 2);
                                    } 
                                }
                             }
                        paymentSuccessMail($subjects,$fullname,$emailname,$phone,$addressline1,$addressline2,$city,$state,$country,$createdat,$useridas,$transactionid,$paymentmode,$amount,$paybleamt,$fee,$gst,$remark,$subtotal);

                        $msg = "Stripe|$email|Success|$paymentid|$amount|usd";
                        $msg1 = base64_encode($msg);

                      	if($request_for == 'App')
                        {
                          echo '<script>
                                  setTimeout(function () {
                                          window.ReactNativeWebView.postMessage("success")
                                        }, 1500)
                                  </script>';
                        }
                        else
                        {
                          return Redirect::to($advertiser_url.'payment/success/'.$msg1);
                        }
                      	//return Redirect::to($advertiser_url.'payment/success/' . $msg1 . '');
                    }
                } else {
                    
                    if($request_for == 'App')
                    {
                      echo '<script>
                                    setTimeout(function () {
                                            window.ReactNativeWebView.postMessage("pending")
                                          }, 1500)
                                    </script>';
                    }
                    else
                    {
                      return Redirect::to($advertiser_url.'payment');
                    }
                    //return Redirect::to($advertiser_url.'payment');
                }
            }
        } else {
            $trxid = $checksts['metadata']['order_id'];
          	$request_for = $checksts['metadata']['request_for'];
            $paymentid = $checksts['payment_intent'];
            $remark  = 'canceled';
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark      = $remark;
            $transac->payment_id      = $paymentid;
            $transac->status      = 2;
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils  = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Stripe|$email|Failed|$paymentid|$amount|usd";
                $msg1 = base64_encode($msg);
                if($request_for == 'App')
                {
                  echo '<script>
                                    setTimeout(function () {
                                            window.ReactNativeWebView.postMessage("failed")
                                          }, 1500)
                                    </script>';
                }
                else
                {
                     $msg = "Stripe|$email|Failed|$paymentid|$amount|usd";
                          $msg1 = base64_encode($msg);
                  return Redirect::to($advertiser_url.'payment/failed/' . $msg1 . '');
                }
                //return Redirect::to($advertiser_url.'payment/failed/' . $msg1 . '');
            } else {
                if($request_for == 'App')
                {
                  echo '<script>
                                    setTimeout(function () {
                                            window.ReactNativeWebView.postMessage("pending")
                                          }, 1500)
                                    </script>';
                }
                else
                {
                  return Redirect::to($advertiser_url.'demo1');
                }
                //return Redirect::to($advertiser_url.'demo1');
            }
        }
    }




}

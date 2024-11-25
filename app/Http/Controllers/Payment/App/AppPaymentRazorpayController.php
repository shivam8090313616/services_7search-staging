<?php

namespace App\Http\Controllers\Payment\App;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\Models\UserNotification;
use App\Models\Notification;
use Carbon\Carbon;


class AppPaymentRazorpayController extends Controller
{

    public function razorpayResponsesuccess(){
        return view('payment.razorpaysuccess');
    }
    public function razorpayResponsefailed(){
        return view('payment.razorpayfailed');
    }
    public function payment_razorpay(Request $request)
    {
        
        $advertiser_url = config('app.advertiser_url');
        $minAmt = manageMinimumPayment();
        if ($request->amount < $minAmt) {
            $return['Message'] = 'Minimum $'.$minAmt.' amount required';
            $return['Back to payment page'] = $advertiser_url . 'payment';
            return response()->json($return);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'uid'    => "required",
                'amount' => "required|numeric",
                'payble' => "required",
                'fee'    => "required",
                'gst'    => "required",
            ]
        );
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['msg'] = 'error';
            $return['err'] = $validator->errors();
            return response()->json($return);
        }
        $uid = $request->input('uid');
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))
            ->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        } 
            PaymentHoldUsers($uid);
            $amt = $request->payble;
            $ip = real_ip();
            //$ip = '183.82.160.200';
            $finalres =  ipaddressconrPayu($ip);
            $usdamt     =  $finalres['data']['famt'];
            $amtinr     = $amt * $usdamt;
            $curcode =  $finalres['data']['currency'];

            $adfund                  = new Transaction();
            $txnid                   = 'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'razorpay';
            $adfund->amount          = number_format($request->amount, 2);
            $adfund->payble_amt      = number_format($amt, 2);
            $adfund->fee             = number_format($request->fee, 2);
            $adfund->fees_tax        = number_format($request->fee_tax, 2);
            $adfund->gst             = number_format($request->gst, 2);
            $adfund->gst_no          = $request->gst_no;
            $adfund->cpn_amt         = $request->cpn_amt;
            $adfund->cpn_code        = $request->cpn_code;
            $adfund->cpn_id          = $request->cpn_id;
            $adfund->status          = 0;
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            $orderData = [
                'amount'        => ceil($amtinr * 100),
                'currency'      => 'INR',
            ];
            $data = [
                'uid'            => $adfund->advertiser_code,
                'full_name'      => $users->full_name,
                'email'          => $users->email,
                'phone'          => $users->phone,
                'amount'         => $request->amount,
                'payble'         => $adfund->payble_amt,
                'fee'            => $adfund->fee,
                'gst'            => $adfund->gst,
                'transaction_id' => $txnid,
            ];
            $url = 'https://api.razorpay.com/v1/orders';
            $jsonData = json_encode($orderData);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                //'Authorization: Basic ' . base64_encode('rzp_live_N5YR4bF9cLQMju' . ':' . 'Gc7UigrasyJu5MUqOUKLI4rd'),
                'Authorization: Basic ' . base64_encode('rzp_test_ZPYP5eYHITB67N' . ':' . '609X2TwCCyMxboBu6eXXz1WX'),
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $response = curl_exec($ch);
            $razorpayOrder = json_decode($response);
            $adfund->rpay_order_id = $razorpayOrder->id;
            $adfund->save();
            echo '
            <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
            var options = {
                "key": "rzp_test_ZPYP5eYHITB67N",
                "amount": "' . $orderData['amount'] . '",
                "currency": "INR",
                "name": "7Search PPC",
                "description": "7Search PPC",
                "order_id" :  "' . $razorpayOrder->id . '",
                "handler": function (response){
                    $.ajax({
                        url: `https://services.7searchppc.in/api/app_razorpay_response`,
                        method: `POST`,
                        data: { response: JSON.stringify(response) },
                        success: function(data) {
                            window.location.href = `https://services.7searchppc.in/razorpay/success`;
                        },
                        error: function(error) {
                            console.error(`Error:`, error);
                        }
                    });
                },
                "modal": {
                    "ondismiss": function(){
                        window.location.href = `https://services.7searchppc.in/razorpay/failed`;
                     }
                },
                "image": "https://www.7searchppc.com/assets/images/favicon.png",
                "prefill": {
                    "name": "' . $users->full_name . '",
                    "email": "' . $users->email . '"
                },
                "theme": {
                    "color": "#ff7529"
                }
            };
            var rzp = new Razorpay(options);
            rzp.on(`payment.failed`, function (response){
                window.location.href = `https://services.7searchppc.in/razorpay/failed`;
            });
            rzp.open();
            </script>';
    }

    public function razorpay_response(Request $request)
    {
            $responseArray = $request->input('response');
            $response = json_decode($responseArray, true);
            $advertiser_url = config('app.advertiser_url');
        if (!empty($response['razorpay_signature'])) {
            $payid = $response['razorpay_payment_id'];
            $trxid = $response['razorpay_order_id'];
            $signature = $response['razorpay_signature'];
            $transac = Transaction::where('rpay_order_id', $trxid)->first();
            $status = $transac->status;
            $cpncode = $transac->cpn_code;
            $cpnid = $transac->cpn_id;
            $cpnamt = $transac->cpn_amt;
            if ($status == 0) {
                $uid = $transac['advertiser_code'];
                $user = User::where('uid', $uid)->first();
                $amounts = $transac['amount'];
                $transac->payment_id = $payid;
                $transac->status = 1;
                $transac->remark = 'Payment success';
                if ($transac->update()) {
                    $transaclog = new TransactionLog();
                    $transaclog->transaction_id =  $transac->transaction_id;
                    $transaclog->advertiser_code = $uid;
                    $transaclog->rpay_signature = $signature;
                    $transaclog->amount = $amounts;
                    // $transaclog->serial_no = generate_serial();
                    $transaclog->serial_no         = $user->account_type == 1 ? 0 : generate_serial();
                    $transaclog->pay_type = 'razorpay';
                    $transaclog->remark = 'Amount added to wallet succefully ! - Razorpay';
                    if ($transaclog->save()) {
                        
                        $user->wallet = $user->wallet + $amounts;
                        $user->update();
                        updateAdvWallet($uid, $amounts);
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
                        $cpn_res = getCouponCal($uid, $cpncode, $amounts, $cpnid);
                        $getusersrecords = DB::table('users')->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
                        $fullname      = $getusersrecords->first_name.' '.$getusersrecords->last_name;
                        $emailname     = $getusersrecords->email;
                        $phone         = $getusersrecords->phone;
                        $addressline1  = $getusersrecords->address_line1;
                        $addressline2  = $getusersrecords->address_line2;
                        $city          = $getusersrecords->city;
                        $state         = $getusersrecords->state;
                        $country       = $getusersrecords->country;
                        $useridas      = $getusersrecords->uid;
                        $createdat     = $transac->created_at;
                        $transactionid = $transac->transaction_id;
                        $paymentmode   = $transac->payment_mode;
                        $amount        = $transac->amount;
                        //$paybleamt     = $transac->amount + $transac->fee + $transac->gst;
                        $paybleamt     = number_format($transac->payble_amt, 2);
                        // $amount        = ($transac->amount +$transac->fee - $transac->fees_tax);
                        // $paybleamt     = ($transac->amount + $transac->fee + $transac->fees_tax + $transac->gst - $transac->fees_tax);
                        //$fee           = $transac->fee - $transac->fees_tax;
                        //$gst           = $transac->gst + $transac->fees_tax;
                        $remark        = $transac->remark;
                        $subjects      = "Fund Added Successfully";
                        if($user->country == 'INDIA'){
                          $gst           = number_format($transac->gst, 2);
                        }else{
                          $gst           = number_format($transac->gst + $transac->fees_tax, 2);
                        }
                         $report = Transaction::select('transactions.payble_amt', 'transactions.gst', 'transactions.fee', 'transactions.fees_tax', 'transaction_logs.remark', 'transactions.payment_mode', 'transactions.amount')
                        ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
                        ->where('transactions.transaction_id', $transactionid)
                        ->first();
                    // $report->gst = ($report->gst > 0) ? $report->gst + $report->fees_tax:'';
                    $subtotal = number_format($report->amount + $report->fee, 2);
                     if($user->country == 'INDIA'){
                        $fee = number_format($report->fee, 2);
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
                      paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark ,$subtotal);
                    
                    
                    
                        //paymentSuccessMail($subjects,$fullname,$emailname,$phone,$addressline1,$addressline2,$city,$state,$country,$createdat,$useridas,$transactionid,$paymentmode,$amount,$paybleamt,$fee,$gst,$remark);
                        if ($cpn_res['code'] == 200) {
                            $transaclog = new TransactionLog();
                            $transaclog->transaction_id = $trxid;
                            $transaclog->rpay_signature = $signature;
                            $transaclog->advertiser_code = $uid;
                            $transaclog->amount = $cpn_res['bonus_amount'];
                            $transaclog->pay_type = 'credit';
                            $transaclog->cpn_typ = 1;
                            $transaclog->remark = 'Coupon Amount added to wallet successfully';
                            $transaclog->save();
                            $user = User::where('uid', $uid)->first();
                            $user->wallet = $user->wallet + $cpn_res['bonus_amount'];
                            $user->update();
                            updateAdvWallet($uid, $cpn_res['bonus_amount']);
                            /* Used Coupon Log Section Save  pay_type */ 
                            $usedcoupon = new UsedCoupon();
                            $usedcoupon->advertiser_code = $uid;
                            $usedcoupon->coupon_code = $cpncode;
                            $usedcoupon->coupon_id = $cpnid;
                            $usedcoupon->save();
                            /* Used Coupon Log Section Save  */
                        }

                        $email = $user['email'];
                        $amount = $transac['amount'];
                        $msg = "Razropay|$email|Success|$payid|$amount|usd";
                        $msg1 = base64_encode($msg);
                        // return 'ok';
                        // return Redirect::to($advertiser_url . 'payment/success/' . $msg1);
                        $return['code'] = 200;
                        $return['message'] = 'Payment Success!';
                        $return['url'] = $msg1;
                        return json_encode($return, JSON_NUMERIC_CHECK);
                    }
                } else {
                    return Redirect::to($advertiser_url . 'payment');
                }
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }
        } else {
            $remark = 'Payment failed';
            $trxid = $request->transId;
            $payid = $request->data['error']['metadata']['payment_id'];
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark = $remark;
            $transac->payment_id = $payid;
            $transac->status = 2;
            
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Razorpay|$email|Fail|$payid|$amount|usd";
                $msg1 = base64_encode($msg);
                // return Redirect::to($advertiser_url . 'payment/failed/' . $msg1 . '');
                $return['code'] = 101;
                $return['message'] = 'Payment Failed!';
                $return['url'] = $msg1;
                return json_encode($return, JSON_NUMERIC_CHECK);
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }
        }
    }
}

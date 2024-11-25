<?php

namespace App\Http\Controllers\Payment\App;

use App\Http\Controllers\Controller;
use App\Mail\TransactionMail;
use App\Models\Config;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\Models\UserNotification;
use App\Models\Notification;
use Carbon\Carbon;

class AppPaymentPhonepeController extends Controller
{
    public function payment_phonepe(Request $request)
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
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))->where('uid', $uid)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        }
             PaymentHoldUsers($uid);
            $amt = $request->payble;

            $ip = real_ip();
            $finalres = ipaddressconr($ip);
            $usdamt = $finalres['data']['famt'];
            $amtinr = $amt * $usdamt;
            $curcode = $finalres['data']['currency'];

            $adfund = new Transaction();
            $txnid = 'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code   = $users->uid;
            $adfund->transaction_id    = $txnid;
            $adfund->payment_mode      = 'phonepe';
            $adfund->amount            = number_format($request->amount, 2, '.', '');
            $adfund->payble_amt        = number_format($amt, 2, '.', '');
            $adfund->fee               = number_format($request->fee, 2, '.', '');
            $adfund->fees_tax          = number_format($request->fee_tax, 2, '.', '');
            $adfund->gst               = number_format($request->gst, 2, '.', '');
            $adfund->cpn_amt           = number_format($request->cpn_amt, 2, '.', '');
            $adfund->cpn_code          = $request->cpn_code;
            $adfund->cpn_id            = $request->cpn_id;
            $adfund->address           = $users->address_line1 . ' ' . $users->address_line2;
            $adfund->city              = $users->city;
            $adfund->state             = $users->state;
            $adfund->post_code         = $users->post_code;
            $adfund->country           = $users->country;
            $adfund->status            = 0;
            $adfund->category          = $users->website_category;
            $adfund->payment_resource  = ($request->request_for == 'App') ? 'app' : 'web';
             if ($adfund->save()) {
             $pay = [
                    "merchantId" => "PHONEPEPGTESTUAT54",
                    "merchantTransactionId" => $txnid,
                    "merchantUserId" => $uid,
                    "amount" => ceil($amtinr * 100),
                    "redirectUrl" => "https://services.7searchppc.in/app_payment_response",
                    "redirectMode" => "POST",
                    "callbackUrl" => "https://services.7searchppc.in/app_payment_response",
                    "paymentInstrument" => [
                        "type" => "PAY_PAGE",
                    ],
                ];                 
                $bstr = base64_encode(json_encode($pay));
                $x_str = hash('sha256', $bstr . "/pg/v1/pay" . 'eb94b389-9708-439e-a03b-333941a6be6a') . '###' . '1';

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode([
                        'request' => $bstr,
                    ]),
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "X-VERIFY: " . $x_str,
                        "accept: application/json",
                    ],
                ]);

                $response = curl_exec($curl);
                // $err = curl_error($curl);
                // curl_close($curl);
                // $res = json_decode($response);
                // $redirect_url = $res->data->instrumentResponse->redirectInfo->url;
                // return Redirect::to($redirect_url);

                $return['code'] = 200;
                $return['data'] = $response;
                $return['message'] = 'Fund added in wallet successfully!';
            } else {
                $return['code'] = 101;
                $return['message'] = 'Something went wrong!';
            }
             return json_encode($return);
            /*
            $data['user'] =  $users;
            $data['txnids'] =  $txnid;
            $data['amounts'] =  $amtinr;
            $data['request_for'] = ($request->request_for == 'App') ? 'App' : 'Web';
            return view('payment.phonepe',$data);
           */
    }

    public function response(Request $request)
    {
        $status = $_POST['code'];
        $advertiser_url = config('app.advertiser_url');
        if ($status == 'PAYMENT_SUCCESS') {
            $trxid = $_POST['transactionId'];
            $payid = $_POST['providerReferenceId'];
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $statu = $transac->status;
            $cpncode = $transac->cpn_code;
            $cpnid = $transac->cpn_id;
            $cpnamt = $transac->cpn_amt;
            if ($statu == 0) {
                $uid = $transac['advertiser_code'];
                $user = User::where('uid', $uid)->first();
                $amounts = $transac['amount'];
                $transac->payment_id = $payid;
                $transac->status = 1;
                if ($transac->update()) {
                    $transaclog = new TransactionLog();
                    $transaclog->transaction_id = $trxid;
                    $transaclog->advertiser_code = $uid;
                    $transaclog->amount = $amounts;           
                    $transaclog->serial_no         = $user->account_type == 1 ? 0 : generate_serial();
                    $transaclog->pay_type = 'credit';
                    $transaclog->remark = 'Amount added to wallet succefully ! - Phonepe';
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
                        $paybleamt     = number_format($transac->payble_amt, 2, '.', '');
                        $remark        = $transac->remark;
                        $subjects      = "Fund Added Successfully";
                       if($user->country == 'INDIA'){
                          $gst           = number_format($transac->gst, 2, '.', '');
                        }else{
                          $gst           = number_format($transac->gst + $transac->fees_tax, 2, '.', '');
                        }
                         $report = Transaction::select('transactions.payble_amt', 'transactions.gst', 'transactions.fee', 'transactions.fees_tax', 'transaction_logs.remark', 'transactions.payment_mode', 'transactions.amount')
                        ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
                        ->where('transactions.transaction_id', $transactionid)
                        ->first();
                    // $report->gst = ($report->gst > 0) ? $report->gst + $report->fees_tax:'';
                    $subtotal = number_format($report->amount + $report->fee, 2, '.', '');
                     if($user->country == 'INDIA'){
                        $fee = number_format($report->fee, 2, '.', '');
                     }else{
                           if($report->payMode == 'now_payments' || $report->payMode == 'bitcoin' || $report->payMode == 'coinpay' || $report->payment_mode == 'stripe'){
                           if($report !== null && isset($report->fee)){
                            $fee = round($report->fee * 100) / 100;
                            $fee = number_format($fee, 2, '.', '');
                           } 
                        }else{
                            if($report !== null && isset($report->fee)){
                                $fee = round(($report->fee + $report->fees_tax) * 100) / 100;
                                $fee = number_format($fee, 2, '.', '');
                            } 
                        }
                     }
                       // paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark ,$subtotal);
                      	if ($cpn_res['code'] == 200) {
                            $transaclog = new TransactionLog();
                            $transaclog->transaction_id = $trxid;
                            $transaclog->advertiser_code = $uid;
                            $transaclog->amount = $cpn_res['bonus_amount'];
                            $transaclog->pay_type = 'credit';
                            $transaclog->cpn_typ = 1;
                            $transaclog->remark = 'Coupon Amount added to wallet succefully';
                            $transaclog->save();
                            $user = User::where('uid', $uid)->first();
                            $user->wallet = $user->wallet + $cpn_res['bonus_amount'];
                            $user->update();
                            updateAdvWallet($uid, $cpn_res['bonus_amount']);
                            /* Used Coupon Log Section Save */
                            $usedcoupon = new UsedCoupon();
                            $usedcoupon->advertiser_code = $uid;
                            $usedcoupon->coupon_code = $cpncode;
                            $usedcoupon->coupon_id = $cpnid;
                            $usedcoupon->save();
                        }
                        $email = $user['email'];
                        $amount = $transac['amount'];
                      	return Redirect::to("https://services.7searchppc.in/razorpay/success");

                    }
                } else {
                    return Redirect::to("https://services.7searchppc.in/razorpay/failed");
                }
            } else {
                return Redirect::to("https://services.7searchppc.in/razorpay/failed");
            }

        } elseif ($status == 'PAYMENT_PENDING') {
            $trxid = $_POST['transactionId'];
            $remark = 'Payment pending';
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark = $remark;
            $transac->status = 0;
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Phonepe|$email|Failed|$trxid|$amount|usd";
                $msg1 = base64_encode($msg);
                return Redirect::to("https://services.7searchppc.in/razorpay/failed");
            } else {
                return Redirect::to("https://services.7searchppc.in/razorpay/failed");
            }

        } else {
            return Redirect::to("https://services.7searchppc.in/razorpay/failed");

        }
    }

}

<?php
namespace App\Http\Controllers\Payment;
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
use Http;
use Session;

class PaymentTazapeController extends Controller
{
    public function payment_tazapay(Request $request)
    { //echo 'sdfa'; exit;
        $advertiser_url = config('app.advertiser_url');
        $minAmt = manageMinimumPayment();
        if ($request->amount < $minAmt) {
            //$return['code'] = 101;
            $return['Message'] = 'Minimum $'.$minAmt.' amount required';
            $return['Back to payment page'] = $advertiser_url . 'payment';
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
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))
            ->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
        //$getusersrecords = DB::table('users')->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        }
            // PaymentHoldUsers($uid);
            $amt                 = $request->payble;
            $ip                  = real_ip();
            $finalres            = ipaddressconrTaza($ip);
            $usdamt              = $finalres['data']['famt'];
            $amtinr              = $amt * $usdamt;
            $curcode             = $finalres['data']['currency'];
            $contcode            = $finalres['data']['country'];
            $adfund              = new Transaction();
            $txnid               = 'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'tazapay';
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
            //dd($adfund);
            if ($adfund->save()) {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://service-sandbox.tazapay.com/v3/checkout',
                    //CURLOPT_URL => 'https://service.tazapay.com/v3/checkout',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => '{
                        "invoice_currency": "USD",
                        "amount": "' . ceil($amtinr * 100) . '",
                        "success_url": "https://services.7searchppc.in/tazapay/response",
                        "cancel_url": "https://services.7searchppc.in/tazapay/response",
                        "customer_details":{
                        "name":"' . $users->full_name . '",
                        "email":"' . $users->email . '",
                        "country":"US"
                    },
                    "transaction_description":"7Searchppc Payment",
                    "payment_methods":[]
                    
                    }',
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                        'Authorization: Basic YWtfdGVzdF80T0ZNWFhNUUU3OTlOOUtIQ0ZEUzpza190ZXN0X0pMMFZIcDd6d1JLSGdvTVpMcXpHNTl5bVdab25pYlhiT0E3a213RUs1WlhPRml4dVZ2M0MwUVhhNTJneEFZVG5hdkVSTTZZeVNMRmRlTFNDbUlyMENHWEJyRU83SDU2T0E3dWVTOWhNeFo1ZERWR1NtZUFISFBoMlRObzJvUncx'
                    ),
                    // CURLOPT_HTTPHEADER => array(
                    //     'Content-Type: application/json',
                    //     'Authorization: Basic YWtfU1FLU0ZPM1ZZMThCUzhFTlI1VTU6c2tfSjRGb3h2dTdxWlhacGhwQ3psdEg1Q1NPYmdXMmZGd3FrNXdrMTNlTTFOdTFxUmszUHRIeTA2RHZiQ1lBN3Bnc0hBTlA2cDRBS083NXhGMUFqeUR3ckdsZ1JrcXI2SXFnb1NWQkNySjRxbGhadXhiRzAyTlBHaHk3QUpMTGJacTI='
                    // ),
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                $res = json_decode($response);
                Session::put('check_id', $res->data->id);
                Session::put('txn_id', $txnid);
                $redirect_url = $res->data->url;
                header('location:' . $redirect_url);
                $return['code'] = 200;
                $return['message'] = 'Fund added in wallet successfully!';
            } else {
                $return['code'] = 101;
                $return['message'] = 'Something went wrong!';
            }
    }
    public function response(Request $request)
    {
        $id = Session::get('check_id');
        $txn = Session::get('txn_id');
        $curl = curl_init();

        curl_setopt_array($curl, array(
             CURLOPT_URL => 'https://service-sandbox.tazapay.com/v3/checkout/'.$id,
            //CURLOPT_URL => 'https://service.tazapay.com/v3/checkout/'.$id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic YWtfdGVzdF80T0ZNWFhNUUU3OTlOOUtIQ0ZEUzpza190ZXN0X0pMMFZIcDd6d1JLSGdvTVpMcXpHNTl5bVdab25pYlhiT0E3a213RUs1WlhPRml4dVZ2M0MwUVhhNTJneEFZVG5hdkVSTTZZeVNMRmRlTFNDbUlyMENHWEJyRU83SDU2T0E3dWVTOWhNeFo1ZERWR1NtZUFISFBoMlRObzJvUncx'
            ),
            // CURLOPT_HTTPHEADER => array(
            //     'Authorization: Basic YWtfU1FLU0ZPM1ZZMThCUzhFTlI1VTU6c2tfSjRGb3h2dTdxWlhacGhwQ3psdEg1Q1NPYmdXMmZGd3FrNXdrMTNlTTFOdTFxUmszUHRIeTA2RHZiQ1lBN3Bnc0hBTlA2cDRBS083NXhGMUFqeUR3ckdsZ1JrcXI2SXFnb1NWQkNySjRxbGhadXhiRzAyTlBHaHk3QUpMTGJacTI='
            // ),
        ));
        

        $response = curl_exec($curl);

        curl_close($curl);

        $res = json_decode($response);


        $status = $res->data->payment_status;
        $advertiser_url = config('app.advertiser_url');
        if ($status == 'paid') {
            $trxid = $txn;
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $statu = $transac->status;
            $cpncode = $transac->cpn_code;
            $cpnid = $transac->cpn_id;
            $cpnamt = $transac->cpn_amt;
            if ($statu == 0) {
                $uid = $transac['advertiser_code'];
                $user = User::where('uid', $uid)->first();
                $amounts = $transac['amount'];
                if ($user->referal_code != "" && $user->referalpmt_status == 0) {
                    $url = "http://refprogramserv.7searchppc.in/api/add-transaction";
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
                $transac->status = 1;
                $transac->payment_id = $id;
                if ($transac->update()) {
                    $transaclog = new TransactionLog();
                    $transaclog->transaction_id = $trxid;
                    $transaclog->advertiser_code = $uid;
                    $transaclog->amount = $amounts;
                    $transaclog->serial_no         = $user->account_type == 1 ? 0 : generate_serial();
                    // $transaclog->serial_no = generate_serial();
                    $transaclog->pay_type = 'credit';
                    $transaclog->remark = 'Amount added to wallet succefully ! - Tazapay';
                    if ($transaclog->save()) {
                        $user->wallet = $user->wallet + $amounts;
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
                        //$cpn_res = getCouponCal($uid, $cpncode, $cpnamt, $cpnid);
                        $cpn_res = getCouponCal($uid, $cpncode, $amounts, $cpnid);
                        $getusersrecords = DB::table('users')->where('uid', $uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
                        $fullname      = $getusersrecords->first_name . ' ' . $getusersrecords->last_name;
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
                        $paybleamt     = $transac->amount + $transac->fee + $transac->gst;
                        $fee           = $transac->fee - $transac->fees_tax;
                        $gst           = $transac->gst + $transac->fees_tax;
                        $remark        = $transac->remark;
                        $subjects      = "Fund Added Successfully";
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
                      paymentSuccessMail($subjects, $fullname, $emailname, $phone, $addressline1, $addressline2, $city, $state, $country, $createdat, $useridas, $transactionid, $paymentmode, $amount, $paybleamt, $fee, $gst, $remark ,$subtotal);
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
                            /* Used Coupon Log Section Save  */
                        }
                        /* Coupon Section  Transaction Log Save  */
                        $email = $user['email'];
                        $amount = $transac['amount'];
                        $msg = "Tazapay|$email|Success|$txn|$amount|usd";
                        $msg1 = base64_encode($msg);
                        return Redirect::to($advertiser_url . 'payment/success/' . $msg1);
                    }
                } else {
                    return Redirect::to($advertiser_url . 'payment');
                }
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }
        } elseif ($status == 'unpaid') {
            $trxid = $txn;
            $remark = 'Payment pending';
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark = $remark;
            $transac->payment_id = $id;
            $transac->status = 0;
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Tazapay|$email|Pending|$txn|$amount|usd";
                $msg1 = base64_encode($msg);
                return Redirect::to($advertiser_url . 'payment/failed/' . $msg1 . '');
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }
        } else {
            return Redirect::to($advertiser_url . 'payment');
        }
    }
}


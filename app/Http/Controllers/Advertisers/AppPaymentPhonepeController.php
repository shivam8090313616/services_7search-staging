<?php

namespace App\Http\Controllers\Advertisers;

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

class AppPaymentPhonepeController extends Controller
{
    public function paymentPhonepe(Request $request)
    {
        $minAmt = manageMinimumPayment();
        if($request->amount < $minAmt){
            $return['code'] = 101;
            $return['msg'] = 'Minimum $'.$minAmt.' required';
            return response()->json($return);
        }
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
        $uid = $request->input('uid');
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category,address_line1,address_line2,city,state,post_code,country"))->where('uid', $uid)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        } else {
            $amt = $request->payble;

            $ip = real_ip();
            $finalres = ipaddressconr($ip);
            $usdamt = $finalres['data']['famt'];
            $amtinr = $amt * $usdamt;
            $curcode = $finalres['data']['currency'];

            $adfund = new Transaction();
            $txnid = 'TXN' . strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id = $txnid;
            $adfund->payment_mode = 'phonepe';
            $adfund->amount = $request->amount;
            $adfund->payble_amt = $amt;
            $adfund->fee = $request->fee;
            $adfund->fees_tax = $request->fee_tax;
            $adfund->gst = $request->gst;
            $adfund->gst_no = $request->gst_no;
            $adfund->cpn_amt = $request->cpn_amt;
            $adfund->cpn_code = $request->cpn_code;
            $adfund->cpn_id = $request->cpn_id;
            $adfund->address = $users->address_line1 . ' ' . $users->address_line2;
            $adfund->city = $users->city;
            $adfund->state = $users->state;
            $adfund->post_code = $users->post_code;
            $adfund->country = $users->country;
            $adfund->status = 0;
            $adfund->category = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            if ($adfund->save()) {
                /*$pay = [
                "merchantId" => "PGTESTPAYUAT88",
                "transactionId" => $txnid,
                "merchantUserId"=> $uid,
                "amount"=> ($amtinr * 100),
                "subMerchant"=>"7SearchPPC",
                ];*/
                $pay = [
                    "merchantId" => "M1O5MHA7D7NP",
                    "merchantTransactionId" => $txnid,
                    "merchantUserId" => $uid,
                    "amount" => ceil($amtinr * 100),
                    "redirectUrl" => "https://services.7searchppc.com/phonepe/response",
                    "redirectMode" => "POST",
                    "callbackUrl" => "https://services.7searchppc.com/phonepe/response",
                    "paymentInstrument" => [
                        "type" => "PAY_PAGE",
                    ],
                ];

                $bstr = base64_encode(json_encode($pay));
                $x_str = hash('sha256', $bstr . "/pg/v1/pay" . '28726f89-dd86-4d30-9b89-5c43e18932b9') . '###1';

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api.phonepe.com/apis/hermes/pg/v1/pay",
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
                $err = curl_error($curl);

                curl_close($curl);

                $res = json_decode($response);
              	//print_r($response); exit;
                $redirect_url = $res->data->instrumentResponse->redirectInfo->url;
                header('location:' . $redirect_url);
                /*if ($err) {
                echo "cURL Error #:" . $err;
                } else {
                echo $response;
                }*/

                $return['code'] = 200;
                $return['msg'] = 'Fund added in wallet successfully!';
            } else {
                $return['code'] = 101;
                $return['msg'] = 'Something went wrong!';
            }

            /*
            $data['user'] =  $users;
            $data['txnids'] =  $txnid;
            $data['amounts'] =  $amtinr;
            $data['request_for'] = ($request->request_for == 'App') ? 'App' : 'Web';
            return view('payment.phonepe',$data);
           */
        }
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
                    $transaclog->pay_type = 'credit';
                    $transaclog->serial_no  = $user->account_type == 1 ? 0 : generate_serial();
                    $transaclog->remark = 'Amount added to wallet succefully ! - Phonepe';
                    if ($transaclog->save()) {
                        $user->wallet = $user->wallet + $amounts;
                        $user->update();
                        //$cpn_res = getCouponCal($uid, $cpncode, $cpnamt, $cpnid);
                        $cpn_res = getCouponCal($uid, $cpncode, $amounts, $cpnid);
                      //print_r($cpn_res); exit;
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
                        /* Mail section */
                        /*$fullname = "$user->first_name $user->last_name";
                        $useridas = $user->uid;
                        $transactionid = $transaclog->transaction_id;
                        $paymentmode = $transac->payment_mode;
                        $amount = $transac->amount;
                        $paybleamt = $transac->payble_amt;
                        $fee = $transac->fee;
                        $gst = $transac->gst;
                        $remark = $transac->remark;
                        $subjects = "Fund Added Successfully";
                        $mailsentdetals = ['subject' => $subjects, 'full_name' => $fullname, 'user_id' => $useridas, 'transaction_id' => $transactionid,
                            'payment_mode' => $paymentmode, 'amount' => $amount, 'payble_amt' => $paybleamt, 'fee' => $fee, 'gst' => $gst, 'remark' => $remark];
                        $mailTo = [$email];
                        /* Mail Section */
                        /*Mail::to($mailTo)->send(new TransactionMail($mailsentdetals));*/
                        $msg = "Phonepe|$email|Success|$payid|$amount|usd";
                        $msg1 = base64_encode($msg);
                      	return Redirect::to($advertiser_url . 'payment/success/' . $msg1);
                    }
                } else {
                    return Redirect::to($advertiser_url . 'payment');
                }
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }

        } elseif ($status == 'PAYMENT_PENDING') {
            $trxid = $_POST['transactionId'];
            $payid = $_POST['providerReferenceId'];
            $remark = 'Payment pending';
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark = $remark;
            $transac->payment_id = $payid;
            $transac->status = 0;
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Phonepe|$email|Failed|$payid|$amount|usd";
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

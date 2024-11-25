<?php

namespace App\Http\Controllers\Advertisers;

use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class AppPaymentAirpayController extends Controller
{
    
    public function paymentAirpay(Request $request)
    {
         $advertiser_url = config('app.advertiser_url');
         $minAmt = manageMinimumPayment();
         if($request->amount < $minAmt){
            $return ['msg'] = 'Minimum $'.$minAmt.' amount required';
            $return ['Back to payment page'] = $advertiser_url . 'payment';
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
        // $uid = 'AD641D821066B5A';
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, first_name, last_name, email, phone, uid, website_category"))
                ->where('uid',$uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)->first();
        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        }
        else
        {
            $amt = $request->payble;
            // $amt = 500;
            $ip = real_ip();
            $finalres =  ipaddressconrAirpay($ip);
            $usdamt =  $finalres['data']['famt'];
            $amtinr = $amt * $usdamt;
            $curcode =  $finalres['data']['currency'];
            $curnumcode =  $finalres['data']['numcode'];
            $curcountry =  $finalres['data']['nicename'];
            $adfund                  = new Transaction();
            $txnid                   = 'TXN'.strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'airpay';
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
                $return ['code']        = 200;
                $return ['msg']     = 'Fund added in wallet successfully!';
            }
            else
            {
                $return ['code']    = 101;
                $return ['msg'] = 'Something went wrong!';
            }
            $username =  'bc2T8RKhCd'; // Username
            $password =  'cAP4qkwe'; // Password
            $secret =    'Rpbm3MNkK3j7atzK'; // API key
            $mercid = '292774'; //Merchant ID
            $data['buyerEmail'] = $users->email;
        	$data['buyerFirstName'] = $users->first_name;
        	$data['buyerLastName'] = $users->last_name;
        	$data['amount'] = number_format($amtinr, 2, '.', '');
        	$data['buyerCountry'] = $curcountry;
        	$data['orderid'] = $txnid; //Your System Generated Order ID
        	$data['currency'] = $curnumcode;
        	$data['isocurrency'] = $curcode;
        	$data['alldata']   =  $data['buyerEmail'].	$data['buyerFirstName'].$data['buyerLastName'].$data['buyerCountry'].$data['amount'].$data['orderid'];
        	$data['privatekey'] = self::encrypt($username.":|:".$password, $secret);
            $data['keySha256'] = self::encryptSha256($username."~:~".$password);
            $data['checksum'] = self::calculateChecksumSha256($data['alldata'].date('Y-m-d'),$data['keySha256']);
            $data['hiddenmod'] = "";
            //dd($data);
            return view('payment/airpay',$data);
        }
    }
    public function response(Request $request)
    {
        $status = $_POST['TRANSACTIONPAYMENTSTATUS'];
        $advertiser_url = config('app.advertiser_url');
        if ($status == 'SUCCESS') {
            $trxid = $_POST['TRANSACTIONID'];
            $payid = $_POST['APTRANSACTIONID'];
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
                    $transaclog->remark = 'Amount added to wallet succefully ! - Airpay';
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
                        $msg = "Airpay|$email|Success|$payid|$amount|usd";
                        $msg1 = base64_encode($msg);
                        return Redirect::to($advertiser_url . 'payment/success/' . $msg1);

                    }
                } else {
                    return Redirect::to($advertiser_url . 'payment');
                }
            } else {
                return Redirect::to($advertiser_url . 'payment');
            }

        } elseif ($status == 'PENDING') {
            $trxid = $_POST['TRANSACTIONID'];
            $payid = $_POST['APTRANSACTIONID'];
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
                $msg = "Airpay|$email|Pending|$payid|$amount|usd";
                $msg1 = base64_encode($msg);
                return Redirect::to($advertiser_url . 'payment/pending/' . $msg1 . '');

            } else {
                return Redirect::to($advertiser_url . 'payment');
            }

        } elseif ($status == 'FAIL') {
            $trxid = $_POST['TRANSACTIONID'];
            $payid = $_POST['APTRANSACTIONID'];
            $remark = 'Payment failed';
            $transac = Transaction::where('transaction_id', $trxid)->first();
            $transac->remark = $remark;
            $transac->payment_id = $payid;
            $transac->status = 2;
            if ($transac->update()) {
                $uidds = $transac['advertiser_code'];
                $usersdeatils = User::where('uid', $uidds)->first();
                $email = $usersdeatils['email'];
                $amount = $transac['amount'];
                $msg = "Airpay|$email|Fail|$payid|$amount|usd";
                $msg1 = base64_encode($msg);
                return Redirect::to($advertiser_url . 'payment/failed/' . $msg1 . '');

            } else {
                return Redirect::to($advertiser_url . 'payment');
            }

        } else {
            return Redirect::to($advertiser_url . 'payment');

        }
    }
    
    
    
    
    
     public function calculateChecksum($data, $secret_key) {
		$checksum = md5($data.$secret_key);
		return $checksum;
	}

    public function encrypt($data, $salt) {
        // Build a 256-bit $key which is a SHA256 hash of $salt and $password.
        $key = hash('SHA256', $salt.'@'.$data);
        return $key;
    }	

	public function encryptSha256($data) {    
        $key = hash('SHA256', $data);
        return $key;
    }    
    public function calculateChecksumSha256($data, $salt) { 
		// print($data);
		// exit;
        $checksum = hash('SHA256', $salt.'@'.$data);
        return $checksum;
    }
    public function outputForm($checksum ,$post) {
		//ksort($_POST);
		foreach($post as $key => $value) {
				echo '<input type="hidden" name="'.$key.'" value="'.$value.'" />'."\n";
		}
		echo '<input type="hidden" name="checksum" value="'.$checksum.'" />'."\n";
	}

    public function verifyChecksum($checksum, $all, $secret) {
		$cal_checksum = Checksum::calculateChecksum($secret, $all);
		$bool = 0;
		if($checksum == $cal_checksum)	{
			$bool = 1;
		}

		return $bool;
	}

}

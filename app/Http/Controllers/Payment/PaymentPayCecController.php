<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\UsedCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

class PaymentPayCecController extends Controller
{
    public function payment_paycec_token(Request $request)
    {
        $advertiser_url = config('app.advertiser_url');
        if ($request->amount < 50) {
            $return['Message'] = 'Minimum $50 amount required';
            $return['Back to payment page'] = $advertiser_url . 'payment';
            return response()->json($return);
        }

        $merchantName = 'wvd_logeliteps2ne4i5ol4zde2j0mdm';
        $merchantSecretKey = 'CGtIrhU_yufRulvbgZ7MxH3Y4glwkoJhoWWFkkN6zS|MHgpPphUaaLKHIurAByrhner|ZVemAQM|_3W1qUgPrcB2I^vXXXj61B-Pe-o1NvPPZeT1-bwmMv-y8YzM0t30';
         $endpoint = 'https://securetest.paycec.com/redirect-service/request-token'; //For Test
        //$endpoint = 'https://secure.paycec.com/redirect-service/request-token'; //For Live
        $txnid = 'TXN' . strtoupper(uniqid());
        $merchantReferenceCode = 'REF' . strtoupper(rand());

        $randomString = bin2hex(random_bytes(4));
        $unixTimestamp = time();
        $uniqueString = $randomString . "_" . $unixTimestamp;
        
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

        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'Not found User ';
            return response()->json($return);
        } 
        //   PaymentHoldUsers($uid);
            $email = $users->email;

            // create params for post request
            $params = [
                'merchantName' => $merchantName,
                'merchantSecretKey' => $merchantSecretKey,
                'merchantReferenceCode' => $merchantReferenceCode,
                'merchantToken' => $uniqueString,
                'amount' => $request->payble,
                'currencyCode' => 'USD',
                 'returnUrl' => 'https://services.7searchppc.com/api/paycec/payment/success?uid='.$uid.'&txnid='.$txnid.'&email='.$email.'&amount='.$request->payble, // For Live
                 'cancelUrl' => 'https://services.7searchppc.com/api/paycec/payment/failed?uid='.$uid.'&txnid='.$txnid.'&email='.$email.'&amount='.$request->payble, // For Live
                //'returnUrl' => 'http://127.0.0.1:8000/api/paycec/payment/success?uid='.$uid.'&txnid='.$txnid.'&email='.$email.'&amount='.$request->payble, // For Test
                //'cancelUrl' => 'http://127.0.0.1:8000/api/paycec/payment/failed?uid='.$uid.'&txnid='.$txnid.'&email='.$email.'&amount='.$request->payble, // For Test
            ];    

            // create sign for post request
            ksort($params);
            $sigString = $endpoint.'?'.http_build_query($params);
            $params['sig'] = hash_hmac('sha512', $sigString, $merchantSecretKey, false); 

            $adfund                  = new Transaction();
            $adfund->advertiser_code = $uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'paycec';
            $adfund->amount          = $request->amount;
            $adfund->payble_amt      = $request->payble;
            $adfund->fee             = $request->fee;
            $adfund->fees_tax        = $request->fee_tax;
            $adfund->gst             = $request->gst;
            $adfund->cpn_amt         = $request->cpn_amt;
            $adfund->cpn_code        = $request->cpn_code;
            $adfund->cpn_id          = $request->cpn_id;
            $adfund->status          = 0; // pending status
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';

            if ($adfund->save()) {
                try {
                    $client = new Client();
                    $response = $client->post($endpoint, [
                        'form_params' => $params,
                    ]);
                
                    if ($response->getBody()) {
                        $data = json_decode($response->getBody(), true);
                        return response()->json($data);
                    }
                } catch (\Exception $e) {
                    return response()->json(['error' => $e->getMessage()], 500);
                }
            }

            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
            return response()->json($return);
    }


    // Success response for paycec payment
    public function paycec_success_response(Request $request)
    {
        $merchantName = 'wvd_logeliteps2ne4i5ol4zde2j0mdm';
        $merchantSecretKey = 'CGtIrhU_yufRulvbgZ7MxH3Y4glwkoJhoWWFkkN6zS|MHgpPphUaaLKHIurAByrhner|ZVemAQM|_3W1qUgPrcB2I^vXXXj61B-Pe-o1NvPPZeT1-bwmMv-y8YzM0t30';

        // Get the payment status
       // $endpoint = 'https://secure.paycec.com/redirect-service/purchase-details'; // For Live
        $endpoint = 'https://securetest.paycec.com/redirect-service/purchase-details'; // For Test

        // Create params for post request
        $params = [
            'merchantName' => $merchantName,
            'merchantSecretKey' => $merchantSecretKey,
            'token' => $request->token
        ];    

        // Create sign for post request
        ksort($params);
        $sigString = $endpoint.'?'.http_build_query($params);
        $params['sig'] = hash_hmac('sha512', $sigString, $merchantSecretKey, false); 

        $successMsg = "PayCec|$request->email|Success|$request->txnid|$request->amount|usd";
        $urlSuccess = base64_encode($successMsg); 
        $failedMsg = "PayCec|$request->email|Failed|$request->txnid|$request->amount|usd";
        $urlFailed = base64_encode($failedMsg);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            $response = curl_exec($ch);

            $response = json_decode($response, true);
            
            if (isset($response['transactionId'])) {
                $adfund                  = Transaction::where('advertiser_code', $request->uid)->where('transaction_id', $request->txnid)->first(); // Update payment status
                $adfund->advertiser_code = $request->uid;
                $adfund->transaction_id  = $request->txnid; //$response['transactionId'];
                $adfund->status          = 1; //payment status success
                
                if ($adfund->save()) {
                    $transaclog = new TransactionLog();
                    $transaclog->transaction_id = $request->txnid;
                    $transaclog->advertiser_code = $request->uid;
                    $transaclog->amount = $response['totalPurchases']['USD'];
                    $transaclog->pay_type = 'PayCec';
                    $transaclog->serial_no = generate_serial();
                    $transaclog->remark = 'Amount added to wallet succefully ! - PayCec';

                    if ($transaclog->save()) {
                        $url = 'https://advertiser.7searchppc.com/payment/success/'.$urlSuccess;
                        return Redirect::away($url);
                    }
                }
            } else {
                $url = 'https://advertiser.7searchppc.com/payment/failed/'.$urlFailed;
                return Redirect::away($url);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    // Failed response for paycec payment
    public function paycec_failed_response(Request $request)
    {
        $failedMsg = "PayCec|$request->email|Failed|$request->txnid|$request->amount|usd";
        $urlFailed = base64_encode($failedMsg); 
        
        $adfund = Transaction::where('advertiser_code', $request->uid)->where('transaction_id', $request->txnid)->first(); // Update payment status
        $adfund->status = 2; //payment status failed
        if ($adfund->save()) {
            $url = 'https://advertiser.7searchppc.com/payment/failed/'.$urlFailed;
            return Redirect::away($url);
        }
    }
}

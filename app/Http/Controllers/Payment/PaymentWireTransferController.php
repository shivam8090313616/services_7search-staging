<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentWireTransferController extends Controller
{
    public function payment_wiretransfer(Request $request)
    {
        $advertiser_url = config('app.advertiser_url');
        $min_wire = DB::table("panel_customizations")->select("wiretransfer_minamt")->first();
        $minWireAmt = $min_wire->wiretransfer_minamt;
        if ($request->amount < $minWireAmt) {
            $return ['Message'] = 'Minimum '.$minWireAmt.' amount required';
            $return ['Back to payment page'] = $advertiser_url . 'payment';
            return response()->json($return);
        }

        $validator = Validator::make(
            $request->all(), [
                'amount' => "required",
                'address_line1' => "required",
                'city' => "required",
                'country' => "required|exists:countries,name",
                'email' => "required",
                'phone' => "required|min:4|max:15",
                'pin' => "required",
                'state' => "required",
                'pfee' => "required",
                'uid' => "required|exists:users,uid",
                'type' => "required|in:1,2",
                'bank_id' => 'required|exists:admin_bank_details,bank_id'
            ]
        );
        
        $validator->sometimes('name', 'required', function($input) {
            return $input->type == 1;
        });

        $validator->sometimes(['legal_entity_name', 'contact_person_name'], 'required', function($input) {
            return $input->type == 2;
        });

        if ($validator->fails()) {
            $return['code'] = 100;
            $return['msg'] = 'error';
            $return['err'] = $validator->errors();
            return response()->json($return);
        }
        $uid = $request->input('uid');
        
        $users = DB::table('users')->select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) AS full_name, email, phone, uid, website_category"))
        ->where('uid',$uid)->where('status', 0)->where('trash', 0)->where('ac_verified', 1)
        ->first();

        if (empty($users)) {
            $return['code'] = 101;
            $return['msg'] = 'User Not found';
            return response()->json($return);
        }   
            // PaymentHoldUsers($uid);
            $amount = $request->amount;
            $adfund                  = new Transaction();
            $txnid                   = 'TXN'.strtoupper(uniqid());
            $adfund->advertiser_code = $users->uid;
            $adfund->transaction_id  = $txnid;
            $adfund->payment_mode    = 'wiretransfer';
            $adfund->amount          = $amount;
            $adfund->payble_amt      = $amount;
            $adfund->fee             = $request->pfee;
          	$adfund->fees_tax        = $request->fee_tax;
          	$adfund->gst             = $request->gst;
          	$adfund->gst_no          = $request->gst_no;
            $adfund->email           = $request->email;
            $adfund->address         = $request->address_line1;
            $adfund->city            = $request->city;
            $adfund->state           = $request->state;
            $adfund->country         = $request->country;
            $adfund->post_code       = $request->pin;
            $adfund->phone           = $request->phone;
            $adfund->bank_id         = $request->bank_id;
            $adfund->status          = 0;
            $adfund->category        = $users->website_category;
            $adfund->payment_resource = ($request->request_for == 'App') ? 'app' : 'web';
            $subjects                = "Wire Transfer Transaction Request Received";
            
            // TYPE: [Individual => 1, Legal => 2]
            if ($request->type == 1) {
                $adfund->name = $request->name;
            } else {                
                $adfund->legal_entity = $request->legal_entity_name;
                $adfund->name = $request->contact_person_name;
            }
            
            if ($adfund->save()) {
                paymentSuccessMail(
                    $subjects,
                    $users->full_name, 
                    $emailname = $request->email, 
                    $request->phone, 
                    $request->address_line1,
                    "", 
                    $request->city, 
                    $request->state, 
                    $request->country, 
                    $createdat = date("Y/m/d"), 
                    $users->uid, 
                    $adfund->transaction_id, 
                    $adfund->payment_mode, 
                    $amount, 
                    $amount, 
                    $adfund->fee, 
                    $adfund->gst, 
                    "",
                    $amount,
                    $request->gst_no,
                    $request->bank_id,
                    $request->pin,
                    $adfund->legal_entity,
                    $adfund->name
                );
                $return ['code']          = 200;
                $return ['transaction_id'] = $txnid;
                $return ['message']       = 'Fund added in wallet successfully!';
            } else {
                $return ['code']    = 101;
                $return ['message'] = 'Something went wrong!';
            }

            return response()->json($return); 
        }
   }

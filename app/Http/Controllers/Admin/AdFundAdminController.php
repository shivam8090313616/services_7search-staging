<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Mail\CreateAdFundMail;
use Exception;
use Illuminate\Support\Facades\Mail;
use PDF;
use PHPMailer\PHPMailer\PHPMailer;

class AdFundAdminController extends Controller
{
  public function adFund(Request $req)
  {
    $uid = $req->advertiser_code;
    $payment_mode = $req->payment_mode;
    $amt = $req->amount;
    $validator = Validator::make($req->all(), [
      'advertiser_code'   => 'required',
      'payment_mode'      => 'required',
      'remark'            => 'required',
      'amount'            => 'required|numeric|min:1',
      'fee' => $payment_mode == 'wiretransfer' ? ['required', 'numeric', 'max:' . ($amt - 1)] : '',
      'taxAmount' => !empty($req->taxAmount) && $payment_mode == 'wiretransfer' ?   ['numeric', 'max:' . ($amt - 1)] : '',
      'pay_type'          => 'required',
    ]);
    if ($validator->fails()) {
      $return['code'] = 100;
      $return['error'] = $validator->errors();
      $return['message'] = 'Valitation error!';
      return json_encode($return);
    }
    $udata = User::where('uid', $uid)->first();
    $usercon = User::select('country')->where('uid', $uid)->first();
    if (empty($udata)) {
      $return['code'] = 100;
      $return['message'] = 'User Not Found!';
      return json_encode($return);
    } else { 
        //  if ($payment_mode == 'stripe') {
        //   if ($usercon->country == 'INDIA') {
        //     $pct = 3;
        //     $fee            = ($pct * $amt) / 100;
        //     $fee_gst        = 0; //(18 * $fee) / 100;
        //     $gst_fee        = (($fee + $amt) * 18) / 100;
        //     $processing_fee = $fee;
        //     $totalpay       = $amt + $processing_fee + $gst_fee;
        //   } else {
        //     $pct = 4.3;
        //     $fee            = $amt * $pct / 100; 
        //     $gst_fee        = 0;
        //     $fee_gst        = ((($amt * $pct / 100) * 18) / 100);
        //     $processing_fee = $fee + $fee_gst;
        //     $totalpay       = $amt + $processing_fee + $gst_fee;
        //   }
        // }
        if ($payment_mode == 'stripe') {
          if ($usercon->country == 'INDIA') {
            $pct = 3;
            $fee            = ($pct * $amt) / 100;
            $fee_gst        = 0; //(18 * $fee) / 100;
            $gst_fee        = (($fee + $amt) * 18) / 100;
            $processing_fee = $fee;
            $totalpay       = $amt + $processing_fee + $gst_fee;
          } else {
            $pct = 4.3;
            $fee            = $amt * $pct / 100; 
            $gst_fee        = 0;
            $fee_gst        = 0; //((($amt * $pct / 100) * 18) / 100);
            $processing_fee = $fee + ((($amt * $pct / 100) * 18) / 100);
            $totalpay       = $amt + $processing_fee + $gst_fee;
          }
        }
      if ($payment_mode == 'bitcoin') {
        $fee            = (0.5 * $amt) / 100;
        if ($usercon->country == 'INDIA') {
          //$fee_gst        = (18 * $fee) / 100;
          $fee_gst        = $fee;
          $gst_fee        = (($fee + $amt) * 18) / 100;
          $processing_fee = $fee_gst;
        } else {
          $gst_fee        = 0;
          $fee_gst        = 0;
          $processing_fee = $fee_gst + $fee;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }
      if($payment_mode == 'coinpay') {
        $fee            = (0.5 * $amt) / 100;
        if ($usercon->country == 'INDIA') {
          //$fee_gst        = (18 * $fee) / 100;
          $fee_gst        = $fee;
          $gst_fee        = (($fee + $amt) * 18) / 100;
          $processing_fee = $fee_gst;
        } else {
          $gst_fee        = 0;
          $fee_gst        = 0; // Tax
          $processing_fee = $fee_gst + $fee;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }
      if ($payment_mode == 'paycec') {
        $fee            = (2 * $amt) / 100;
        if ($usercon->country == 'INDIA') {
          $fee_gst        = (18 * $fee) / 100;
          $gst_fee        = (18 * $amt) / 100;
        } else {
          $gst_fee        = 0;
          $fee_gst        = 0;
        }
        $processing_fee = $fee + $fee_gst;
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }
     

      if ($payment_mode == 'now_payments') {
        $fee            = (0.5 * $amt) / 100;
        if ($usercon->country == 'INDIA') {
          //$fee_gst        = (18 * $fee) / 100;
          $fee_gst        = $fee;
          $gst_fee        = (($fee + $amt) * 18) / 100;
          $processing_fee = $fee_gst;
        } else {
          $gst_fee        = 0;
          $fee_gst        = 0;
          $processing_fee = $fee_gst + $fee;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

      if ($payment_mode == 'bonus') {
        $fee            = 0;
        $fee_gst        = 0;
        $processing_fee = $fee + $fee_gst;
        $gst_fee        = 0;
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }
      if ($payment_mode == 'coupon') {
        $fee            = 0;
        $fee_gst        = 0;
        $processing_fee = $fee + $fee_gst;
        $gst_fee        = 0;
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

     if ($payment_mode == 'payu') {
        $fee            = (2 * $amt) / 100;
        $fee_gst        = 0; //(18 * $fee) / 100;
        $processing_fee = $fee + $fee_gst;
        if ($usercon->country == 'INDIA') {
          $gst_fee        = (18 * ($amt + $fee)) / 100;
        } else {
          $gst_fee        = 0;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

    //   if ($payment_mode == 'razorpay') {
    //     $fee            = (2 * $amt) / 100;
    //     $fee_gst        = (18 * $fee) / 100;
    //     $processing_fee = $fee + $fee_gst;
    //     if ($usercon->country == 'INDIA') {
    //       $gst_fee        = (18 * $amt) / 100;
    //     } else {
    //       $gst_fee        = 0;
    //     }
    //     $totalpay       = $amt + $processing_fee + $gst_fee;
    //   }

     if ($payment_mode == 'airpay') {
        $fee            = (2 * $amt) / 100;
        $fee_gst        = 0; //(18 * $fee) / 100;
        $processing_fee = $fee + $fee_gst;
        if ($usercon->country == 'INDIA') {
          $gst_fee        = (18 * ($amt + $fee)) / 100;
        } else {
          $gst_fee        = 0;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

    if ($payment_mode == 'phone_pe') {
        $fee            = (2 * $amt) / 100;
        $fee_gst        = 0; //(18 * $fee) / 100;
        $processing_fee = $fee + $fee_gst;
        if ($usercon->country == 'INDIA') {
          $gst_fee        = (18 * ($amt + $fee)) / 100;
        } else {
          $gst_fee        = 0;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

      if ($payment_mode == 'tazapay') {
        $fee            = ((4.7 * $amt) / 100) + 0.50;
        if ($usercon->country == 'INDIA') {
          //$fee_gst        = (18 * $fee) / 100;
          $fee_gst        = ($fee * 18) / 100;
          $gst_fee        = (($fee + $amt) * 18) / 100;
          $processing_fee = $fee;
        } else {
          $gst_fee        = 0;
          $fee_gst        = 0;
          $processing_fee = $fee + $fee_gst;
        }
        $totalpay       = $amt + $processing_fee + $gst_fee;
      }

      if ($payment_mode == 'wiretransfer') {
        $transaction = User::select("country")->where('uid', $req->advertiser_code)->first();
        $transactionCountry = $transaction->country;
        // dd($transactionCountry);
        $taxAmount=$transactionCountry=="INDIA" ? $req->taxAmount : 0;
        $fee            = $req->fee;
        $gst_fee        = $taxAmount;
        $fee_gst        = 0;
        $processing_fee = $fee;
        $totalpay       = $amt - ($processing_fee + $taxAmount);
      }
      if($amt < ($req->fee + $req->taxAmount) && $payment_mode == 'wiretransfer' && $transactionCountry == 'INDIA'){
        return json_encode([
            'code'=> 100,
            'message'=> "The taxable amount (tax and fee) should be less than the payable amount."
            ]);
      }
      $userdata = User::select('website_category', 'email', 'first_name', 'last_name', 'uid', 'account_type', 'phone', 'address_line1', 'city', 'state', 'country')->where('uid', $uid)->first();
      $adfund                     = new Transaction();
      $txnid                      = 'TXN' . strtoupper(uniqid());
      $adfund->advertiser_code    = $req->advertiser_code;
      $adfund->payment_id         = 0;
      $adfund->payment_mode       = $req->payment_mode;
      $adfund->amount             = $payment_mode == 'wiretransfer' ? $totalpay : $req->amount;
      $adfund->gst                = $gst_fee;
      $adfund->fee                = $processing_fee;
      $adfund->fees_tax           = $fee_gst;
      $adfund->payble_amt         = $payment_mode == 'wiretransfer' ? $req->amount : $totalpay;
      $adfund->status             = 1;
      $adfund->transaction_id     = $txnid;
      $adfund->remark             = $req->remark;
      $adfund->category           = $userdata->website_category;
      if($payment_mode == 'wiretransfer'){
        $adfund->bank_id          = $req->bank;
        $adfund->name             = $userdata->first_name . " " .  $userdata->last_name;
        $adfund->email            = $userdata->email;
        $adfund->phone            = $userdata->phone;
        $adfund->address          = $userdata->address_line1;
        $adfund->city             = $userdata->city;
        $adfund->state            = $userdata->state;
        $adfund->country          = $userdata->country;
      }
      $transac                    = new TransactionLog();
      $transac->transaction_id    = $txnid;
      $transac->advertiser_code   = $req->advertiser_code;
      $transac->amount = (isset($req->amount) && $payment_mode == 'wiretransfer') ? $req->amount - ($req->fee + $taxAmount) : $req->amount;
      $transac->pay_type          = $req->pay_type;
     // 03-04-2024
     //   $transac->cpn_typ          = ($payment_mode == 'coupon') ? 1 : 0;
     //   $transac->serial_no          = ($payment_mode == 'coupon' || $payment_mode == 'bonus') ? 0 : generate_serial();
      $transac->cpn_typ           = ($payment_mode == 'coupon' || $payment_mode == 'bonus') ? 1 : 0;
      $transac->serial_no         = ($payment_mode == 'coupon' || $payment_mode == 'bonus' || $userdata->account_type == 1) ? 0 : generate_serial();
      
      $transac->remark            = $req->remark;
      
      if ($transac->save()) {
        $adfund->save();
        $user         = User::where('uid', $req->advertiser_code)->first();
        $user->wallet = $adfund->payment_mode == 'wiretransfer' ? $user->wallet + $req->amount - ($req->fee + $taxAmount) : $user->wallet + $req->amount;
        $user->update();
        $redisWallet = $adfund->payment_mode == 'wiretransfer' ? $req->amount - ($req->fee + $taxAmount) : $req->amount;
        updateAdvWallet($req->advertiser_code, $redisWallet);
        /* Create Ad Fund Mail To User */
        $fullname = "$user->first_name $user->last_name";
        $emailname = $user->email;
        $phone = $user->phone;
        $addressline1 = $user->address_line1;
        $addressline2 = $user->address_line2;
        $city = $user->city;
        $state = $user->state;
        $country = $user->country;
        $useridas = $user->uid;
        // 03-04-2024
        // $transactionid = $transac->transaction_id;
        
         $transactionid = $transac->serial_no > 0 ? $transac->serial_no :  $transac->transaction_id;
        $createdat = $transac->created_at;
        $paymentmode = $adfund->payment_mode;
        $amount = $adfund->amount;
        $paybleamt = $adfund->payble_amt;
        $fee = $adfund->fee;
        $gst = $adfund->gst;
        $remark = $transac->remark;
        $subtotal =  number_format($amount + $fee, 2);
        if($req->mailuser == 'yes') {
        if($paymentmode == 'wiretransfer'){
         $data['termslist'] = DB::table('admin_invoice_terms')->select('terms')->whereNull('deleted_at')->get(); 
         $bankData = DB::table('admin_bank_details')->select('bank_name','acc_name','acc_number','swift_code','ifsc_code','country','acc_address')->where('bank_id',$req->bank)->first();
         $phonecode = DB::table("countries")->select('phonecode')->where('name',$country)->first();
         $data['acc_name'] = $bankData->acc_name;
         $data['acc_number'] = $bankData->acc_number;
         $data['bank_name'] = $bankData->bank_name;
         $data['swift_code'] = $bankData->swift_code;
         $data['ifsc_code'] = $bankData->ifsc_code;
         $data['country'] = $bankData->country;
         $data['acc_address'] = $bankData->acc_address;
         $data['wordAmount'] = numberToWords($paybleamt);
         $data['gst_no'] = $adfund->gst_no ?? 'N/A';
         $data['phonecode'] = $phonecode->phonecode;
         $data['pin'] = $adfund->post_code ?? '';
         $data['name'] = $fullname;
         $data['legal_entity'] = '';
         }
          $subjects = "Funds Added Successfully - 7Search PPC";
          $data['details'] = ['subject' => $subjects, 'full_name' => $fullname, 'emails' => $emailname, 'phone' => $phone, 'addressline1' => $addressline1, 'addressline2' => $addressline2, 'city' => $city, 'state' => $state, 'country' => $country, 'createdat' => $createdat, 'user_id' => $useridas, 'transaction_id' => $transactionid, 'payment_mode' => $paymentmode, 'amount' => $amount, 'payble_amt' => $paybleamt, 'fee' => $fee, 'gst' => $gst, 'remark' => $remark, 'subtotal' => $subtotal];
          $data["email"] = $emailname;
          $data["title"] = $subjects;
          $pdf = $paymentmode == 'wiretransfer' ?  PDF::loadView('emailtemp.pdf.pdf_wire_transfer', $data) : PDF::loadView('emailtemp.pdf.pdf_stripe', $data);
          $postpdf = time() . '_' . $transactionid;
          $fileName =  $postpdf . '.' . 'pdf';
          $path = public_path('pdf/invoice');
          $finalpath = $path . '/' . $fileName;
          $pdf->save($finalpath);
          $body =  View('emailtemp.transactionrecipt', $data);
          $isHTML = true;
          $mail = new PHPMailer();
          $mail->IsSMTP();
          $mail->CharSet = 'UTF-8';
          $mail->Host       = env('MAIL_HOST', "");
          $mail->SMTPDebug  = 0;
          $mail->SMTPAuth   = true;
          $mail->Port       = env('MAIL_PORT', "");
          $mail->Username   = env('mail_username', "");
          $mail->Password   = env('MAIL_PASSWORD', "");
          $mail->setFrom(env('mail_from_address', ""), "7Search PPC");
          $mail->addAddress($emailname);
          $mail->SMTPSecure = 'ssl';
          $mail->isHTML($isHTML);
          $mail->Subject = $subjects;
          $mail->Body    = $body;
          $mail->addAttachment($finalpath);
          $mail->send();
        }
        
        $return['code']        = 200;
        $return['data']        = $adfund;
        $return['trasacdata']  = $transac;
        $return['message']     = 'Fund added in wallet successfully!';
      } else {
        $return['code']    = 101;
        $return['message'] = 'Something went wrong!';
      }
    }
    return json_encode($return, JSON_NUMERIC_CHECK);
  }
}

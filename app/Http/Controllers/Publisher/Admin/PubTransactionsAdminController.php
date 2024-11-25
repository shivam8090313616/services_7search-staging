<?php

namespace App\Http\Controllers\Publisher\Admin;

use App\Http\Controllers\Controller;
use App\Models\Publisher\PubPayout;
use App\Models\AdImpression;
use App\Models\User;
use App\Models\UserCampClickLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PubTransactionsAdminController extends Controller
{

  public function transacAdminList(Request $request)
  {
    $sort_order = $request->sort_order;
    $col = $request->col;
    $status  = $request->payout_sts;
    $limit = $request->lim;
    $page = $request->page;
    $pg = $page - 1;
    $start = ($pg > 0) ? $limit * $pg : 0;
    $startDate = $request->startDate;
    $nfromdate = date('Y-m-d', strtotime($startDate));
    $endDate = $request->endDate;
    $src = $request->src;

    $trasaclist = DB::table('pub_payouts')
      ->select('pub_payouts.id', 'pub_payouts.publisher_id', 'pub_payouts.transaction_id', 'pub_payouts.amount', 'pub_payouts.payout_method', 'pub_payouts.invoice_number', 'pub_payouts.payout_transaction_id', 'pub_payouts.status', 'pub_payouts.release_date', 'pub_payouts.release_created_at', 'pub_payouts.remark', 'pub_payouts.created_at', 'users.first_name as first_name', 'users.last_name')
      ->join('users', 'pub_payouts.publisher_id', 'users.uid')
      ->where('pub_payouts.status', $status);
    if ($status == 1) {
      $trasaclist->whereDate('pub_payouts.release_created_at', '>=', $nfromdate)->whereDate('pub_payouts.release_created_at', '<=', $endDate);
    } else {
      $trasaclist->whereDate('pub_payouts.created_at', '>=', $nfromdate)->whereDate('pub_payouts.created_at', '<=', $endDate);
    }
    if ($src) {
      $trasaclist->whereRaw('concat(ss_pub_payouts.transaction_id, ss_pub_payouts.publisher_id) like ?', "%{$src}%");
    }
    $row = $trasaclist->count();
    if ($col) {
      if ($col == 'first_name') {
        $getdata = $trasaclist->offset($start)->limit($limit)->orderBy('first_name', $sort_order)->get();
      } else {
        $getdata  = $trasaclist->offset($start)->limit($limit)->orderBy('pub_payouts.' . $col, $sort_order)->get();
      }
    } else {
      $getdata = $trasaclist->offset($start)->limit($limit)->orderBy('pub_payouts.id', 'DESC')->get();
    }
    if ($row != null) {
      $return['code']          = 200;
      $return['data']          = $getdata;
      $return['row']           = $row;
      $return['message']       = 'data successfully!';
    } else {
      $return['code']    = 101;
      $return['message'] = 'Not Found Data !';
    }
    return json_encode($return, JSON_NUMERIC_CHECK);
  }

  public function view(Request $request)
  {
    $validator = Validator::make(
      $request->all(),
      [
        'transaction' => "required",
      ]
    );
    if ($validator->fails()) {
      $return['code'] = 100;
      $return['msg'] = 'error';
      $return['err'] = $validator->errors();
      return response()->json($return);
    }
    $transaction = $request->transaction;
    $transview = PubPayout::where('transaction_id', $transaction)->first();
    if ($transview) {
      $return['code'] = 200;
      $return['msg']  = 'Data Successfully !';
      $return['data']  = $transview;
    } else {
      $return['code']  = 101;
      $return['msg'] = 'Not Transaction !';
    }
    return json_encode($return, JSON_NUMERIC_CHECK);
  }

  // public function transactionStatusUpdate(Request $request)
  // {
  //   $validator = Validator::make(
  //     $request->all(),
  //     [
  //       'txnid'      => 'required',
  //       'status_type' => 'required',
  //       'remark'     => 'required',
  //     ]
  //   );

  //   if ($validator->fails()) {
  //     $return['code'] = 100;
  //     $return['error'] = $validator->errors();
  //     $return['message'] = 'Validation error!';
  //     return json_encode($return);
  //   }

  //   $uid = $request->uid;
  //   $txn_id = $request->txnid;
  //   $txnupdate = PubPayout::where('transaction_id', $txn_id)->first();

  //   // Ensure the release date is valid
  //   // if ($txnupdate->release_date >= date('Y-m-d')) {
  //   //   $return['code'] = 101;
  //   //   $return['message'] = 'Payment will be released on or after the Release date.';
  //   //   return json_encode($return);
  //   // }

  //   // Fetch the user
  //   $user = User::where('uid', $uid)->first();

  //   // Check if user_type is not equal to 1
  //   if ($user->user_type == 1) {
  //     $return['code'] = 103;
  //     $return['message'] = 'Cannot update transaction, invalid user type.';
  //     return json_encode($return);
  //   }

  //   // New condition: Check KYC verification status based on the user's country
  //   if (strtoupper($user->country) != 'INDIA') {
  //     // If user is not from India, ensure both photo and photo_id are approved
  //     if ($user->photo_verified != 2 || $user->photo_id_verified != 2) {
  //       $return['code'] = 102;
  //       $return['message'] = 'Cannot update transaction, user Kyc Documents must be approved.';
  //       return json_encode($return);
  //     }
  //   } else {
  //     // If user is from India, ensure photo, photo_id, and PAN are approved
  //     if ($user->photo_verified != 2 || $user->photo_id_verified != 2 || $user->pan_verified != 2) {
  //       $return['code'] = 102;
  //       $return['message'] = 'Cannot update transaction, user Kyc Documents must be approved.';
  //       return json_encode($return);
  //     }
  //   }

  //   // Update transaction details
  //   $txnupdate->remark = $request->remark;
  //   $txnupdate->status = $request->status_type;
  //   $txnupdate->release_created_at = date('Y-m-d H:i:s');
  //   $txnupdate->invoice_number = generateInvoiceNumber();

  //   // Existing condition: If the status is approved (1), handle referral payment processing
  //   if ($request->status_type == 1) {
  //     if ($user->referal_code != "" && $user->referalpmt_status == 0) {
  //       $url = "http://refprogramserv.7searchppc.in/api/add-transaction";
  //       $refData = [
  //         'user_id' => $uid,
  //         'referral_code' => $user->referal_code,
  //         'amount' => $txnupdate->amount,
  //         'transaction_type' => 'Payout',
  //       ];
  //       $curl = curl_init();
  //       curl_setopt_array($curl, [
  //         CURLOPT_URL => $url,
  //         CURLOPT_RETURNTRANSFER => true,
  //         CURLOPT_CUSTOMREQUEST => "POST",
  //         CURLOPT_POSTFIELDS => json_encode($refData),
  //         CURLOPT_HTTPHEADER => [
  //           "Content-Type: application/json"
  //         ],
  //       ]);
  //       $response = curl_exec($curl);
  //       curl_close($curl);
  //     }
  //     $user->referalpmt_status = 1;
  //     $user->update();
  //   }

  //   // Existing logic: Update payout transaction ID
  //   $txnupdate->payout_transaction_id = $request->payout_transac_id ? $request->payout_transac_id : 'NULL';

  //   // Save transaction update and return response
  //   if ($txnupdate->update()) {
  //     $return['code'] = 200;
  //     $return['message'] = 'Transaction approved successfully';
  //   } else {
  //     $return['code'] = 101;
  //     $return['message'] = 'Something went wrong';
  //   }

  //   return json_encode($return, JSON_NUMERIC_CHECK);
  // }

  public function transactionStatusUpdate(Request $request)
  {
    $validator = Validator::make(
      $request->all(),
      [
        'txnid'      => 'required',
        'status_type' => 'required',
        'remark'     => 'required',
      ]
    );

    if ($validator->fails()) {
      $return['code'] = 100;
      $return['error'] = $validator->errors();
      $return['message'] = 'Validation error!';
      return json_encode($return);
    }

    $uid = $request->uid;
    $txn_id = $request->txnid;
    $txnupdate = PubPayout::where('transaction_id', $txn_id)->first();

    // Ensure the release date is valid
    if ($txnupdate->release_date >= date('Y-m-d')) {
      $return['code'] = 101;
      $return['message'] = 'Payment will be released on or after the Release date.';
      return json_encode($return);
    }

    // Fetch the user
    $user = User::where('uid', $uid)->first();

    // Check if user_type is not equal to 1
    if ($user->user_type == 1) {
      $return['code'] = 103;
      $return['message'] = 'Cannot update transaction, invalid user type.';
      return json_encode($return);
    }

    // KYC verification checks
    if (strtoupper($user->country) != 'INDIA') {
      if ($user->photo_verified != 2 || $user->photo_id_verified != 2) {
        $return['code'] = 102;
        $return['message'] = 'Cannot update transaction, user Kyc Documents must be approved.';
        return json_encode($return);
      }
    } else {
      if ($user->photo_verified != 2 || $user->photo_id_verified != 2 || $user->pan_verified != 2) {
        $return['code'] = 102;
        $return['message'] = 'Cannot update transaction, user Kyc Documents must be approved.';
        return json_encode($return);
      }
    }

    // Update transaction details
    $txnupdate->remark = $request->remark;
    $txnupdate->status = $request->status_type;
    $txnupdate->release_created_at = date('Y-m-d H:i:s');
    $txnupdate->invoice_number = generateInvoiceNumber();

    // Handle payment details when status is 1
    if ($request->status_type == 1) {
      // Fetch payment details from pub_user_payout_modes
      $paymentDetails = DB::table('pub_user_payout_modes')
        ->where('publisher_id', $uid)
        ->where('payout_name', $txnupdate->payout_method)
        ->first();

      if ($paymentDetails) {
        // Check if the payout method is wire transfer
        if (strtolower($paymentDetails->payout_name) === 'wire transfer') {
          $accountDetails = [
            'bank_name' => $paymentDetails->bank_name,
            'account_holder_name' => $paymentDetails->account_holder_name,
            'account_number' => $paymentDetails->account_number,
            'ifsc_code' => $paymentDetails->ifsc_code,
            'swift_code' => $paymentDetails->swift_code,
            'iban_code' => $paymentDetails->iban_code,
          ];
          $txnupdate->payout_id = json_encode($accountDetails);
        } else {
          // Store the payout method as a string
          $txnupdate->payout_id = $paymentDetails->pay_account_id;
        }
      } else {
        $return['code'] = 104;
        $return['message'] = 'Payment details not found for the user.';
        return json_encode($return);
      }

      // Handle referral payment processing
      if ($user->referal_code != "" && $user->referalpmt_status == 0) {
        $url = "http://refprogramserv.7searchppc.in/api/add-transaction";
        $refData = [
          'user_id' => $uid,
          'referral_code' => $user->referal_code,
          'amount' => $txnupdate->amount,
          'transaction_type' => 'Payout',
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
      $user->update();
    }

    // Update payout transaction ID
    $txnupdate->payout_transaction_id = $request->payout_transac_id ? $request->payout_transac_id : 'NULL';

    // Save transaction update and return response
    if ($txnupdate->update()) {
      $return['code'] = 200;
      $return['message'] = 'Transaction approved successfully';
    } else {
      $return['code'] = 101;
      $return['message'] = 'Something went wrong';
    }

    return json_encode($return, JSON_NUMERIC_CHECK);
  }

  public function payoutInvoice(Request $request)
  {
    $validator = Validator::make(
      $request->all(),
      [
        'transaction_id' => "required",
      ]
    );

    if ($validator->fails()) {
      $return['code'] = 100;
      $return['msg'] = 'error';
      $return['err'] = $validator->errors();
      return response()->json($return, 400);
    }

    $transaction_id = $request->input('transaction_id');

    $transview = DB::table('pub_payouts')
      ->select(
        'pub_payouts.id',
        'pub_payouts.publisher_id',
        'pub_payouts.transaction_id',
        'pub_payouts.amount',
        'pub_payouts.payout_method',
        'pub_payouts.payout_id',
        'pub_payouts.payout_transaction_id',
        'pub_payouts.status as payout_status',
        'pub_payouts.release_date',
        'pub_payouts.release_created_at',
        'pub_payouts.remark',
        'pub_payouts.invoice_number',
        'pub_payouts.created_at',
        'users.first_name',
        'users.last_name',
        'users.email',
        'users.address_line1',
        'users.address_line2',
        'users.city',
        'users.state',
        'users.country',
      )
      ->leftJoin('users', 'users.uid', '=', 'pub_payouts.publisher_id')
      ->where('pub_payouts.transaction_id', $transaction_id)
      ->first();

    if ($transview) {
      $tax_deduction = 0.00;
      $net_amount = $transview->amount;

      if (strtolower($transview->country) == 'india') {
        $tax_deduction = 0.03 * $transview->amount;
        $net_amount = $transview->amount - $tax_deduction;
      }

      $return['code'] = 200;
      $return['msg'] = 'Data Successfully!';
      $return['data'] = $transview;
      $return['tax_deduction'] = $tax_deduction;
      $return['net_amount'] = $net_amount;
      return response()->json($return, 200);
    } else {
      $return['code'] = 101;
      $return['msg'] = 'Transaction Not Found!';
      return response()->json($return, 404);
    }

    return response()->json($return, JSON_NUMERIC_CHECK);
  }
}

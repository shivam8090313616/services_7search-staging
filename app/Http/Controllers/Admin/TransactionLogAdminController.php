<?php



namespace App\Http\Controllers\Admin;



use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\AdminInvoiceTerm;
use App\Models\TransactionLog;
use App\Models\User;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PDF;
use Illuminate\Support\Facades\Validator;
use PHPMailer\PHPMailer\PHPMailer;
use Carbon\Carbon;



class TransactionLogAdminController extends Controller

{

    public function transactionsList(Request $request)
    {
        $uid = $request->uid;
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        $transaction = DB::table('transactions')
            ->select('transactions.id', 'transactions.transaction_id', 'transactions.advertiser_code', 'transactions.payment_mode', 'transactions.payment_id', 'transaction_logs.remark', 'transaction_logs.amount', 'transaction_logs.pay_type', 'transaction_logs.cpn_typ', 'transaction_logs.created_at')
            ->join('transaction_logs', 'transactions.transaction_id', '=', 'transaction_logs.transaction_id')
            ->where('transactions.advertiser_code', $uid)
            ->orderBy('transactions.id', 'desc');
        $row = $transaction->count();
        $data = $transaction->offset($start)->limit($limit)->get();
        if ($transaction) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = $row;
            $return['message'] = 'Transaction list retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function transactionsReport(Request $request)
    {
        $type = $request->type;
        $cat = $request->cat;
        $authProvider = $request->auth_provider;
        $country = $request->country;
        $startDate = $request->startDate;
        $nfromdate = date('Y-m-d', strtotime($startDate));
        // $endDate = $request->endDate;
        $endDate = date('Y-m-d', strtotime($request->endDate));
        $limit = $request->lim;
        $page = $request->page;
        $src = $request->src;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        $col = $request->col;
        $sort_order = $request->sort_order;


        $report = Transaction::select(
            'users.email',
            'users.country',
            'users.auth_provider',
            'users.website_category',
            'transactions.advertiser_code',
            'transactions.fee',
            'transactions.gst',
            DB::raw('ss_transactions.amount + ss_transactions.fee + ss_transactions.gst as payble_amt'),
            'transactions.amount as tmaunt',
            'transactions.transaction_id',
            'transactions.payment_mode',
            'transaction_logs.id',
            'transaction_logs.pay_type',
            'transactions.payment_id',
            'transaction_logs.amount',
            'transaction_logs.remark',
            'transaction_logs.created_at',
            'categories.cat_name',
            'transactions.payment_resource',
            'transactions.country as wirecountry',
            'transaction_logs.serial_no',
            'sources.title as source_title'
        )
            ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
            ->join('users', 'users.uid', '=', 'transactions.advertiser_code')
            ->join('categories', 'users.website_category', '=', 'categories.id')
            ->join('sources', 'users.auth_provider', '=', 'sources.source_type')
            ->where('transactions.status', 1)
            ->where('transactions.payment_mode', '!=', 'bonus')
            ->where('transaction_logs.cpn_typ', 0)
            ->where('users.account_type', 0);
        if (strlen($type) > 0) {
            $report->where('transactions.payment_mode', $type);
        }
        if (strlen($cat) > 0) {
            $report->where('categories.cat_name', $cat);
        }
        if (strlen($authProvider) > 0) {
            $report->where('users.auth_provider', $authProvider);
        }
        if (strlen($country) > 0) {
            $report->where('users.country', $country)->orWhere('transactions.country', $country);
        }
        if ($startDate && $endDate) {
            $report->whereDate('transactions.created_at', '>=', $nfromdate)
                ->whereDate('transactions.created_at', '<=', $endDate);
        }
        if ($src) {
            $report->whereRaw('concat(ss_users.uid,ss_users.email,ss_transactions.transaction_id,ss_transactions.payment_id,ss_transaction_logs.serial_no) like ?', "%{$src}%");
        }
        if($col){
            $report->orderBy($col, $sort_order);
         }else{
            $report->orderBy('transaction_logs.id', 'desc');
         }
        $row = $report->count();
        $data = $report->offset($start)->limit($limit)->get();
        $totalsuccamt = 0;
        $totalPaybleamt = 0;
        foreach ($data as $value) {
            $totalsuccamt += $value->amount;
            (int)$totalPaybleamt += $value->payble_amt;
            //$totalPaybleamt += (int)$value->amount + (int)$value->fee + (int)$value->gst;
        }
        if ($data) {
            $return['code']                = 200;
            $return['data']                = $data;
            $return['row']                 = $row;
            $return['totalSuccessAmoutn']  = number_format($totalsuccamt, 2);
            $return['totalPaybleamt']      = number_format($totalPaybleamt, 2);
            $return['message'] = 'Transaction list retrieved successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    // public function transactionsReportExcelImport(Request $request)
    // {

    //     $startDate = $request->startDate;

    //     $nfromdate = date('Y-m-d', strtotime($startDate));

    //     $endDate = $request->endDate;

    //   	$report = Transaction::select('users.email', 'transactions.advertiser_code', 'transactions.fee', 'transactions.gst', 'transactions.transaction_id', 'transactions.payment_mode', 'transaction_logs.id', 'transaction_logs.pay_type', 'transactions.payment_id', 'transaction_logs.amount', 'transaction_logs.remark', 'transaction_logs.created_at')

    //         ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')

    //         ->join('users', 'users.uid', '=', 'transactions.advertiser_code')

    //       	->whereDate('transaction_logs.created_at', '>=', $nfromdate)

    //       	->whereDate('transaction_logs.created_at', '<=', $endDate)

    //         ->where('transactions.status', 1);

    //     $data = $report->orderBy('transaction_logs.id', 'desc')->get();

    //   	if ($data) {

    //         $return['code']    = 200;

    //         $return['data']    = $data;

    //         $return['message'] = 'Transaction list retrieved successfully!';

    //     } else {

    //         $return['code']    = 101;

    //         $return['message'] = 'Something went wrong!';

    //     }



    //     return json_encode($return, JSON_NUMERIC_CHECK);

    // }

    public function transactionsReportExcelImport(Request $request)
    {

        $startDate = $request->startDate;

        $nfromdate = date('Y-m-d', strtotime($startDate));

        $endDate = $request->endDate;

        $report = Transaction::select('users.email', 'transactions.advertiser_code', 'users.auth_provider', 'users.country', 'categories.cat_name', 'transactions.fee', 'transactions.gst', 'transactions.transaction_id', 'transaction_logs.serial_no', 'transactions.payment_mode', 'transaction_logs.id', 'transaction_logs.pay_type', 'transactions.payment_id', 'transaction_logs.amount', 'transaction_logs.remark', 'transaction_logs.created_at', 'sources.title as auth_provider')

            ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')

            ->join('users', 'users.uid', '=', 'transactions.advertiser_code')

            ->join('categories', 'users.website_category', '=', 'categories.id')

            ->join('sources', 'users.auth_provider', '=', 'sources.source_type')

            ->whereDate('transaction_logs.created_at', '>=', $nfromdate)

            ->whereDate('transaction_logs.created_at', '<=', $endDate)

            ->where('transactions.status', 1)
            ->where('transactions.payment_mode', '!=', 'bonus')
            ->where('transactions.payment_mode', '!=', 'coupon')
            ->where('users.account_type', '!=', 1);

        $data = $report->orderBy('transaction_logs.id', 'desc')->get();

        //  foreach ($data as $val) {
        //     $auth_provider = $val->auth_provider;
        //     if ($auth_provider === '7api') {
        //         $val->auth_provider = 'Organic';
        //     } else if ($auth_provider === '7smobileapi') {
        //         $val->auth_provider = 'App';
        //     } else if ($auth_provider === '7sinapi') {
        //         $val->auth_provider = '7searchIn';
        //     } else if ($auth_provider === '7sinfoapi') {
        //         $val->auth_provider = 'Info Ads';
        //     } else if ($auth_provider === 'admin') {
        //         $val->auth_provider = 'Admin';
        //     } else if ($auth_provider === '7susapi') {
        //         $val->auth_provider = 'US Ads';
        //     } else if ($auth_provider === '7scaapi') {
        //         $val->auth_provider = 'CA Ads';
        //     } else if ($auth_provider === '7snetapi') {
        //         $val->auth_provider = 'Net Ads';
        //     } else if ($auth_provider === '7sexternal') {
        //         $val->auth_provider = 'External';
        //     } else {
        //         $val->auth_provider = '--';
        //     }
        // }

        if ($data) {

            $return['code']    = 200;

            $return['data']    = $data;

            $return['message'] = 'Transaction list retrieved successfully!';
        } else {

            $return['code']    = 101;

            $return['message'] = 'Something went wrong!';
        }



        return json_encode($return, JSON_NUMERIC_CHECK);
    }



    public function userInfo(Request $request)

    {

        $advertiser_code = $request->uid;

        $userInfo = User::where('uid', $advertiser_code)->first();

        if ($userInfo) {

            $return['code'] = 200;

            $return['data'] = $userInfo;

            $return['message'] = 'User info retrieved successfully!';
        } else {

            $return['code'] = 101;

            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }



    public function transactionsView(Request $request)
    {
        $transactionid = $request->input('transaction_id');
        $report = Transaction::select(DB::raw("CONCAT(ss_users.first_name,' ',ss_users.last_name) as name"), 'transactions.payble_amt', 'transactions.fee', 'transactions.bank_id', 'transactions.name as person_name', 'transactions.legal_entity', 'transactions.email as person_email', 'transactions.phone as person_phone', 'transactions.address as person_address', 'transactions.city as person_city', 'transactions.state as person_state', 'transactions.post_code as person_postcode', 'transactions.country as person_country', 'transactions.gst', 'transactions.fees_tax', 'transaction_logs.remark', 'users.phone', 'users.address_line1', 'users.address_line2', 'users.city', 'users.state', 'users.country', 'users.email', 'transactions.advertiser_code', 'transactions.transaction_id', 'transactions.payment_mode', 'transactions.gst_no', 'transactions.amount', 'transaction_logs.id', 'transaction_logs.pay_type', 'transactions.payment_id', 'transaction_logs.created_at', 'transaction_logs.serial_no', 'admin_bank_details.bank_name', 'admin_bank_details.acc_name', 'admin_bank_details.acc_number', 'admin_bank_details.swift_code', 'admin_bank_details.ifsc_code', 'admin_bank_details.country as bankCountry', 'admin_bank_details.acc_address','countries.phonecode')
            ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
            ->join('users', 'users.uid', '=', 'transactions.advertiser_code')
            ->leftJoin('admin_bank_details', 'transactions.bank_id', '=', 'admin_bank_details.bank_id')
            ->leftJoin('countries','countries.name','=','transactions.country')
            ->where('transactions.transaction_id', $transactionid)
            ->first();
        if ($report->payment_mode == 'bitcoin' || $report->payment_mode == 'stripe' || $report->payment_mode == 'now_payments' || $report->payment_mode == 'coinpay' || $report->payment_mode == 'tazapay' || $report->payment_mode == 'wiretransfer') {
            $report->fee = $report->fee;
        } else {
            $report->fee = $report->fee - $report->fees_tax;
        }

        $report->gst = $report->gst + $report->fees_tax;

        $report->subtotal = $report->amount + $report->fee;

        if ($report) {

            $return['code']    = 200;

            $return['data']    = $report;
            if ($report->payment_mode == 'wiretransfer') {
                $terms = AdminInvoiceTerm::select('terms')->get()->toArray();
                $return['terms']    = $terms;
            }
            $return['message'] = 'Transaction View retrieved successfully!';
        } else {

            $return['code']    = 101;

            $return['message'] = 'Something went wrong!';
        }

        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function transactionApproved(Request $request)
    {
        $userInfo = User::select('uid','status','trash')->where('uid', $request->uid)->first();
        if ($userInfo) {
            if ($userInfo->trash == 1) {
                $return['code'] = 101;
                $return['message'] = 'This User is Deleted!.';
                return json_encode($return, JSON_NUMERIC_CHECK);
            } else {
                $messages = [
                    3 => 'This User is Suspended!.',
                    4 => 'This User is Hold!.',
                ];
                if (isset($messages[$userInfo->status])) {
                    $return['code'] = 101;
                    $return['message'] = $messages[$userInfo->status];
                    return json_encode($return, JSON_NUMERIC_CHECK);
                }
            }
        }
        $exists = TransactionLog::where('transaction_id', $request->txnid)->exists();
        if(!empty($exists)){
            $return['code'] = 101; 
            $return['message'] = 'The Transaction ID already exists!.';
            return json_encode($return, JSON_NUMERIC_CHECK);
        }
        $txn_id = $request->txnid;
        $uid = $request->uid;
        $getUser = DB::table('users')->select('account_type', 'uid')->where('uid', $uid)->where('status', 0)->where('trash', 0)->first();
        $transaction = Transaction::select('transaction_id', 'advertiser_code', 'amount', 'cpn_id', 'cpn_amt', 'status','gst_no','bank_id','payment_mode','post_code','legal_entity','name','country','address','state','city','phone')->where('transaction_id', $txn_id)->first();
        if($transaction->payment_mode == 'wiretransfer'){
            $validator = Validator::make($request->all(),[
                'amount' => ['required', 'numeric', 'min:1'],
                'fee' => ['required', 'numeric', 'max:' . ($request->amount - 1)],
                'taxAmount' => $transaction->country == 'INDIA' ? ['required', 'numeric', 'max:' . ($request->amount - 1)] : '',
            ]);

        if ($validator->fails()) {
            $return["code"] = 100;
            $return["msg"] = "error";
            $return["err"] = $validator->errors();
            return response()->json($return);
        }
        }
        $txnupdate = Transaction::where('transaction_id', $txn_id)->first();
        $txnupdate->remark = 'Payment added to wallet';
        $txnupdate->status = 1;

        //This conditions checks that the request is from WireTransfer
        if ($transaction->payment_mode == 'wiretransfer') {
            $taxAmt= $transaction->country == 'INDIA' ? $request->taxAmount : 0;
            $txnupdate->payble_amt = $request->amount;
            $txnupdate->amount = $request->amount - ($request->fee + $taxAmt);
            $txnupdate->fee = $request->fee;
            $txnupdate->payment_id = $request->transaction_id;
            $txnupdate->gst = $taxAmt;
        }
        if($transaction->payment_mode == 'wiretransfer' && $request->amount < ($request->fee + $taxAmt) && $transaction->country == 'INDIA'){
            return json_encode([
                'code'=> 100,
                'message'=> "The taxable amount (tax and fee) should be less than the payable amount."
                ]);
        }
        if ($txnupdate->update()) {
            $transaction_log = new TransactionLog();
            $transaction_log->transaction_id = $transaction->transaction_id;
            $transaction_log->advertiser_code = $transaction->advertiser_code;
            $transaction_log->amount = isset($request->amount) ? ($transaction->payment_mode == 'wiretransfer' ? $request->amount - ($request->fee + $taxAmt)
            : $request->amount)  : $transaction->amount;
            $transaction_log->pay_type = 'credit';
            // 03-04-2024
            // $transaction_log->serial_no = generate_serial();
            $transaction_log->serial_no = $getUser->account_type == 1 ? 0 : generate_serial();
            $transaction_log->remark = $transaction->payment_mode == 'wiretransfer' ? 'Your amount added in to your wallet via wire transfer.' : 'Amount added to wallet successfully';
            $transaction_log->save();
            $userwalletupdate = User::where('uid', $uid)->first();
            if ($userwalletupdate->referal_code != "" && $userwalletupdate->referalpmt_status == 0) {
                $url = "http://refprogramserv.7searchppc.in/api/add-transaction";
                $refData = [
                    'user_id' => $uid,
                    'referral_code' => $userwalletupdate->referal_code,
                    'amount' => $transaction->amount,
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
            $userwalletupdate->referalpmt_status = 1;      
            $paymentmode = $txnupdate->payment_mode;
            if($paymentmode == 'wiretransfer'){
                $userwalletupdate->wallet = $userwalletupdate->wallet + ($request->amount - ($request->fee+$taxAmt));
                $transaction->amount = ($request->amount - ($request->fee+$taxAmt));
                $txn_id = $request->transaction_id;
            } else {
                $userwalletupdate->wallet = $userwalletupdate->wallet + $transaction->amount;
            }
            
            $userwalletupdate->update();
            updateAdvWallet($transaction->advertiser_code, $transaction->amount);
            // if($transaction->cpn_amt !== null )
            if ($transaction->cpn_amt > 0) {
                $transaction_log = new TransactionLog();
                $transaction_log->transaction_id = $transaction->transaction_id;
                $transaction_log->advertiser_code = $transaction->advertiser_code;
                $transaction_log->amount = $transaction->cpn_amt;
                $transaction_log->pay_type = 'credit';
                // $transaction_log->serial_no = generate_serial();
                $transaction_log->cpn_typ = 1;
                $transaction_log->remark = 'Coupon Amount added to wallet successfully';
                $transaction_log->save();
                $usercouponamount = User::where('uid', $uid)->first();
                $usercouponamount->wallet = $usercouponamount->wallet + $transaction->cpn_amt;
                $usercouponamount->update();
                updateAdvWallet($transaction->advertiser_code, $transaction->cpn_amt);
            }

            /* User Section */

            $fullname = "$userwalletupdate->first_name $userwalletupdate->last_name";
            $emailname = $userwalletupdate->email;
            $phone = strlen($transaction->phone) > 0 ? $transaction->phone : $userwalletupdate->phone;
            $addressline1 = strlen($transaction->address) > 0 ? $transaction->address : $userwalletupdate->address_line1;
            $addressline2 = $userwalletupdate->address_line2;
            $city =  strlen($transaction->city) > 0 ? $transaction->city : $userwalletupdate->city;
            $state = strlen($transaction->state) > 0 ? $transaction->state : $userwalletupdate->state;
            $country = strlen($transaction->country) > 0 ? $transaction->country : $userwalletupdate->country;
            $useridas = $userwalletupdate->uid;

            /* Transaction Section */
            // 03-04-2024
            // $transactionid = $transaction_log->transaction_id;

            $transactionid = $transaction_log->serial_no > 0 ? $transaction_log->serial_no :  $transaction_log->transaction_id;
            $createdat = $transaction_log->created_at;
            $remark = $transaction_log->remark;
            /* Transaction Log Section */
            $amount        = $txnupdate->amount;
            $paybleamt     = $paymentmode == 'wiretransfer' ? $txnupdate->payble_amt :  number_format($txnupdate->amount + $txnupdate->fee + $txnupdate->gst, 2);
            //$amount = $txnupdate->amount;
            //$paybleamt = $txnupdate->payble_amt;
            $fee = number_format($txnupdate->fee, 2);
            $gst = number_format($txnupdate->gst, 2);

            $subjects = "Funds Added Successfully - 7Search PPC";
            $report = Transaction::select('transactions.payble_amt', 'transactions.gst', 'transactions.fee', 'transactions.fees_tax', 'transaction_logs.remark', 'transactions.payment_mode', 'transactions.amount')
                ->join('transaction_logs', 'transaction_logs.transaction_id', '=', 'transactions.transaction_id')
                ->where('transactions.transaction_id', $txn_id)
                ->first();
             $fee = $report->fee;
             if($paymentmode == 'wiretransfer'){
              $data['termslist'] = DB::table('admin_invoice_terms')->select('terms')->whereNull('deleted_at')->get(); 
              $bankData = DB::table('admin_bank_details')->select('bank_name','acc_name','acc_number','swift_code','ifsc_code','country','acc_address')->where('bank_id',$transaction->bank_id)->first();
              $phonecode = DB::table("countries")->select('phonecode')->where('name',$country)->first();
             $data['acc_name'] = $bankData->acc_name;
             $data['acc_number'] = $bankData->acc_number;
             $data['bank_name'] = $bankData->bank_name;
             $data['swift_code'] = $bankData->swift_code;
             $data['ifsc_code'] = $bankData->ifsc_code;
             $data['country'] = $bankData->country;
             $data['acc_address'] = $bankData->acc_address;
             $data['wordAmount'] = numberToWords($paybleamt);
             $data['gst_no'] = $transaction->gst_no ?? 'N/A';
             $data['phonecode'] = $phonecode->phonecode;
             $data['pin'] = $transaction->post_code;
             $data['name'] = $transaction->name ?? $fullname;
             $data['legal_entity'] = $transaction->legal_entity ?? '';
             }
            $subtotal = $report->amount + $report->fee;
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
            /* Closed Section */
            $noti_title = 'Payment successfully';
            $noti_desc = 'Your payment has been successfully completed. The amount is added to your wallet.';
            $notification = new Notification();
            $notification->notif_id = gennotificationuniq();
            $notification->title = $noti_title;
            $notification->noti_desc = $noti_desc;
            $notification->noti_type = 1;
            $notification->noti_for = 1;
            $notification->all_users = 0;
            $notification->status = 1;
            if ($notification->save()) {
                $noti = new UserNotification();
                $noti->notifuser_id = gennotificationuseruniq();
                $noti->noti_id = $notification->id;
                $noti->user_id = $userwalletupdate->uid;
                $noti->user_type = 1;
                $noti->view = 0;
                $noti->created_at = Carbon::now();
                $noti->updated_at = now();
                $noti->save();
            }
            $return['code'] = 200;
            $return['message'] = 'Transaction approved successfully';
        } else {
            $return['code'] = 101;
            $return['message'] = 'Something went wrong';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
}

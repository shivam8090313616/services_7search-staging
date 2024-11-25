<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRemovedPaymentResctrictionLog;
use App\Models\User;
use App\Models\Notification;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ManageAdminPaymentSettingController extends Controller
{
    public function managePaymentList(Request $request)
    {
        $data = DB::table('panel_customizations')->select('payment_title', 'payment_header', 'payment_min_amt', 'payment_description', 'id', 'placeholder', 'info_desc', 'desc_status', 'wiretransfer_minamt', 'trans_attempt')->get();

        if ($data) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['row']     = count($data);
            $return['message'] = 'get List Successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    public function updateManagePayment(Request $request)
    {
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;

        $validator = Validator::make(
            $request->all(),
            [
                'id' => $request->id ? 'required|exists:panel_customizations,id' : '',
                'payment_min_amt' => 'required|numeric|min:20',
                'payment_title' => 'required',
                'wiretransfer_minamt' => 'required|numeric',
                'email_verify_otp' => 'required',
                'trans_attempt' => 'required|numeric|min:2',
            ],
            [
                'payment_min_amt.required' => 'The payment Minimum Amount field is required.',
                'payment_min_amt.min' => 'The payment Minimum Amount must be at least $20.',
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 100;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error';
            return json_encode($return);
        }

        if (!empty($request)) {
            if ($verifyotp == 1) {
                $profileLog = [];
                $getRecords =  DB::table('panel_customizations')->where('id', $request->id)->first();
                if ($getRecords->payment_title != $request->payment_title) {
                    $profileLog['paymentTitle']['previous'] =  $getRecords->payment_title;
                    $profileLog['paymentTitle']['updated']  =  $request->payment_title;
                }
                if ($getRecords->payment_header != $request->payment_header) {
                    $profileLog['paymentHeader']['previous'] = $getRecords->payment_header;
                    $profileLog['paymentHeader']['updated']  =  $request->payment_header;
                }
                if ($getRecords->payment_min_amt != $request->payment_min_amt) {
                    $profileLog['paymentMinAmt']['previous'] = $getRecords->payment_min_amt;
                    $profileLog['paymentMinAmt']['updated']  =  $request->payment_min_amt;
                }
                if ($getRecords->payment_description != $request->payment_description) {
                    $profileLog['paymentDescription']['previous'] = $getRecords->payment_description;
                    $profileLog['paymentDescription']['updated']  =  $request->payment_description;
                }
                if ($getRecords->placeholder != $request->placeholder) {
                    $profileLog['placeholder']['previous'] = $getRecords->placeholder;
                    $profileLog['placeholder']['updated']  =  $request->placeholder;
                }
                if ($getRecords->info_desc != $request->info) {
                    $profileLog['infoDesc']['previous'] = $getRecords->info_desc;
                    $profileLog['infoDesc']['updated']  =  $request->info;
                }
                if ($getRecords->desc_status != $request->status) {
                    $profileLog['descStatus']['previous'] = $getRecords->desc_status;
                    $profileLog['descStatus']['updated']  =  $request->status;
                }
                if ($getRecords->wiretransfer_minamt != $request->wiretransfer_minamt) {
                    $profileLog['wiretransferMinamt']['previous'] = $getRecords->wiretransfer_minamt;
                    $profileLog['wiretransferMinamt']['updated']  =  $request->wiretransfer_minamt;
                }
                if ($getRecords->trans_attempt != $request->trans_attempt) {
                    $profileLog['transAttempt']['previous'] = $getRecords->trans_attempt;
                    $profileLog['transAttempt']['updated']  =  $request->trans_attempt;
                }
                if (count($profileLog) > 0) {
                    Cache::clear();
                    $profileLog['message'] =  "Record updated successfully.";
                    $data = json_encode($profileLog);
                    DB::table('common_logs')->insert(['uid' => 1, 'type_module' => 'payment-Min-Amt', 'description' => $data, 'created_at' => date('Y-m-d H:i:s')]);
                }
                $res =  DB::table('panel_customizations')
                    ->where('id', $request->id)
                    ->update(['payment_title' => $request->payment_title, 'payment_header' => $request->payment_header, 'payment_min_amt' => $request->payment_min_amt, 'payment_description' => $request->payment_description, 'placeholder' => $request->placeholder, 'info_desc' => $request->info, 'desc_status' => $request->status, 'wiretransfer_minamt' => $request->wiretransfer_minamt, 'trans_attempt' => $request->trans_attempt]);
                if ($res > 0) {
                    $return['code']    = 200;
                    $return['message'] = 'Payment Updated Successfully!';
                } else {
                    $return['code']    = 101;
                    $return['message'] = 'Something went wrong!';
                }
                return json_encode($return, JSON_NUMERIC_CHECK);
            } else {
                return json_encode([
                    'code' => 101,
                    'message' => "Invalid OTP Code!",
                ]);
            }
        }
    }
    public function userGetPaymentList()
    {
        $data = DB::table('panel_customizations')->select('payment_title', 'payment_header', 'payment_min_amt', 'payment_description', 'placeholder', 'info_desc', 'desc_status', 'payment_description', 'wiretransfer_minamt')->first();
        if ($data) {
            $return['code']    = 200;
            $return['data']    = $data;
            $return['message'] = 'Get List Successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    // api for sent otp update payment page on crm
    public function sendOtpUpdateAmt()
    {
        $otp = mt_rand(100000, 999999);
        Cache::forget('otp');
        Cache::put('otp', $otp, now()->addMinutes(15));
        $email = ['abhinav97dubey@gmail.com', 'ry0085840@gmail.com', 'rajeevgp1596@gmail.com', 'pragendras94@gmail.com', 'shivam.logelite@gmail.com', 'deepaklogelite@gmail.com'];
        $data['details'] = ['subject' => 'Your One-Time Password (OTP) for min. pay amount limit. - 7Search PPC', 'otp' => $otp];
        /* Admin Section */
        $subject = 'Your One-Time Password (OTP) for min. pay amount limit. - 7Search PPC';
        $body =  View('emailtemp.paymentVerificationMail', $data);
        /* Admin Mail Section */
        $res = sendmailpaymentupdate($subject, $body, $email);
        if ($res == 1) {
            $return['code'] = 200;
            $return['data'] = base64_encode($otp);
            $return['msg'] = 'Otp Sent Successfully.';
        } else {
            $return['code'] = 101;
            $return['msg'] = 'Email Not Send.';
        }
        return response()->json($return);
    }
    public function PaymentLogsList(Request $request)
    {
        $limit = $request->lim;
        $page = $request->page;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        $data = DB::table('common_logs')->orderBy('id', 'desc')->offset($start)->limit($limit)->get();
        $row = $data->count();
        foreach ($data as $log) {
            $description = [json_decode($log->description)];
            $log->description = $description;
        }
        if ($data) {
            $return['code']    = 200;
            $return['row']     = $row;
            $return['data']    =  $data;
            $return['message'] = 'Get List Successfully!';
        } else {
            $return['code']    = 101;
            $return['message'] = 'Something went wrong!';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
    public function userPayAttempts(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'uid' => 'required'
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 100;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return, JSON_NUMERIC_CHECK);
        }
        $uid  = $request->uid;
        $user = User::select('payment_lock')->where('uid', $uid)->first();
        if ($user->payment_lock == 0) {
            $data = checkPayAttempts($uid);
            return json_encode($data, JSON_NUMERIC_CHECK);
        }
        $return['code']      = 102;
        $return['message']   = 'Transaction limit exceeded for today. Your payment page has been blocked.';
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    // api for sent otp for payment unlock
    public function sendOtpUnlockPayment()
    {
        $otp = mt_rand(100000, 999999);
        Cache::forget('otp');
        Cache::put('otp', $otp, now()->addMinutes(15));
        $email = ['abhinav97dubey@gmail.com', 'ry0085840@gmail.com', 'rajeevgp1596@gmail.com', 'pragendras94@gmail.com', 'shivam.logelite@gmail.com', 'deepaklogelite@gmail.com'];
        $data['details'] = ['subject' => 'Your One-Time Password (OTP) for Unlock Payment. - 7Search PPC', 'otp' => $otp];
        /* Admin Section */
        $subject = 'Your One-Time Password (OTP) for Unlock Payment. - 7Search PPC';
        $body =  View('emailtemp.paymentVerificationMail', $data);
        /* Admin Mail Section */
        $res = sendmailpaymentupdate($subject, $body, $email);
        if ($res == 1) {
            $return['code'] = 200;
            $return['data'] = base64_encode($otp);
            $return['msg'] = 'Otp Sent Successfully.';
        } else {
            $return['code'] = 101;
            $return['msg'] =  $otp;
        }
        return response()->json($return);
    }

    public function paymentUnlock(Request $request)
    {
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;
        $uid = $request->uid;
        $today = date('Y-m-d');
        $statusArray = [0, 2];
        $validator = Validator::make(
            $request->all(),
            [
                'uid' => 'required|exists:users,uid',
                'email_verify_otp' => 'required'
            ]
        );
        if ($validator->fails()) {
            $return['code']    = 100;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        if ($verifyotp == 1) {
            $getpmtlock = User::select('payment_lock', 'payment_lock_at')
                ->where(['uid' => $uid, 'payment_lock' => 1])
                ->first();;
            if ($getpmtlock->payment_lock == 1) {
                DB::table('transactions')
                    ->where('advertiser_code', $uid)
                    ->whereIn('status', $statusArray)
                    ->whereDate('created_at', $today)
                    ->update(['payment_lock' => 1]);
                $user = DB::table('users')->where('uid', $uid)->update(['payment_lock' => 0]);
                if ($user) {
                    $removedLog = new AdminRemovedPaymentResctrictionLog();
                    $removedLog->uid = $uid;
                    $removedLog->remark = 'Payment Page has been unlocked by Admin.';
                    $removedLog->payment_lock_at = $getpmtlock->payment_lock_at;
                    if ($removedLog->save()) {
                        Cache::clear();
                    // send notification to user
                    $noti_title = "Your payment page access has been unlocked.";
                    $noti_desc  = "Dear advertiser, Your payment page access has been successfully unlocked. You can now proceed with your payment.";
                    $notification = new Notification();
                    $notification->notif_id = gennotificationuniq();
                    $notification->title = $noti_title;
                    $notification->noti_desc = $noti_desc;
                    $notification->noti_type = 1;
                    $notification->noti_for = 1;
                    $notification->all_users = 0;
                    $notification->status = 1;
                    $notification->uid = $uid;
                    if ($notification->save()) {
                        $noti = new UserNotification();
                        $noti->notifuser_id = gennotificationuseruniq();
                        $noti->noti_id = $notification->id;
                        $noti->user_id = $uid;
                        $noti->user_type = 1;
                        $noti->view = 0;
                        $noti->created_at = Carbon::now();
                        $noti->updated_at = now();
                        $noti->save();
                    }
                        // $adminmail1 = 'rajeevgp1596@gmail.com';
                        // $adminmail2 = 'deepaklogelite@gmail.com';
                        // $data['details'] = ['usersid' => $uid];
                        // $bodyadmin =   View('emailtemp.paymentunlockadmin', $data);
                        // $subjectadmin = 'Payment Page Unlocked - 7Search PPC';
                        // $admreturn = sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
                        $userdetail = User::where('uid', $uid)->first();
                        $email = $userdetail->email;
                        $fullname = $userdetail->first_name . " " . $userdetail->last_name;
                        $subjects = "Your Payment page access has been unlocked - 7Search PPC";
                        $data['details'] = ['subject' => $subjects, 'fullname' => $fullname];
                        $body = View('emailtemp.paymentunlockuser', $data);
                        $userreturn = sendmailUser($subjects, $body, $email);
                        return json_encode([
                            'code' => 200,
                            'message' => $userreturn == 1 ? 'Payment page has been unlocked & mail sent successfully.' : 'Payment page has been unlocked but mail not sent.'
                        ]);
                    } else {
                        return json_encode([
                            'code' => 201,
                            'message' => 'Failed to unlock payment page!'
                        ]);
                    }
                }
            } else {
                return json_encode([
                    'code' => 201,
                    'message' => 'This user payment page is already unlocked!'
                ]);
            }
        } else {
            return json_encode([
                'code' => 101,
                'message' => 'Invalid OTP Code!'
            ]);
        }
    }

    // Payment unlock log list
    public function paymentUnlockLogList(Request $request)
    {
        $invoice = AdminRemovedPaymentResctrictionLog::select('admin_removed_payment_resctriction_logs.*', DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as fullname"))->join('users', 'admin_removed_payment_resctriction_logs.uid', '=', 'users.uid');
        $page    = $request->page ?? 1;
        $limit   = $request->lim ?? 10;
        $pg      = $page - 1;
        $start   = ($pg > 0) ? $limit * $pg : 0;
        $src = $request->src;
        $startDate = date('Y-m-d', strtotime($request->startDate));
        $endDate =  date('Y-m-d', strtotime($request->endDate));
        $validator = Validator::make($request->all(),[
            'startDate'=>'required',
            'endDate'=>'required'
            ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }
        if(!empty($src)){
            $invoice->whereRaw('concat(ss_users.first_name," ",ss_users.last_name," ",ss_users.uid) like ?', "%{$src}%");
        }
        if ($startDate && $endDate && !$src) {
            $invoice->whereDate('admin_removed_payment_resctriction_logs.created_at', '>=', $startDate)->whereDate('admin_removed_payment_resctriction_logs.created_at', '<=', $endDate);
        }
        $row     = $invoice->count();
        $data    = $invoice->offset($start)->limit($limit)->orderBy('id', 'desc')->get()->toArray();
        if (!empty($data)) {
            $return['code'] = 200;
            $return['data'] = $data;
            $return['row']  = $row;
            $return['msg']  = 'Unlock user logs fetched successfully!';
        } else {
            $return['code'] = 101;
            $return['data'] = [];
            $return['row']  = 0;
            $return['msg']  = 'Logs not found!.';
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }

    public function paymentPageLocklist(Request $request)
    {
        $src = $request->src;
        $startDate = date('Y-m-d', strtotime($request->startDate));
        $endDate =  date('Y-m-d', strtotime($request->endDate));
        $page = $request->page ?? 1;
        $limit = $request->lim ?? 10;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;

        $users = User::select('uid', 'payment_lock_at', 'phone', 'email','user_type', DB::raw("CONCAT(ss_users.first_name, ' ', ss_users.last_name) as fullname"))->where('payment_lock', 1)->where('status', 0)->where('account_type', 0);
        
        $validator = Validator::make($request->all(),[
            'startDate'=>'required',
            'endDate'=>'required'
            ]);
        if ($validator->fails()) {
            $return['code'] = 100;
            $return['error'] = $validator->errors();
            $return['message'] = 'Validation error!';
            return json_encode($return);
        }
        if(!empty($src)){
            $users->whereRaw('concat(ss_users.first_name," ",ss_users.last_name," ",ss_users.email, ss_users.uid, ss_users.phone) like ?', "%{$src}%");
        }
        if ($startDate && $endDate && !$src) {
            $users->whereDate('users.payment_lock_at', '>=', $startDate)->whereDate('users.payment_lock_at', '<=', $endDate);
        }
        $row = $users->count();
        $lockdata = $users->offset($start)->limit($limit)->orderBy('payment_lock_at', 'desc')->get();
        if ($row > 0) {
            $return['code'] = 200;
            $return['restrict_users'] = $lockdata;
            $return['row'] = $row;
            $return['message'] = "Transaction locked list fetched successfully.";
        } else {
            $return['code'] = 101;
            $return['restrict_users'] = [];
            $return['row'] = $row;
            $return['message'] = "Record not found!";
        }
        return json_encode($return, JSON_NUMERIC_CHECK);
    }
}

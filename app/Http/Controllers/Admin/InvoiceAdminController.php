<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use DB;

class InvoiceAdminController extends Controller
{
    public function manageInvoiceOtp()
    {
        $otp = mt_rand(100000, 999999);

        Cache::forget('otp');

        Cache::put('otp', $otp, now()->addMinutes(15));
        $adminmail1 = 'rajeevgp1596@gmail.com';
        $adminmail2 = 'adnan.logelite@gmail.com';
        $data['details'] = ['subject' => 'Your One-Time Password (OTP) for Manage Invoice. - 7Search PPC', 'otp' => $otp];
        /* Admin Section */
        $subjectadmin = 'Your One-Time Password (OTP) for Manage Invoice. - 7Search PPC';
        $bodyadmin =  View('emailtemp.paymentVerificationMail', $data);
        /* Admin Mail Section */
        $sendmailadmin =  sendmailAdmin($subjectadmin, $bodyadmin, $adminmail1, $adminmail2);
        if ($sendmailadmin == 1) {
            $return['code'] = 200;
            $return['data'] = base64_encode($otp);
            $return['msg'] = 'Otp Sent Successfully.';
        } else {
            $return['code'] = 101;
            $return['msg'] = 'Email Not Send.';
        }
        return response()->json($return);
    }

    public function getAdminBank(Request $request)
    {
        $bank = AdminBankDetail::select('id', 'bank_id', 'bank_name', 'acc_name', 'acc_number', 'swift_code', 'ifsc_code', 'country', 'acc_address','created_at','updated_at');
        if ($request->uid) {
            $row  = $bank->count();
            $data = $bank->get()->toArray();
        } else {
            $page = $request->page ?? 1;
            $limit = $request->lim ?? 5;
            $pg = $page - 1;
            $start = ($pg > 0) ? $limit * $pg : 0;
            $row  = $bank->count();
            $data = $bank->offset($start)->limit($limit)->get()->toArray();
        }
        if (!empty($data)) {
            $return['code'] = 200;
            $return['data'] = $data;
            $return['row']  = $row;
            $return['msg']  = 'Bank Details fetched successfully!.';
        } else {
            $return['code'] = 101;
            $return['data'] = [];
            $return['row']  = 0;
            $return['msg']  = 'Bank Details not found!.';
        }
        return response()->json($return);
    }

    // add & upadate invoice bank details
    public function ManagBankDetails(Request $request)
    {
        $bankData = AdminBankDetail::select('id','bank_id')->where('id', $request->id)->withTrashed()->first();
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;
        $validator = Validator::make(
            $request->all(),
            [
                'id' => $request->id ? 'required|exists:admin_bank_details,id' : '',
                'bank_name' => $request->id ? "required|unique:admin_bank_details,bank_name," . $bankData->id . ",id,deleted_at,NULL" : "required|unique:admin_bank_details,bank_name,NULL,id,deleted_at,NULL",
                'acc_name' => 'required|string',
                'acc_number' => 'required|numeric',
                'swift_code' => 'required',
                'ifsc_code' => 'required',
                'country' => 'required|string',
                'acc_address' => 'required',
                'email_verify_otp' => 'required'
            ]
        );

        if ($validator->fails()) {
            $return['code']    = 100;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }
        if (!empty($request)) {
            if ($verifyotp == 1) {
                $bankDetails = AdminBankDetail::updateOrCreate(
                    [
                        'id' => $request->id,
                    ],
                    [
                        'bank_id' => $request->id > 0 ? $bankData->bank_id : generateBankId(),
                        'bank_name' => $request->bank_name,
                        'acc_name' => $request->acc_name,
                        'acc_number' => $request->acc_number,
                        'swift_code' => $request->swift_code,
                        'ifsc_code' => $request->ifsc_code,
                        'country' => $request->country,
                        'acc_address' => $request->acc_address,
                    ]
                );
                if (!empty($bankDetails)) {
                    Cache::clear();
                    $return['code']    = 200;
                    $return['message'] = $request->id ? 'Bank Detail Updated Successfully!' : 'Bank Detail Added Successfully!';
                } else {
                    $return['code']    = 101;
                    $return['message'] = 'Something went wrong!';
                }
                return json_encode($return);
            } else {
                return json_encode([
                    'code' => 101,
                    'message' => "Invalid OTP Code!",
                ]);
            }
        }
    }

    public function deleteBankDetails(Request $request)
    {
        $id = $request->id;
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:admin_bank_details,id',
                'email_verify_otp' => 'required'
            ]
        );

        if ($validator->fails()) {
            $return['code']    = 100;
            $return['error']   = $validator->errors();
            $return['message'] = 'Validation Error!';
            return json_encode($return);
        }

        if (!empty($request)) {
            if ($verifyotp == 1) {
                $data = AdminBankDetail::select('id')->where('id', $id)->delete();
                if ($data > 0) {
                    Cache::clear();
                    $return['code'] = 200;
                    $return['message'] = "Bank data deleted successfully!";
                } else {
                    $return['code'] = 101;
                    $return['message'] = "This record is already deleted!";
                }
            } else {
                return json_encode([
                    'code' => 101,
                    'message' => "Invalid OTP Code!",
                ]);
            }
        }
        return json_encode($return);
    }
}

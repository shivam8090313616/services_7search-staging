<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminInvoiceTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class InvoiceTermsAdminController extends Controller
{
    public function SentInvoiceTermsOtp()
    {
        $otp = mt_rand(100000, 999999);

        Cache::forget('otp');

        Cache::put('otp', $otp, now()->addMinutes(15));
        $adminmail1 = 'rajeevgp1596@gmail.com';
        $adminmail2 = 'adnan.logelite@gmail.com';
        $data['details'] = ['subject' => 'Your One-Time Password (OTP) for Manage Invoice Terms & Conditions. - 7Search PPC', 'otp' => $otp];
        /* Admin Section */
        $subjectadmin = 'Your One-Time Password (OTP) for Manage Invoice Terms & Conditions. - 7Search PPC';
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

    public function InvoiceTermsList(Request $request)
    {
        $invoice = AdminInvoiceTerm::select('*');
        $page = $request->page ?? 1;
        $limit = $request->lim ?? 5;
        $pg = $page - 1;
        $start = ($pg > 0) ? $limit * $pg : 0;
        $row  = $invoice->count();
        $data = $invoice->offset($start)->limit($limit)->get()->toArray();
        if (!empty($data)) {
            $return['code'] = 200;
            $return['data'] = $data;
            $return['row']  = $row;
            $return['msg']  = 'Invoice Terms Details fetched successfully!.';
        } else {
            $return['code'] = 101;
            $return['data'] = [];
            $return['row']  = 0;
            $return['msg']  = 'Record not found!.';
        }
        return response()->json($return);
    }

    // add & upadate invoice terms details
    public function ManagInvoiceTermsDetails(Request $request)
    {
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;
        $validator = Validator::make(
            $request->all(),
            [
                'id' => $request->id ? 'required|exists:admin_invoice_terms,id' : '',
                'terms' => 'required',
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
                $bankDetails = AdminInvoiceTerm::updateOrCreate(
                    [
                        'id' => $request->id,
                    ],
                    [
                        'terms' => $request->terms,
                    ]
                );

                if (!empty($bankDetails)) {
                    Cache::clear();
                    $return['code']    = 200;
                    $return['message'] = $request->id ? 'Invoice Terms Detail Updated Successfully!' : 'Invoice Terms Detail Added Successfully!';
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

    public function delInvoiceTermsDetails(Request $request)
    {
        $id = $request->id;
        $email_verify_otp = $request->email_verify_otp;
        $sentotp = Cache::get('otp');
        $verifyotp = $sentotp == base64_decode($email_verify_otp) ? 1 : 0;
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:admin_invoice_terms,id',
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
                $data = AdminInvoiceTerm::select('id')->where('id', $id)->delete();
                if ($data > 0) {
                    Cache::clear();
                    $return['code'] = 200;
                    $return['message'] = "Invoice terms record deleted successfully!";
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

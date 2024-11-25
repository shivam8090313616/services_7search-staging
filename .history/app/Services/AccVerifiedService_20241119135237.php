<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;

class AccVerifiedService
{
    /**
     * Handle account verification or send verification email.
     *
     * @param string $uid
     * @param string|null $method
     * @return array
     */
    public function handleVerification(string $uid, ?string $method = null): array
    {
        // Find the user by UID
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

     

        // If no method is provided, return ac_verified status
        // if (is_null($method)) {
        //     return [
        //         'success' => true,
        //         'ac_verified' => $user->ac_verified,
        //     ];
        // }
        
        // If method is 'sendMail', send verification email
        if ($method === 'sendMail') {
           
            
            $mailStatus = $this->sendVerificationEmail($user);

            if ($mailStatus) {
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send verification email.',
                ];
            }
        }

        // If the method is invalid, return an error
        return [
            'success' => false,
            'message' => 'Invalid method provided.',
        ];
    }

    /**
     * Send a verification email to the user.
     *
     * @param User $user
     * @return bool
     */
    protected function sendVerificationEmail(User $user): bool
    {
        try {
            $user = User::where('user_id', $userId)->first();
            $mailData = [];
            $email = $user->email;
            $token = Str::random(45);
            if ($method == 'sendMail') {
                if ($user->status == 1) {
                    return response()->json(['message' => 'You are already verified!'], 400);
                }
                $user->verify_token = $token;
                $user->verify_token_at = date('Y-m-d H:i:s');
                $user->save();
                $subject = 'Please Verify Your Email';
                $mailData = [
                    'title' => 'Verification Mail from 7SearchPPC Referral',
                    'body' => 'Please click on the link below to verify your account.',
                    'token' => $user->verify_token,
                    'id' => $user->user_id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ];
                $body = View('mail.user_verify_mail', $mailData);
                $res = sendMailUser($subject, $body, $email);
                return $res;
            }
         sendmailUser($subject, $body, $email);
            return true;
        } catch (\Exception $e) {

            return false;
        }
    }
}

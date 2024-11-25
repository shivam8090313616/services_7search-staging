<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\PHPMailer;
use Exception;

class AccVerifiedService
{
    public function handleVerification(string $uid, ?string $method = null): array
    {
        try {
            $user = User::where('uid', $uid)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }

            if (is_null($method)) {
                return [
                    'success' => true,
                    'mail_verified' => $user->mail_verified,
                ];
            }
            if ($method === 'sendMail') {
                if ($user->mail_verified == 1) {
                    return [
                        'success' => false,
                        'message' => 'Your mail address is verified.',
                    ];
                }
                $mailStatus = $this->sendVerificationEmail($user);

                if ($mailStatus['success']) {
                    return [
                        'success' => true,
                        'message' => 'Verification email sent successfully.',
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $mailStatus['message'],
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid method provided.',
            ];
        } catch (Exception $e) {
            Log::error('Error in handleVerification: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during verification. Please try again later.',
            ];
        }
    }

    protected function sendVerificationEmail(User $user): array
    {
        try {
            if ($user->status == 1) {
                return [
                    'success' => false,
                    'message' => 'User already verified.',
                ];
            }

            if (!empty($user->verify_code)) {
                return [
                    'success' => false,
                    'message' => 'Verification email has already been sent. Please check your email.',
                ];
            }

            $rawToken = str_shuffle('JbrFpMxLHDnbs' . rand(1111111, 9999999));
            // Ek random string generate karte hain using str_shuffle() aur rand(). Example: "JbrFpMxLHDnbs1234567"
            $encryptedToken = Crypt::encryptString($rawToken);
            // Ye encrypted token user ko email ke through bhejenge.
            $hashedToken = Hash::make($rawToken);
            // Is hashed value ko sirf server-side use karenge verification ke time pe. means

            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'userid' => $user->uid,
                'token' => $encryptedToken,
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            $body = View::make('emailtemp.user_verify_mail', $mailData)->render();
            // https://7searchppc.com/verify-mail?uid=123&token=ENCRYPTED_TOKEN


            $mailStatus = sendmailUser($subject, $body, $user->email);

            if ($mailStatus == 1) {
                $user->update(['verify_code' => $hashedToken]);
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again later.',
                ];
            }
        } catch (Exception $e) {
            Log::error('Error sending verification email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending the verification email. ' . $e->getMessage(),
            ];
        }
    }

    public function verifyAccount(string $uid, string $encryptedToken): array
    {
        try {
            $user = User::where('uid', $uid)->first();

            if (!$user || $user->verify_code === null) {
                return [
                    'success' => false,
                    'message' => 'This link has expired or is invalid!',
                ];
            }

            $rawToken = Crypt::decryptString($encryptedToken);
            if (!Hash::check($rawToken, $user->verify_code)) {
                return [
                    'success' => false,
                    'message' => 'This link has expired or is invalid!',
                ];
            }

            if ($user->mail_verified == 1) {
                return [
                    'success' => false,
                    'message' => 'Account is already verified.',
                ];
            }

            $user->update([
                'mail_verified' => 1,
                'email_verified_at' => Carbon::now(),
                'verify_code' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Account successfully verified.',
            ];
        } catch (Exception $e) {
            Log::error('Error verifying account: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during verification. Please try again later.',
            ];
        }
    }
}

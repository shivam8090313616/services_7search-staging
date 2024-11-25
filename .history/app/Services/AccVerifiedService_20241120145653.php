<?php

namespace App\Services;

use App\Models\User;
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
                    'ac_verified' => $user->ac_verified,
                ];
            }
            if ($method === 'sendMail') {
                if ($user->ac_verified == 1) {
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

            $token = Str::random(10);
            $user->verify_code = $token;
            $user->save();

            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'userid' => $user->uid,
                'token' => $token,
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            $body = View::make('emailtemp.user_verify_mail', $mailData)->render();

            return $this->sendmailUser($subject, $body, $user->email);
        } catch (Exception $e) {
            Log::error('Error sending verification email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending the verification email. ' . $e->getMessage(),
            ];
        }
    }

    function sendmailUser($subject, $body, $email)
    {
        $isHTML = true;

        $mail = new PHPMailer();

        $mail->IsSMTP();

        $mail->CharSet = 'UTF-8';

        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;

        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';

        $mail->Username = 'adnan.logelite@gmail.com';
        $mail->Password = 'owdwgrcdwcrlissi';

        // From address and name
        $mail->setFrom('adnan.logelite@gmail.com', '7Search PPC');
        $mail->addAddress($email);

        $mail->isHTML($isHTML);

        $mail->Subject = $subject;
        $mail->Body = $body;

        try {
            if ($mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email. PHPMailer Error: ' . $mail->ErrorInfo
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error in sending email: ' . $e->getMessage()
            ];
        }
    }

    public function verifyAccount(string $uid, string $token): array
    {
        try {
            $user = User::where('uid', $uid)->first();

            if (!$user || !$token || $user->verify_code === null || $token !== $user->verify_code) {
                return [
                    'success' => false,
                    'message' => 'This link has expired or is invalid!',
                ];
            }

            if ($user->ac_verified == 1) {
                return [
                    'success' => false,
                    'message' => 'Account is already verified.',
                ];
            }

            $user->update([
                'ac_verified' => 1,
                'email_verified_at' => "dsfgsdfgsfdg",
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

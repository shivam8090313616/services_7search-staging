<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use PHPMailer\PHPMailer\PHPMailer;

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
        try {
            // Find the user by UID
            $user = User::where('uid', $uid)->first();
    
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }
    
            // If method is 'sendMail', send verification email
            if ($method === 'sendMail') {
                $mailStatus = $this->sendVerificationEmail($user);
    
                if ($mailStatus['success']) {
                    return [
                        'success' => true,
                        'message' => 'Verification email sent successfully.',
                    ];
                } else {
                    // Return the detailed error message if the email could not be sent
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
    
    /**
     * Send a verification email to the user.
     *
     * @param User $user
     * @return array
     */
    protected function sendVerificationEmail(User $user): array
    {
        try {
            // Check if user is already verified
            if ($user->status == 1) {
                return [
                    'success' => false,
                    'message' => 'User already verified.',
                ]; 
            }

            // Prepare the email content
            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'userid' => $user->uid,  
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            // Render the email body using Blade view
            $body = View::make('emailtemp.user_verify_mail', $mailData)->render();

            // Send the verification email
            return $this->sendmailUser($subject, $body, $user->email);
        } catch (Exception $e) {
            Log::error('Error sending verification email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending the verification email.' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send an email using PHPMailer.
     *
     * @param string $subject
     * @param string $body
     * @param string $email
     * @return array
     */
    function sendmailUser($subject, $body, $email)
    {
        $isHTML = true;

        $mail = new PHPMailer();

        $mail->IsSMTP();

        $mail->CharSet = 'UTF-8';

        // Directly using values instead of env()
        $mail->Host       = 'smtp.gmail.com';   // Directly set SMTP host
        $mail->SMTPDebug  = 0;
        $mail->SMTPAuth   = true;

        // Set the port and encryption type directly
        $mail->Port       = 587;  // SMTP Port (587 for TLS)
        $mail->SMTPSecure = 'tls';  // Using TLS encryption (recommended)

        // Use direct values for SMTP authentication
        $mail->Username   = 'adnan.logelite@gmail.com';  // Your Gmail username
        $mail->Password   = 'owdwgrcdwcrlissi';  // Your Gmail app-specific password (if 2FA enabled)

        // From address and name
        $mail->setFrom('adnan.logelite@gmail.com', "7Search PPC");  // Sender email and name
        $mail->addAddress($email);  // Recipient's email

        // Set HTML format
        $mail->isHTML($isHTML);

        // Email subject and body
        $mail->Subject = $subject;
        $mail->Body    = $body;

        try {
            // Try sending the email
            if ($mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Verification email sent successfully.'
                ];  // Email sent successfully
            } else {
                // If email fails, return the PHPMailer error message
                return [
                    'success' => false,
                    'message' => 'Failed to send email. PHPMailer Error: ' . $mail->ErrorInfo
                ];
            }
        } catch (Exception $e) {
            // If an exception occurs, return the exception message
            return [
                'success' => false,
                'message' => 'Error in sending email: ' . $e->getMessage()
            ];
        }
    }

    public function verify($userId)
    {
        $status = User::select('ac_verified')->where('uid', $userId)->first();
        if ($status->ac_verified == 1) {
            return response()->json(['message' => 'This link has been expired!'], 403);
        }
        $user = User::where('verify_token', $token)->first();
        $first_name = $user->first_name;
        $last_name = $user->last_name;
        $email = $user->email;
        if (!empty($user)) {
            if ($user->status == 1) {
                return response()->json(['message' => 'This link has been expired!'], 403);
            }
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $user->verify_token_at);
            $from = Carbon::now()->format('Y-m-d H:i:s');
            $diff_in_hours = $to->diffInMinutes($from);
            if ($diff_in_hours >= 30) {
                return response()->json(['message' => 'This link has been expired!'], 400);
            }
            $user->status = 1;
            $user->verify_token = '';
            $user->email_verified_at = Carbon::now()->format('Y-m-d H:i:s');
            if ($user->save()) {
                $notif_id = 'NOTIF' . strtoupper(uniqid());
    
                Notification::create([
                    'notif_id' => $notif_id,
                    'user_id' => $userId,
                    'title' => "User Account Verified",
                    'noti_desc' => "User has verified it's account",
                    'noti_for' => 1
                ]);
                $adminMailData = [
                    'title' => 'New User Verified',
                    'body' => "A new referal partner varified it's account successfully.",
                    'name' => $first_name . ' ' . $last_name,
                    'id' => $userId,
                    'email' => $email,
                ];
                $adminSubject = "Referral Partner Verified";
                $adminBody = View('mail.admin_user_verified', $adminMailData);
                sendMailAdmin($adminSubject, $adminBody);
                return response()->json(['message' => 'Email confirmed please login!'], 200);
            }
        }
        return response()->json(['message' => 'This link has been expired!'], 400);
    }
}

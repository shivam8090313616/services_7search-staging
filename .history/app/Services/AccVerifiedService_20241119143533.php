<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            if ($user->status == 1) {
                return false; 
            }

            $token = Str::random(45);
            $user->verify_token = $token;
            $user->verify_token_at = now(); 
            $user->save();

            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'token' => $user->verify_token,
                'id' => $user->id,  
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            $body = View::make('mail.user_verify_mail', $mailData)->render();

            $this->sendMailUser($subject, $body, $user->email);

            return true;

        } catch (\Exception $e) {
            // Log the exception if needed
            \Log::error('Error sending verification email: ' . $e->getMessage());
            return false;
        }
    }

    protected function sendmailUser($subject, $body, $email)
    {
        $isHTML = true;
    
        $mail = new PHPMailer(true);
    
        try {
        
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port = 587; 
            $mail->Username = 'adnan.logelite@gmail.com'; 
            $mail->Password = 'owdwgrcdwcrlissi';  
            $mail->setFrom('adnan.logelite@gmail.com', '7SearchPPC');  
            $mail->addAddress($email);  
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body = $body;
            return $mail->send();
        } catch (Exception $e) {
            Log::error("Mail could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
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
                    // Return the error message if the email could not be sent
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

            // Generate a random verification token
            $token = Str::random(45);
            $user->verify_token = $token;
            $user->verify_token_at = now();
            $user->save();

            // Prepare the email content
            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'token' => $user->verify_token,
                'id' => $user->id,  
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            // Render the email body using Blade view
            $body = View::make('emailtemp.user_verify_mail', $mailData)->render();

            // Send the verification email
            return $this->sendMailUser($subject, $body, $user->email);
        } catch (Exception $e) {
            Log::error('Error sending verification email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while sending the verification email.',
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
    
        // Send email and return result
        if ($mail->send()) {
            return 1;  // Email sent successfully
        } else {
            return $mail->send();  // Email failed to send
        }
    }
    

}

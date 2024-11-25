<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

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

        // If no valid method is provided, return an error
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
            // Check if the user is already verified
            if ($user->status == 1) {
                return false; // User is already verified
            }

            // Generate a unique token for verification
            $token = Str::random(45);
            $user->verify_token = $token;
            $user->verify_token_at = now(); // Use the current time
            $user->save();

            // Set up the email subject and body
            $subject = 'Please Verify Your Email';
            $mailData = [
                'title' => 'Verification Mail from 7SearchPPC Referral',
                'body' => 'Please click on the link below to verify your account.',
                'token' => $user->verify_token,
                'id' => $user->id,  // Use the correct user ID
                'name' => $user->first_name . ' ' . $user->last_name,
            ];

            // Render the email body using a Blade view
            $body = View::make('mail.user_verify_mail', $mailData)->render();

            // Assuming you have a global mail function or use Laravel's Mail facade
            $this->sendMailUser($subject, $body, $user->email);

            return true;

        } catch (\Exception $e) {
            // Log the exception if needed
            \Log::error('Error sending verification email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the email using Laravel's Mail facade or custom email function.
     *
     * @param string $subject
     * @param string $body
     * @param string $email
     * @return bool
     */
    protected function sendMailUser(string $subject, string $body, string $email): bool
    {
        try {
            // Use Laravel's Mail facade or your custom email sending logic
            Mail::raw($body, function ($message) use ($subject, $email) {
                $message->to($email)
                        ->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send email to ' . $email . ': ' . $e->getMessage());
            return false;
        }
    }
}

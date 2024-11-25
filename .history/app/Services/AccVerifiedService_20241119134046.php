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
            return [
                'success' => $,
                'message' => 'Verification email sent successfully.',
            ];
            
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
            // Assuming you have a Mailable class for sending verification emails
            // Mail::to($user->email)->send(new VerificationEmail($user));
            return true;
        } catch (\Exception $e) {
            // Log the error and return false if the email couldn't be sent
            // \Log::error("Failed to send verification email to user: " . $user->uid);
            return false;
        }
    }
}

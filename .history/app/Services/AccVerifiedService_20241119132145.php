<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AccVerifiedService
{
    /**
     * Check account verification or send a verification email.
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

        // If no method, return ac_verified
        if (is_null($method)) {
            return [
                'success' => true,
                'ac_verified' => $user->ac_verified,
            ];
        }

        // If method is provided, send verification email
        if ($method === 'sendMail') {
            $this->sendVerificationEmail($user);

            return [
                'success' => true,
                'message' => 'Verification email sent successfully.',
            ];
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
     */
    protected function sendVerificationEmail(User $user)
    {
        return se
    }
}

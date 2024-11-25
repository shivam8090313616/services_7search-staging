<?php

namespace App\Services;

use App\Models\User;

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
     * Dummy function to send a verification email.
     *
     * @param User $user
     * @return bool
     */
    protected function sendVerificationEmail(User $user): bool
    {

        try {
         
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
